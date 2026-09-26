<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Integration;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Allows customizing the request information that is attached to the logged event.
 * An implementation of this interface can be passed to RequestIntegration.
 */
interface RequestFetcherInterface
{
    /**
     * Returns the PSR-7 request object that will be attached to the logged event.
     */
    public function fetchRequest(): ?ServerRequestInterface;
}
