<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tracing\Routing;

use Closure;
use Illuminate\Routing\Route;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;

abstract class TracingRoutingDispatcher
{
    protected function wrapRouteDispatch(callable $dispatch, Route $route)
    {
        $parentSpan = JasnitaSdk::getCurrentHub()->getSpan();

        // If there is no sampled span there is no need to wrap the dispatch
        if ($parentSpan === null || !$parentSpan->getSampled()) {
            return $dispatch();
        }

        // The action name can be a Closure curiously enough... so we guard againt that here
        // @see: (dokumentasi hulu)
        $action = $route->getActionName() instanceof Closure ? 'Closure' : $route->getActionName();

        $span = $parentSpan->startChild(
            SpanContext::make()
                ->setOp('http.route')
                ->setOrigin('auto.http.server')
                ->setDescription($action)
        );

        JasnitaSdk::getCurrentHub()->setSpan($span);

        try {
            return $dispatch();
        } finally {
            $span->finish();

            JasnitaSdk::getCurrentHub()->setSpan($parentSpan);
        }
    }
}
