<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features\Ai;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\RequestSending;
use Jasnita\Monitor\Laravel\Features\AiIntegration;
use Jasnita\Monitor\Laravel\Features\Feature;

/**
 * This integration is only used to produce chat spans before http.client spans are emitted
 * by the generic HTTP integration to create a proper hierarchy
 *
 * @internal
 */
class HttpRequestIntegration extends Feature
{
    public function isApplicable(): bool
    {
        return class_exists(RequestSending::class)
            && $this->container()->make(AiIntegration::class)->isApplicable();
    }

    public function onBoot(Dispatcher $events): void
    {
        $events->listen(RequestSending::class, [AiIntegration::class, 'handleHttpRequestSending']);
    }
}
