<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel;

use Jasnita\Monitor\Sdk\State\HubInterface;

/**
 * @see \Jasnita\Monitor\Sdk\State\HubInterface
 *
 * @method static \Jasnita\Monitor\Sdk\ClientInterface|null getClient()
 * @method static \Jasnita\Monitor\Sdk\EventId|null getLastEventId()
 * @method static \Jasnita\Monitor\Sdk\State\Scope pushScope()
 * @method static bool popScope()
 * @method static void withScope(callable $callback)
 * @method static void configureScope(callable $callback)
 * @method static void bindClient(\Jasnita\Monitor\Sdk\ClientInterface $client)
 * @method static \Jasnita\Monitor\Sdk\EventId|null captureMessage(string $message, \Jasnita\Monitor\Sdk\Severity|null $level = null, \Jasnita\Monitor\Sdk\EventHint|null $hint = null)
 * @method static \Jasnita\Monitor\Sdk\EventId|null captureException(\Throwable $exception, \Jasnita\Monitor\Sdk\EventHint|null $hint = null)
 * @method static \Jasnita\Monitor\Sdk\EventId|null captureEvent(\Throwable $exception, \Jasnita\Monitor\Sdk\EventHint|null $hint = null)
 * @method static \Jasnita\Monitor\Sdk\EventId|null captureLastError(\Jasnita\Monitor\Sdk\EventHint|null $hint = null)
 * @method static bool addBreadcrumb(\Jasnita\Monitor\Sdk\Breadcrumb $breadcrumb)
 * @method static \Jasnita\Monitor\Sdk\Integration\IntegrationInterface|null getIntegration(string $className)
 * @method static \Jasnita\Monitor\Sdk\Tracing\Transaction startTransaction(\Jasnita\Monitor\Sdk\Tracing\TransactionContext $context, array $customSamplingContext = [])
 * @method static \Jasnita\Monitor\Sdk\Tracing\Transaction|null getTransaction()
 * @method static \Jasnita\Monitor\Sdk\Tracing\Span|null getSpan()
 * @method static \Jasnita\Monitor\Sdk\State\HubInterface setSpan(\Jasnita\Monitor\Sdk\Tracing\Span|null $span)
 */
class Facade extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return HubInterface::class;
    }
}
