<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Integration;

use Jasnita\Monitor\Sdk\ErrorHandler;
use Jasnita\Monitor\Sdk\Exception\SilencedErrorException;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;

/**
 * This integration hooks into the global error handlers and emits events to
 * Jasnita.
 */
final class ErrorListenerIntegration extends AbstractErrorListenerIntegration implements OptionAwareIntegrationInterface
{
    /**
     * @var Options
     */
    private $options;

    public function setOptions(Options $options): void
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        ErrorHandler::registerOnceErrorHandler($this->options)
                    ->addErrorHandlerListener(
                        static function (\ErrorException $exception): void {
                            $currentHub = JasnitaSdk::getCurrentHub();
                            $integration = $currentHub->getIntegration(self::class);
                            $client = $currentHub->getClient();

                            // The client bound to the current hub, if any, could not have this
                            // integration enabled. If this is the case, bail out
                            if ($integration === null || $client === null) {
                                return;
                            }

                            if ($exception instanceof SilencedErrorException && !$client->getOptions()->shouldCaptureSilencedErrors()) {
                                return;
                            }

                            if (!$exception instanceof SilencedErrorException && !($client->getOptions()->getErrorTypes() & $exception->getSeverity())) {
                                return;
                            }

                            $integration->captureException($currentHub, $exception);
                        }
                    );
    }

    /**
     * @internal this is a convenience method to create an instance of this integration for tests
     */
    public static function make(Options $options): self
    {
        $integration = new self();

        $integration->setOptions($options);

        return $integration;
    }
}
