<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Sdk\State\Scope;

/**
 * This Monolog handler logs every message to a Jasnita's server using the given
 * hub instance.
 *
 * @deprecated since version 4.24. To be removed in version 5.0. Use {@see LogsHandler}
 *             for Jasnita logs, {@see ExceptionToJasnitaIssueHandler} to send Monolog exceptions
 *             to Jasnita issues, and {@see LogToJasnitaIssueHandler}
 *             to send Monolog log messages to Jasnita issues.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Handler extends AbstractProcessingHandler
{
    use CompatibilityProcessingHandlerTrait;

    private const CONTEXT_EXCEPTION_KEY = 'exception';

    /**
     * @var HubInterface
     */
    private $hub;

    /**
     * @var bool
     */
    private $fillExtraContext;

    /**
     * {@inheritdoc}
     *
     * @param HubInterface $hub The hub to which errors are reported
     */
    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true, bool $fillExtraContext = false)
    {
        parent::__construct($level, $bubble);

        $this->hub = $hub;
        $this->fillExtraContext = $fillExtraContext;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    protected function doWrite($record): void
    {
        $event = Event::createEvent();
        $event->setLevel(self::getSeverityFromLevel($record['level']));
        $event->setMessage($record['message']);
        $event->setLogger(\sprintf('monolog.%s', $record['channel']));

        $hint = new EventHint();

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $hint->exception = $record['context']['exception'];
        }

        $this->hub->withScope(function (Scope $scope) use ($record, $event, $hint): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);

            $monologContextData = $this->getMonologContextData($record['context']);

            if ($monologContextData !== []) {
                $scope->setExtra('monolog.context', $monologContextData);
            }

            $monologExtraData = $this->getMonologExtraData($record['extra']);

            if ($monologExtraData !== []) {
                $scope->setExtra('monolog.extra', $monologExtraData);
            }

            $this->hub->captureEvent($event, $hint);
        });
    }

    /**
     * @param mixed[] $context
     *
     * @return mixed[]
     */
    private function getMonologContextData(array $context): array
    {
        if (!$this->fillExtraContext) {
            return [];
        }

        $contextData = [];

        foreach ($context as $key => $value) {
            // We skip the `exception` field because it goes in its own context
            if ($key === self::CONTEXT_EXCEPTION_KEY) {
                continue;
            }

            $contextData[$key] = $value;
        }

        return $contextData;
    }

    /**
     * @param mixed[] $context
     *
     * @return mixed[]
     */
    private function getMonologExtraData(array $context): array
    {
        if (!$this->fillExtraContext) {
            return [];
        }

        $extraData = [];

        foreach ($context as $key => $value) {
            $extraData[$key] = $value;
        }

        return $extraData;
    }
}
