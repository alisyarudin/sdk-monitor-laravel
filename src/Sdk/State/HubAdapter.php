<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\State;

use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Sdk\CheckInStatus;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\EventId;
use Jasnita\Monitor\Sdk\Integration\IntegrationInterface;
use Jasnita\Monitor\Sdk\MonitorConfig;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Severity;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\Transaction;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;

/**
 * An implementation of {@see HubInterface} that uses {@see JasnitaSdk} internally
 * to manage the current hub.
 */
final class HubAdapter implements HubInterface
{
    /**
     * @var self|null The single instance which forwards all calls to {@see JasnitaSdk}
     */
    private static $instance;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Gets the instance of this class. This is a singleton, so once initialized
     * you will always get the same instance.
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ?ClientInterface
    {
        return JasnitaSdk::getCurrentHub()->getClient();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEventId(): ?EventId
    {
        return JasnitaSdk::getCurrentHub()->getLastEventId();
    }

    /**
     * {@inheritdoc}
     */
    public function pushScope(): Scope
    {
        return JasnitaSdk::getCurrentHub()->pushScope();
    }

    /**
     * {@inheritdoc}
     */
    public function popScope(): bool
    {
        return JasnitaSdk::getCurrentHub()->popScope();
    }

    /**
     * {@inheritdoc}
     */
    public function withScope(callable $callback)
    {
        return JasnitaSdk::getCurrentHub()->withScope($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function configureScope(callable $callback): void
    {
        JasnitaSdk::getCurrentHub()->configureScope($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function bindClient(ClientInterface $client): void
    {
        JasnitaSdk::getCurrentHub()->bindClient($client);
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
    {
        return JasnitaSdk::getCurrentHub()->captureMessage($message, $level, $hint);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        return JasnitaSdk::getCurrentHub()->captureException($exception, $hint);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
    {
        return JasnitaSdk::getCurrentHub()->captureEvent($event, $hint);
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(?EventHint $hint = null): ?EventId
    {
        return JasnitaSdk::getCurrentHub()->captureLastError($hint);
    }

    /**
     * {@inheritdoc}
     *
     * @param int|float|null $duration
     */
    public function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string
    {
        return JasnitaSdk::getCurrentHub()->captureCheckIn($slug, $status, $duration, $monitorConfig, $checkInId);
    }

    /**
     * {@inheritdoc}
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        return JasnitaSdk::getCurrentHub()->addBreadcrumb($breadcrumb);
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        return JasnitaSdk::getCurrentHub()->getIntegration($className);
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $customSamplingContext Additional context that will be passed to the {@see SamplingContext}
     */
    public function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        return JasnitaSdk::getCurrentHub()->startTransaction($context, $customSamplingContext);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransaction(): ?Transaction
    {
        return JasnitaSdk::getCurrentHub()->getTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function getSpan(): ?Span
    {
        return JasnitaSdk::getCurrentHub()->getSpan();
    }

    /**
     * {@inheritdoc}
     */
    public function setSpan(?Span $span): HubInterface
    {
        return JasnitaSdk::getCurrentHub()->setSpan($span);
    }

    /**
     * @see https://www.php.net/manual/en/language.oop5.cloning.php#object.clone
     */
    public function __clone()
    {
        throw new \BadMethodCallException('Cloning is forbidden.');
    }

    /**
     * @see https://www.php.net/manual/en/language.oop5.magic.php#object.wakeup
     */
    public function __wakeup()
    {
        throw new \BadMethodCallException('Unserializing instances of this class is forbidden.');
    }
}
