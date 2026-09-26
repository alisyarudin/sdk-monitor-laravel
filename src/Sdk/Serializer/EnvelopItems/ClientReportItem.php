<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer\EnvelopItems;

use Jasnita\Monitor\Sdk\ClientReport\DiscardedEvent;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Util\JSON;

class ClientReportItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): ?string
    {
        $reports = $event->getClientReports();

        $headers = ['type' => 'client_report'];
        $body = [
            'timestamp' => $event->getTimestamp(),
            'discarded_events' => array_map(static function (DiscardedEvent $report) {
                return [
                    'category' => $report->getCategory(),
                    'reason' => $report->getReason(),
                    'quantity' => $report->getQuantity(),
                ];
            }, $reports),
        ];

        return \sprintf("%s\n%s", JSON::encode($headers), JSON::encode($body));
    }
}
