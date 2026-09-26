<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer\EnvelopItems;

use Jasnita\Monitor\Sdk\Event;

/**
 * @internal
 */
interface EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): ?string;
}
