<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Integration;

use Jasnita\Monitor\Sdk\Options;

interface OptionAwareIntegrationInterface extends IntegrationInterface
{
    /**
     * Sets the options for the integration, is called before `setupOnce()`.
     */
    public function setOptions(Options $options): void;
}
