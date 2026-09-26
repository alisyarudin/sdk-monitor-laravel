<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Logger;

use Psr\Log\AbstractLogger;

abstract class DebugLogger extends AbstractLogger
{
    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param mixed[]            $context
     */
    public function log($level, $message, array $context = []): void
    {
        $formattedMessageAndContext = implode(' ', array_filter([(string) $message, json_encode($context)]));

        $this->write(
            \sprintf("jasnita/monitor-laravel: [%s] %s\n", $level, $formattedMessageAndContext)
        );
    }

    abstract public function write(string $message): void;
}
