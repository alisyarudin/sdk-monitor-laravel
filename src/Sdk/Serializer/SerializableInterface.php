<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer;

/**
 * This interface can be used to customize how an object is serialized in the
 * payload of an event.
 */
interface SerializableInterface
{
    /**
     * Returns an array representation of the object for Jasnita.
     *
     * @return mixed[]|null
     */
    public function toJasnita(): ?array;
}
