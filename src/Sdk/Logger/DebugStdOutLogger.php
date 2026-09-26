<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Logger;

class DebugStdOutLogger extends DebugLogger
{
    public function write(string $message): void
    {
        file_put_contents('php://stdout', $message);
    }
}
