<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Http;

use Closure;
use Illuminate\Http\Request;
use Jasnita\Monitor\Laravel\Integration;

class FlushEventsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        Integration::flushEvents();
    }
}
