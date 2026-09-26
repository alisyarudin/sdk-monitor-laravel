<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Logger;

class DebugFileLogger extends DebugLogger
{
    /**
     * @var string
     */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function write(string $message): void
    {
        file_put_contents($this->filePath, $message, \FILE_APPEND);
    }
}
