<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer;

use Jasnita\Monitor\Sdk\Event;

/**
 * This interface defines the contract for the classes willing to serialize an
 * event object to a format suitable for sending over the wire to Jasnita.
 */
interface PayloadSerializerInterface
{
    /**
     * Serializes the given event object into a string.
     *
     * @param Event $event The event object
     */
    public function serialize(Event $event): string;
}
