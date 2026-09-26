<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Http;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Sdk\State\Scope;

/**
 * This middleware enriches the Jasnita scope with the IP address of the request.
 * We do this ourself instead of letting the PHP SDK handle this because we want
 * the IP from the Laravel request because it takes into account trusted proxies.
 */
class SetRequestIpMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $container = Container::getInstance();

        if ($container->bound(HubInterface::class)) {
            /** @var \Jasnita\Monitor\Sdk\State\HubInterface $jasnita */
            $jasnita = $container->make(HubInterface::class);

            $client = $jasnita->getClient();

            if ($client !== null && $client->getOptions()->shouldSendDefaultPii()) {
                $jasnita->configureScope(static function (Scope $scope) use ($request): void {
                    $scope->setUser([
                        'ip_address' => $request->ip(),
                    ]);
                });
            }
        }

        return $next($request);
    }
}
