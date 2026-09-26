<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Integration;

use Jasnita\Monitor\Sdk\ErrorHandler;
use Jasnita\Monitor\Sdk\Exception\FatalErrorException;
use Jasnita\Monitor\Sdk\JasnitaSdk;

/**
 * This integration hooks into the error handler and captures fatal errors.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class FatalErrorListenerIntegration extends AbstractErrorListenerIntegration
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
        $errorHandler->addFatalErrorHandlerListener(static function (FatalErrorException $exception): void {
            $currentHub = JasnitaSdk::getCurrentHub();
            $integration = $currentHub->getIntegration(self::class);
            $client = $currentHub->getClient();

            // The client bound to the current hub, if any, could not have this
            // integration enabled. If this is the case, bail out
            if (null === $integration || null === $client) {
                return;
            }

            if (!($client->getOptions()->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            $integration->captureException($currentHub, $exception);
        });
    }
}
