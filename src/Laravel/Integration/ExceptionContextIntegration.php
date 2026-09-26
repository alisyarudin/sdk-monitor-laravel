<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Integration;

use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Integration\IntegrationInterface;

class ExceptionContextIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event, ?EventHint $hint = null): Event {
            $self = JasnitaSdk::getCurrentHub()->getIntegration(self::class);

            if (!$self instanceof self) {
                return $event;
            }

            if ($hint === null || $hint->exception === null) {
                return $event;
            }

            if (!method_exists($hint->exception, 'context')) {
                return $event;
            }

            $context = $hint->exception->context();

            if (is_array($context)) {
                $event->setExtra(['exception_context' => $context]);
            }

            return $event;
        });
    }
}
