<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Sdk\CheckInStatus;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\EventId;
use Jasnita\Monitor\Sdk\MonitorConfig;
use Jasnita\Monitor\Sdk\MonitorSchedule;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Severity;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\PropagationContext;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Jasnita\Monitor\Sdk\Tracing\Transaction;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;
use Jasnita\Monitor\Sdk\Util\JasnitaUid;
use function Jasnita\Monitor\Sdk\addBreadcrumb;
use function Jasnita\Monitor\Sdk\captureCheckIn;
use function Jasnita\Monitor\Sdk\captureEvent;
use function Jasnita\Monitor\Sdk\captureException;
use function Jasnita\Monitor\Sdk\captureLastError;
use function Jasnita\Monitor\Sdk\captureMessage;
use function Jasnita\Monitor\Sdk\configureScope;
use function Jasnita\Monitor\Sdk\continueTrace;
use function Jasnita\Monitor\Sdk\getBaggage;
use function Jasnita\Monitor\Sdk\getTraceparent;
use function Jasnita\Monitor\Sdk\init;
use function Jasnita\Monitor\Sdk\startTransaction;
use function Jasnita\Monitor\Sdk\trace;
use function Jasnita\Monitor\Sdk\withScope;

final class FunctionsTest extends TestCase
{
    public function testInit(): void
    {
        init(['default_integrations' => false]);

        $this->assertNotNull(JasnitaSdk::getCurrentHub()->getClient());
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($eventId, captureMessage(...$functionCallArgs));
    }

    public static function captureMessageDataProvider(): \Generator
    {
        yield [
            [
                'foo',
                Severity::debug(),
            ],
            [
                'foo',
                Severity::debug(),
                null,
            ],
        ];

        yield [
            [
                'foo',
                Severity::debug(),
                new EventHint(),
            ],
            [
                'foo',
                Severity::debug(),
                new EventHint(),
            ],
        ];
    }

    /**
     * @dataProvider captureExceptionDataProvider
     */
    public function testCaptureException(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureException')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($eventId, captureException(...$functionCallArgs));
    }

    public static function captureExceptionDataProvider(): \Generator
    {
        yield [
            [
                new \Exception('foo'),
            ],
            [
                new \Exception('foo'),
                null,
            ],
        ];

        yield [
            [
                new \Exception('foo'),
                new EventHint(),
            ],
            [
                new \Exception('foo'),
                new EventHint(),
            ],
        ];
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent();
        $hint = new EventHint();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint)
            ->willReturn($event->getId());

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), captureEvent($event, $hint));
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureLastError')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        JasnitaSdk::setCurrentHub($hub);

        @trigger_error('foo', \E_USER_NOTICE);

        $this->assertSame($eventId, captureLastError(...$functionCallArgs));
    }

    public static function captureLastErrorDataProvider(): \Generator
    {
        yield [
            [],
            [null],
        ];

        yield [
            [new EventHint()],
            [new EventHint()],
        ];
    }

    public function testCaptureCheckIn(): void
    {
        $checkInId = JasnitaUid::generate();
        $monitorConfig = new MonitorConfig(
            MonitorSchedule::crontab('*/5 * * * *'),
            5,
            30,
            'UTC'
        );

        $hub = $this->createMock(StubHubInterface::class);
        $hub->expects($this->once())
            ->method('captureCheckIn')
            ->with('test-crontab', CheckInStatus::ok(), 10, $monitorConfig, $checkInId)
            ->willReturn($checkInId);

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($checkInId, captureCheckIn(
            'test-crontab',
            CheckInStatus::ok(),
            10,
            $monitorConfig,
            $checkInId
        ));
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['default_integrations' => false]));

        JasnitaSdk::getCurrentHub()->bindClient($client);

        addBreadcrumb($breadcrumb);
        configureScope(function (Scope $scope) use ($breadcrumb): void {
            $event = $scope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        });
    }

    public function testWithScope(): void
    {
        $returnValue = withScope(static function (): string {
            return 'foobarbaz';
        });

        $this->assertSame('foobarbaz', $returnValue);
    }

    public function testConfigureScope(): void
    {
        $callbackInvoked = false;

        configureScope(static function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testStartTransaction(): void
    {
        $transactionContext = new TransactionContext('foo');
        $transaction = new Transaction($transactionContext);
        $customSamplingContext = ['foo' => 'bar'];

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('startTransaction')
            ->with($transactionContext, $customSamplingContext)
            ->willReturn($transaction);

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($transaction, startTransaction($transactionContext, $customSamplingContext));
    }

    public function testTraceReturnsClosureResult(): void
    {
        $returnValue = 'foo';

        $result = trace(function () use ($returnValue) {
            return $returnValue;
        }, new SpanContext());

        $this->assertSame($returnValue, $result);
    }

    public function testTraceCorrectlyReplacesAndRestoresCurrentSpan(): void
    {
        $hub = new Hub();

        $transaction = new Transaction(new TransactionContext());

        $hub->setSpan($transaction);

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($transaction, $hub->getSpan());

        trace(function () use ($transaction, $hub) {
            $this->assertNotSame($transaction, $hub->getSpan());
        }, new SpanContext());

        $this->assertSame($transaction, $hub->getSpan());

        try {
            trace(function () {
                throw new RuntimeException('Throwing should still restore the previous span');
            }, new SpanContext());
        } catch (RuntimeException $e) {
            $this->assertSame($transaction, $hub->getSpan());
        }
    }

    public function testTraceparentWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $scope = new Scope($propagationContext);

        $hub = new Hub(null, $scope);

        JasnitaSdk::setCurrentHub($hub);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testTraceparentWithTracingEnabled(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1.0,
            ]));

        $hub = new Hub($client);

        JasnitaSdk::setCurrentHub($hub);

        $spanContext = new SpanContext();
        $spanContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $spanContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $span = new Span($spanContext);

        $hub->setSpan($span);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testBaggageWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));

        $scope = new Scope($propagationContext);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'release' => '1.0.0',
                'environment' => 'development',
            ]));

        $hub = new Hub($client, $scope);

        JasnitaSdk::setCurrentHub($hub);

        $baggage = getBaggage();

        $this->assertSame('jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-release=1.0.0,jasnita-environment=development', $baggage);
    }

    public function testBaggageWithTracingEnabled(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1.0,
                'release' => '1.0.0',
                'environment' => 'development',
            ]));

        $hub = new Hub($client);

        JasnitaSdk::setCurrentHub($hub);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('Test');
        $transactionContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));

        $transaction = startTransaction($transactionContext);

        $spanContext = new SpanContext();

        $span = $transaction->startChild($spanContext);

        $hub->setSpan($span);

        $baggage = getBaggage();

        $this->assertSame('jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1,jasnita-transaction=Test,jasnita-release=1.0.0,jasnita-environment=development,jasnita-sampled=true', $baggage);
    }

    public function testContinueTrace(): void
    {
        $hub = new Hub();

        JasnitaSdk::setCurrentHub($hub);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'jasnita-trace_id=566e3688a61d4bc888951642d6f14a19'
        );

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $transactionContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $transactionContext->getParentSpanId());
        $this->assertTrue($transactionContext->getParentSampled());

        configureScope(function (Scope $scope): void {
            $propagationContext = $scope->getPropagationContext();

            $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $propagationContext->getTraceId());
            $this->assertSame('566e3688a61d4bc8', (string) $propagationContext->getParentSpanId());

            $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();

            $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $dynamicSamplingContext->get('trace_id'));
            $this->assertTrue($dynamicSamplingContext->isFrozen());
        });
    }
}

interface StubHubInterface extends HubInterface
{
    public function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string;
}
