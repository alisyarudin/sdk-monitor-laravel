<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer\EnvelopItems;

use Jasnita\Monitor\Sdk\Attributes\Attribute;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventType;
use Jasnita\Monitor\Sdk\Logs\Log;
use Jasnita\Monitor\Sdk\Util\JSON;

/**
 * @internal
 */
class LogsItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $logs = $event->getLogs();

        $header = [
            'type' => (string) EventType::logs(),
            'item_count' => \count($logs),
            'content_type' => 'application/vnd.jasnita.items.log+json',
        ];

        return \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => array_map(static function (Log $log): array {
                    return [
                        'timestamp' => $log->getTimestamp(),
                        'trace_id' => $log->getTraceId(),
                        'level' => (string) $log->getLevel(),
                        'body' => $log->getBody(),
                        'attributes' => array_map(static function (Attribute $attribute): array {
                            return [
                                'type' => $attribute->getType(),
                                'value' => $attribute->getValue(),
                            ];
                        }, $log->attributes()->all()),
                    ];
                }, $logs),
            ])
        );
    }
}
