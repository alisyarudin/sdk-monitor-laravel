<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Integration;

use Illuminate\Log\Context\Repository as ContextRepository;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\EventType;
use Jasnita\Monitor\Sdk\Integration\IntegrationInterface;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;

class LaravelContextIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        // Context was introduced in Laravel 11 so we need to check if we can use it otherwise we skip the event processor
        if (!class_exists(ContextRepository::class)) {
            return;
        }

        Scope::addGlobalEventProcessor(static function (Event $event, ?EventHint $hint = null): Event {
            $self = JasnitaSdk::getCurrentHub()->getIntegration(self::class);

            if (!$self instanceof self) {
                return $event;
            }

            if (!in_array($event->getType(), [EventType::event(), EventType::transaction()], true)) {
                return $event;
            }

            $event->setContext('laravel', app(ContextRepository::class)->all());

            return $event;
        });
    }
}
