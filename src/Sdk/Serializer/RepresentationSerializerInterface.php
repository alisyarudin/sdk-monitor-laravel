<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer;

/**
 * Serializes a value into a representation that should reasonably suggest
 * both the type and value, and be serializable into JSON.
 */
interface RepresentationSerializerInterface
{
    /**
     * Serialize an object (recursively) into something safe to be sent as a stacktrace frame argument.
     *
     * The main difference with the {@link SerializerInterface} is the fact that even basic types
     * (bool, int, float) are converted into strings, to avoid misrepresentations on the server side.
     *
     * @param mixed $value
     *
     * @return mixed[]|string|null
     */
    public function representationSerialize($value);
}
