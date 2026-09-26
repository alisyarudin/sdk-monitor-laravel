<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Integration;

use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;

/**
 * This integration sets the `transaction` attribute of the event to the value
 * found in the raw event payload or to the value of the `PATH_INFO` server var
 * if present.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class TransactionIntegration implements IntegrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event, EventHint $hint): Event {
            $integration = JasnitaSdk::getCurrentHub()->getIntegration(self::class);

            // The client bound to the current hub, if any, could not have this
            // integration enabled. If this is the case, bail out
            if (null === $integration) {
                return $event;
            }

            if (null !== $event->getTransaction()) {
                return $event;
            }

            if (isset($hint->extra['transaction']) && \is_string($hint->extra['transaction'])) {
                $event->setTransaction($hint->extra['transaction']);
            } elseif (isset($_SERVER['PATH_INFO'])) {
                $event->setTransaction($_SERVER['PATH_INFO']);
            }

            return $event;
        });
    }
}
