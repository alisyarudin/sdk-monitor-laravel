<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Transport;

use Jasnita\Monitor\Sdk\Options;

/**
 * This interface defines a contract for all classes willing to create instances
 * of the transport to use with the Jasnita client.
 */
interface TransportFactoryInterface
{
    /**
     * Creates a new instance of a transport that will be used to send events.
     *
     * @param Options $options The options of the Jasnita client
     */
    public function create(Options $options): TransportInterface;
}
