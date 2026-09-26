<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer;

use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventType;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\AttachmentItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\CheckInItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\ClientReportItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\EventItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\LogsItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\MetricsItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\ProfileItem;
use Jasnita\Monitor\Sdk\Serializer\EnvelopItems\TransactionItem;
use Jasnita\Monitor\Sdk\Tracing\DynamicSamplingContext;
use Jasnita\Monitor\Sdk\Util\JSON;

/**
 * This is a simple implementation of a serializer that takes in input an event
 * object and returns a serialized string ready to be sent off to Jasnita.
 *
 * @internal
 */
final class PayloadSerializer implements PayloadSerializerInterface
{
    /**
     * @var Options The SDK client options
     */
    private $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(Event $event): string
    {
        $envelopeHeader = null;
        if ($event->getType() !== EventType::clientReport()) {
            // @see (dokumentasi hulu)
            $envelopeHeader = [
                'sent_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'dsn' => (string) $this->options->getDsn(),
                'sdk' => $event->getSdkPayload(),
            ];

            if ($event->getType()->requiresEventId()) {
                $envelopeHeader['event_id'] = (string) $event->getId();
            }

            $dynamicSamplingContext = $event->getSdkMetadata('dynamic_sampling_context');
            if ($dynamicSamplingContext instanceof DynamicSamplingContext) {
                $entries = $dynamicSamplingContext->getEntries();

                if (!empty($entries)) {
                    $envelopeHeader['trace'] = $entries;
                }
            }
        }

        $items = [];

        switch ($event->getType()) {
            case EventType::event():
                $items[] = EventItem::toEnvelopeItem($event);
                foreach ($event->getAttachments() as $attachment) {
                    $items[] = AttachmentItem::toAttachmentItem($attachment);
                }
                break;
            case EventType::transaction():
                $items[] = TransactionItem::toEnvelopeItem($event);
                if ($event->getSdkMetadata('profile') !== null) {
                    $items[] = ProfileItem::toEnvelopeItem($event);
                }
                foreach ($event->getAttachments() as $attachment) {
                    $items[] = AttachmentItem::toAttachmentItem($attachment);
                }
                break;
            case EventType::checkIn():
                $items[] = CheckInItem::toEnvelopeItem($event);
                break;
            case EventType::logs():
                $items[] = LogsItem::toEnvelopeItem($event);
                break;
            case EventType::metrics():
                $items[] = MetricsItem::toEnvelopeItem($event);
                break;
            case EventType::clientReport():
                $items[] = ClientReportItem::toEnvelopeItem($event);
                break;
        }

        if ($envelopeHeader === null) {
            return \sprintf("{}\n%s", implode("\n", array_filter($items)));
        }

        return \sprintf("%s\n%s", JSON::encode($envelopeHeader), implode("\n", array_filter($items)));
    }
}
