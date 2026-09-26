<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Transport;

use Jasnita\Monitor\Sdk\Event;

interface TransportInterface
{
    public function send(Event $event): Result;

    public function close(?int $timeout = null): Result;
}
