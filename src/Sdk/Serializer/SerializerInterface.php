<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer;

/**
 * Basic serializer for the event data.
 */
interface SerializerInterface
{
    /**
     * Serialize an object (recursively) into something safe to be sent in an Event.
     *
     * @param mixed $value
     *
     * @return string|bool|float|int|object|mixed[]|null
     */
    public function serialize($value);
}
