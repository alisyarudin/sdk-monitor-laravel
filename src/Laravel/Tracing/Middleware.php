<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tracing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;
use Symfony\Component\HttpFoundation\Response;

class Middleware
{
    /**
     * The current active transaction.
     *
     * @var \Jasnita\Monitor\Sdk\Tracing\Transaction|null
     */
    protected $transaction;

    /**
     * The span for the `app.handle` part of the application.
     *
     * @var \Jasnita\Monitor\Sdk\Tracing\Span|null
     */
    protected $appSpan;

    /**
     * The timestamp of application bootstrap completion.
     *
     * @var float|null
     */
    private $bootedTimestamp;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (app()->bound(HubInterface::class)) {
            $this->startTransaction($request, app(HubInterface::class));
        }

        return $next($request);
    }

    /**
     * Handle the application termination.
     *
     * @param \Illuminate\Http\Request                   $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function terminate($request, $response): void
    {
        if ($this->transaction !== null && app()->bound(HubInterface::class)) {
            if ($this->appSpan !== null) {
                $this->appSpan->finish();
            }

            // Make sure we set the transaction and not have a child span in the Jasnita SDK
            // If the transaction is not on the scope during finish, the trace.context is wrong
            JasnitaSdk::getCurrentHub()->setSpan($this->transaction);

            if ($request instanceof Request) {
                $this->hydrateRequestData($request);
            }

            if ($response instanceof Response) {
                $this->hydrateResponseData($response);
            }

            $this->transaction->finish();
        }
    }

    /**
     * Set the timestamp of application bootstrap completion.
     *
     * @param float|null $timestamp The unix timestamp of the booted event, default to `microtime(true)` if not `null`.
     *
     * @return void
     * @internal This method should only be invoked right after the application has finished "booting":
     *           For Laravel this is from the application `booted` callback.
     *           For Lumen this is right before returning from the `bootstrap/app.php` file.
     */
    public function setBootedTimestamp(?float $timestamp = null): void
    {
        $this->bootedTimestamp = $timestamp ?? microtime(true);
    }

    private function startTransaction(Request $request, HubInterface $jasnita): void
    {
        $requestStartTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $jasnitaTraceHeader = $request->header('jasnita-trace');

        $context = $jasnitaTraceHeader
            ? TransactionContext::fromJasnitaTrace($jasnitaTraceHeader)
            : new TransactionContext;

        $context->setOp('http.server');
        $context->setData([
            'url' => '/' . ltrim($request->path(), '/'),
            'method' => strtoupper($request->method()),
        ]);
        $context->setStartTimestamp($requestStartTime);

        $this->transaction = $jasnita->startTransaction($context);

        // Setting the Transaction on the Hub
        JasnitaSdk::getCurrentHub()->setSpan($this->transaction);

        $bootstrapSpan = $this->addAppBootstrapSpan($request);

        $appContextStart = new SpanContext();
        $appContextStart->setOp('middleware.handle');
        $appContextStart->setStartTimestamp($bootstrapSpan ? $bootstrapSpan->getEndTimestamp() : microtime(true));

        $this->appSpan = $this->transaction->startChild($appContextStart);

        JasnitaSdk::getCurrentHub()->setSpan($this->appSpan);
    }

    private function addAppBootstrapSpan(Request $request): ?Span
    {
        if ($this->bootedTimestamp === null) {
            return null;
        }

        $laravelStartTime = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');

        if ($laravelStartTime === null) {
            return null;
        }

        $spanContextStart = new SpanContext();
        $spanContextStart->setOp('app.bootstrap');
        $spanContextStart->setStartTimestamp($laravelStartTime);
        $spanContextStart->setEndTimestamp($this->bootedTimestamp);

        $span = $this->transaction->startChild($spanContextStart);

        // Consume the booted timestamp, because we don't want to report the bootstrap span more than once
        $this->bootedTimestamp = null;

        // Add more information about the bootstrap section if possible
        $this->addBootDetailTimeSpans($span);

        return $span;
    }

    private function addBootDetailTimeSpans(Span $bootstrap): void
    {
        // This constant should be defined right after the composer `autoload.php` require statement in `public/index.php`
        // define('JASNITA_MONITOR_AUTOLOAD', microtime(true));
        if (!defined('JASNITA_MONITOR_AUTOLOAD') || !JASNITA_MONITOR_AUTOLOAD) {
            return;
        }

        $autoload = new SpanContext();
        $autoload->setOp('app.php.autoload');
        $autoload->setStartTimestamp($bootstrap->getStartTimestamp());
        $autoload->setEndTimestamp(JASNITA_MONITOR_AUTOLOAD);

        $bootstrap->startChild($autoload);
    }

    private function hydrateRequestData(Request $request): void
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $this->updateTransactionNameIfDefault(
                Integration::extractNameForRoute($route)
            );

            $this->transaction->setData([
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'method' => $request->getMethod(),
            ]);
        } elseif (is_array($route) && count($route) === 3) {
            $this->updateTransactionNameIfDefault(
                Integration::extractNameForLumenRoute($route, $request->path())
            );

            $action = $route[1] ?? [];

            $this->transaction->setData([
                'name' => $action['as'] ?? null,
                'action' => $action['uses'] ?? 'Closure',
                'method' => $request->getMethod(),
            ]);
        }

        $this->updateTransactionNameIfDefault('/' . ltrim($request->path(), '/'));
    }

    private function hydrateResponseData(Response $response): void
    {
        $this->transaction->setHttpStatus($response->getStatusCode());
    }

    private function updateTransactionNameIfDefault(?string $name): void
    {
        // Ignore empty names (and `null`) for caller convenience
        if (empty($name)) {
            return;
        }

        // If the transaction already has a name other than the default
        // ignore the new name, this will most occur if the user has set a
        // transaction name themself before the application reaches this point
        if ($this->transaction->getName() !== TransactionContext::DEFAULT_NAME) {
            return;
        }

        $this->transaction->setName($name);
    }
}
