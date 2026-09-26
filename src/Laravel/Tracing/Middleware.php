<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tracing;

use Closure;
use Illuminate\Http\Request;
use Laravel\Lumen\Application as LumenApplication;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionSource;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use function Jasnita\Monitor\Sdk\continueTrace;

/**
 * @internal
 */
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
     * Whether we should continue tracing after the response has been sent to the client.
     *
     * @var bool
     */
    private $continueAfterResponse;

    /**
     * Whether a defined route was matched in the application.
     *
     * @var bool
     */
    private $didRouteMatch = false;

    /**
     * Construct the Jasnita tracing middleware.
     */
    public function __construct(bool $continueAfterResponse = true)
    {
        $this->continueAfterResponse = $continueAfterResponse;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (app()->bound(HubInterface::class)) {
            $this->startTransaction($request);
        }

        return $next($request);
    }

    /**
     * Handle the application termination.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $response
     *
     * @return void
     */
    public function terminate(Request $request, $response): void
    {
        // If there is no transaction there is nothing for us to do
        if ($this->transaction === null) {
            return;
        }

        if ($this->shouldRouteBeIgnored($request)) {
            $this->discardTransaction();

            return;
        }

        if ($this->appSpan !== null) {
            $this->appSpan->finish();
            $this->appSpan = null;
        }

        if ($response instanceof SymfonyResponse) {
            $this->hydrateResponseData($response);
        }

        if ($this->continueAfterResponse) {
            // Resolving the transaction finisher class will register the terminating callback
            // which is responsible for calling `finishTransaction`. We have registered the
            // class as a singleton to keep the state in the container and away from here
            // this way we ensure the callback is only registered once even for Octane.
            app(TransactionFinisher::class);
        } else {
            $this->finishTransaction();
        }
    }

    /**
     * Set the timestamp of application bootstrap completion.
     *
     * @param float|null $timestamp The unix timestamp of the booted event, default to `microtime(true)` if not `null`.
     *
     * @return void
     *
     * @internal This method should only be invoked right after the application has finished "booting".
     */
    public function setBootedTimestamp(?float $timestamp = null): void
    {
        $this->bootedTimestamp = $timestamp ?? microtime(true);
    }

    private function startTransaction(Request $request): void
    {
        $hub = JasnitaSdk::getCurrentHub();
        $client = $hub->getClient();

        // Prevent starting a new transaction if we are already in a transaction
        if ($hub->getTransaction() !== null) {
            return;
        }

        // Reset our internal state in case we are handling multiple requests (e.g. in Octane)
        $this->didRouteMatch = false;

        // Try $_SERVER['REQUEST_TIME_FLOAT'] then LARAVEL_START and fallback to microtime(true) if neither are defined
        $requestStartTime = $request->server(
            'REQUEST_TIME_FLOAT',
            defined('LARAVEL_START')
                ? LARAVEL_START
                : microtime(true)
        );

        $context = continueTrace(
            $request->header('jasnita-trace', ''),
            $request->header('baggage', '')
        );

        // OTLP external propagation relies on the scope not already carrying a Jasnita span,
        // but continueTrace() still needs to run so incoming distributed trace headers can
        // populate the scope propagation context while tracing is disabled.
        if ($client === null || !$client->getOptions()->isTracingEnabled()) {
            return;
        }

        $requestPath = '/' . ltrim($request->path(), '/');

        $context->setOp('http.server');
        $context->setName($requestPath);
        $context->setOrigin('auto.http.server');
        $context->setSource(TransactionSource::url());
        $context->setStartTimestamp($requestStartTime);

        $context->setData([
            'url' => $requestPath,
            'http.request.method' => strtoupper($request->method()),
        ]);

        $transaction = $hub->startTransaction($context);

        $hub->setSpan($transaction);

        // If this transaction is not sampled, we can stop here to prevent doing work for nothing
        if (!$transaction->getSampled()) {
            return;
        }

        $this->transaction = $transaction;

        $bootstrapSpan = $this->addAppBootstrapSpan();

        $this->appSpan = $this->transaction->startChild(
            SpanContext::make()
                ->setOp('middleware.handle')
                ->setOrigin('auto.http.server')
                ->setStartTimestamp($bootstrapSpan ? $bootstrapSpan->getEndTimestamp() : microtime(true))
        );

        $hub->setSpan($this->appSpan);
    }

    private function discardTransaction(): void
    {
        $this->appSpan = null;
        $this->transaction = null;
        $this->didRouteMatch = false;
        $this->bootedTimestamp = null;

        JasnitaSdk::getCurrentHub()->setSpan(null);
    }

    private function addAppBootstrapSpan(): ?Span
    {
        if ($this->bootedTimestamp === null) {
            return null;
        }

        $span = $this->transaction->startChild(
            SpanContext::make()
                ->setOp('app.bootstrap')
                ->setOrigin('auto.http.server')
                ->setStartTimestamp($this->transaction->getStartTimestamp())
                ->setEndTimestamp($this->bootedTimestamp)
        );

        // Add more information about the bootstrap section if possible
        $this->addBootDetailTimeSpans($span);

        // Consume the booted timestamp, because we don't want to report the boot(strap) spans more than once
        $this->bootedTimestamp = null;

        return $span;
    }

    private function addBootDetailTimeSpans(Span $bootstrap): void
    {
        // This constant should be defined right after the composer `autoload.php` require statement in `public/index.php`
        // define('JASNITA_MONITOR_AUTOLOAD', microtime(true));
        if (!defined('JASNITA_MONITOR_AUTOLOAD') || !JASNITA_MONITOR_AUTOLOAD) {
            return;
        }

        $bootstrap->startChild(
            SpanContext::make()
                ->setOp('app.php.autoload')
                ->setOrigin('auto.http.server')
                ->setStartTimestamp($this->transaction->getStartTimestamp())
                ->setEndTimestamp(JASNITA_MONITOR_AUTOLOAD)
        );
    }

    private function hydrateResponseData(SymfonyResponse $response): void
    {
        $this->transaction->setHttpStatus($response->getStatusCode());
        $this->transaction->setData([
            'http.response.status_code' => $response->getStatusCode(),
        ]);
    }

    public function finishTransaction(): void
    {
        // We could end up multiple times here since we register a terminating callback so
        // double check if we have a transaction before trying to finish it since it could
        // have already been finished in between being registered and being executed again
        if ($this->transaction === null) {
            return;
        }

        // Make sure we set the transaction and not have a child span in the Jasnita SDK
        // If the transaction is not on the scope during finish, the trace.context is wrong
        JasnitaSdk::getCurrentHub()->setSpan($this->transaction);

        $this->transaction->finish();
        $this->transaction = null;
    }

    private function internalSignalRouteWasMatched(): void
    {
        $this->didRouteMatch = true;
    }

    /**
     * Indicates if the route should be ignored and the transaction discarded.
     */
    private function shouldRouteBeIgnored(Request $request): bool
    {
        // Laravel Lumen doesn't use `illuminate/routing`.
        // Instead we use the route available on the request to detect if a route was matched.
        if (app() instanceof LumenApplication) {
            return $request->route() === null && config('jasnita.tracing.missing_routes', false) === false;
        }

        // If a route has not been matched we ignore unless we are configured to trace missing routes
        return !$this->didRouteMatch && config('jasnita.tracing.missing_routes', false) === false;
    }

    public static function signalRouteWasMatched(): void
    {
        if (!app()->bound(self::class)) {
            return;
        }

        app(self::class)->internalSignalRouteWasMatched();
    }
}
