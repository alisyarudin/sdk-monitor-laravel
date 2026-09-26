<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer\EnvelopItems;

use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Profiling\Profile;
use Jasnita\Monitor\Sdk\Util\JSON;

/**
 * @internal
 */
class ProfileItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): ?string
    {
        $header = [
            'type' => 'profile',
            'content_type' => 'application/json',
        ];

        $profile = $event->getSdkMetadata('profile');
        if (!$profile instanceof Profile) {
            return null;
        }

        $payload = $profile->getFormattedData($event);
        if ($payload === null) {
            return null;
        }

        return \sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
    }
}
