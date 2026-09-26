<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Serializer\EnvelopItems;

use Jasnita\Monitor\Sdk\Attachment\Attachment;
use Jasnita\Monitor\Sdk\Util\JSON;

class AttachmentItem
{
    public static function toAttachmentItem(Attachment $attachment): ?string
    {
        $data = $attachment->getData();
        if ($data === null) {
            return null;
        }

        $header = [
            'type' => 'attachment',
            'filename' => $attachment->getFilename(),
            'content_type' => $attachment->getContentType(),
            'attachment_type' => 'event.attachment',
            'length' => \strlen($data),
        ];

        return \sprintf("%s\n%s", JSON::encode($header), $data);
    }
}
