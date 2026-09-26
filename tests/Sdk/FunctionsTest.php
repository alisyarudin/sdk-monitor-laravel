<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Attachment\Attachment;
use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Sdk\CheckInStatus;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\EventId;
use Jasnita\Monitor\Sdk\Integration\OTLPIntegration;
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
use Jasnita\Monitor\Sdk\Transport\Result;
use Jasnita\Monitor\Sdk\Transport\ResultStatus;
use Jasnita\Monitor\Sdk\Util\JasnitaUid;

use function Jasnita\Monitor\Sdk\addAttachment;
use function Jasnita\Monitor\Sdk\addBreadcrumb;
use function Jasnita\Monitor\Sdk\captureCheckIn;
use function Jasnita\Monitor\Sdk\captureEvent;
use function Jasnita\Monitor\Sdk\captureException;
use function Jasnita\Monitor\Sdk\captureLastError;
use function Jasnita\Monitor\Sdk\captureMessage;
use function Jasnita\Monitor\Sdk\configureScope;
use function Jasnita\Monitor\Sdk\continueTrace;
use function Jasnita\Monitor\Sdk\endContext;
use function Jasnita\Monitor\Sdk\getBaggage;
use function Jasnita\Monitor\Sdk\getOtlpTracesEndpointUrl;
use function Jasnita\Monitor\Sdk\getTraceparent;
use function Jasnita\Monitor\Sdk\init;
use function Jasnita\Monitor\Sdk\startContext;
use function Jasnita\Monitor\Sdk\startTransaction;
use function Jasnita\Monitor\Sdk\trace;
use function Jasnita\Monitor\Sdk\withContext;
use function Jasnita\Monitor\Sdk\withMonitor;
use function Jasnita\Monitor\Sdk\withScope;

final class FunctionsTest extends TestCase
{
    public function testInit(): void
    {
        init(['default_integrations' => false]);

        $this->assertNotNull(JasnitaSdk::getCurrentHub()->getClient());
    }

    public function testInitUsesRuntimeContextStorage(): void
    {
        $storage = new StubRuntimeContextStorage();

        JasnitaSdk::setRuntimeContextStorage($storage);
        init(['default_integrations' => false]);

        $storage->switchTo('request');
        startContext();

        $this->assertNotNull($storage->get());

        endContext();

        $this->assertNull($storage->get());
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

        $hub = $this->createMock(HubInterface::class);
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

    public function testWithMonitor(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))
            ->method('captureCheckIn')
            ->with(
                $this->callback(static function (string $slug): bool {
                    return $slug === 'test-crontab';
                }),
                $this->callback(static function (CheckInStatus $checkInStatus): bool {
                    // just check for type CheckInStatus
                    return true;
                }),
                $this->anything(),
                $this->callback(static function (MonitorConfig $monitorConfig): bool {
                    return $monitorConfig->getSchedule()->getValue() === '*/5 * * * *'
                        && $monitorConfig->getSchedule()->getType() === MonitorSchedule::TYPE_CRONTAB
                        && $monitorConfig->getCheckinMargin() === 5
                        && $monitorConfig->getMaxRuntime() === 30
                        && $monitorConfig->getTimezone() === 'UTC';
                })
            );

        JasnitaSdk::setCurrentHub($hub);

        withMonitor('test-crontab', static function () {
            // Do something...
        }, new MonitorConfig(
            new MonitorSchedule(MonitorSchedule::TYPE_CRONTAB, '*/5 * * * *'),
            5,
            30,
            'UTC'
        ));
    }

    public function testWithMonitorCallableThrows(): void
    {
        $this->expectException(\Exception::class);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))
            ->method('captureCheckIn')
            ->with(
                $this->callback(static function (string $slug): bool {
                    return $slug === 'test-crontab';
                }),
                $this->callback(static function (CheckInStatus $checkInStatus): bool {
                    // just check for type CheckInStatus
                    return true;
                }),
                $this->anything(),
                $this->callback(static function (MonitorConfig $monitorConfig): bool {
                    return $monitorConfig->getSchedule()->getValue() === '*/5 * * * *'
                        && $monitorConfig->getSchedule()->getType() === MonitorSchedule::TYPE_CRONTAB
                        && $monitorConfig->getCheckinMargin() === 5
                        && $monitorConfig->getMaxRuntime() === 30
                        && $monitorConfig->getTimezone() === 'UTC';
                })
            );

        JasnitaSdk::setCurrentHub($hub);

        withMonitor('test-crontab', static function () {
            throw new \Exception();
        }, new MonitorConfig(
            new MonitorSchedule(MonitorSchedule::TYPE_CRONTAB, '*/5 * * * *'),
            5,
            30,
            'UTC'
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

    public function testAddAttachment(): void
    {
        $attachment = Attachment::fromBytes('test.txt', 'test');
        $scope = new Scope();
        JasnitaSdk::setCurrentHub(new Hub(null, $scope));

        addAttachment($attachment);

        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([$attachment], $event->getAttachments());
        $this->assertSame('void', (string) (new \ReflectionFunction('Jasnita\Monitor\Sdk\addAttachment'))->getReturnType());
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

    public function testStartAndEndContext(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();

        startContext();

        $requestHub = JasnitaSdk::getCurrentHub();

        $this->assertNotSame($globalHub, $requestHub);

        endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testStartContextForwardsProvidedHub(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();
        $hub = new Hub();

        startContext($hub);

        $this->assertSame($hub, JasnitaSdk::getCurrentHub());

        endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testWithContext(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();

        $result = withContext(function () use ($globalHub): string {
            $this->assertNotSame($globalHub, JasnitaSdk::getCurrentHub());

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testNestedWithContextReusesOuterContext(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();
        $outerHub = null;
        $innerHub = null;

        withContext(function () use (&$outerHub, &$innerHub, $globalHub): void {
            $outerHub = JasnitaSdk::getCurrentHub();

            configureScope(static function (Scope $scope): void {
                $scope->setTag('outer', 'yes');
            });

            withContext(static function () use (&$innerHub): void {
                $innerHub = JasnitaSdk::getCurrentHub();
            });

            $event = Event::createEvent();

            configureScope(static function (Scope $scope) use (&$event): void {
                $event = $scope->applyToEvent($event);
            });

            $this->assertNotSame($globalHub, JasnitaSdk::getCurrentHub());
            $this->assertSame('yes', $event->getTags()['outer'] ?? null);
        });

        $this->assertNotNull($outerHub);
        $this->assertNotNull($innerHub);
        $this->assertSame($outerHub, $innerHub);
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testWithContextAlwaysEndsContextWithOptionalTimeout(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->once())
            ->method('flush')
            ->with(13)
            ->willReturn(new Result(ResultStatus::success()));

        JasnitaSdk::init()->bindClient($client);

        try {
            withContext(static function (): void {
                throw new \RuntimeException('callback failed');
            }, 13);

            $this->fail('The callback exception should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('callback failed', $exception->getMessage());
        }
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

        $result = trace(static function () use ($returnValue) {
            return $returnValue;
        }, new SpanContext());

        $this->assertSame($returnValue, $result);
    }

    public function testTraceCorrectlyReplacesAndRestoresCurrentSpan(): void
    {
        $hub = new Hub();

        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(true);

        $hub->setSpan($transaction);

        JasnitaSdk::setCurrentHub($hub);

        $this->assertSame($transaction, $hub->getSpan());

        trace(function () use ($transaction, $hub) {
            $this->assertNotSame($transaction, $hub->getSpan());
        }, new SpanContext());

        $this->assertSame($transaction, $hub->getSpan());

        try {
            trace(static function () {
                throw new \RuntimeException('Throwing should still restore the previous span');
            }, new SpanContext());
        } catch (\RuntimeException $e) {
            $this->assertSame($transaction, $hub->getSpan());
        }
    }

    public function testTraceDoesntCreateSpanIfTransactionIsNotSampled(): void
    {
        $scope = $this->createMock(Scope::class);

        $hub = new Hub(null, $scope);

        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(false);

        $scope->expects($this->never())
            ->method('setSpan');
        $scope->expects($this->exactly(3))
            ->method('getSpan')
            ->willReturn($transaction);

        JasnitaSdk::setCurrentHub($hub);

        trace(function () use ($transaction, $hub) {
            $this->assertSame($transaction, $hub->getSpan());
        }, SpanContext::make());

        $this->assertSame($transaction, $hub->getSpan());
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

        $spanContext = (new SpanContext())
            ->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'))
            ->setSpanId(new SpanId('566e3688a61d4bc8'));

        $span = new Span($spanContext);

        $hub->setSpan($span);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testTraceHeadersAreEmptyWhenExternalPropagationContextIsActive(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        JasnitaSdk::setCurrentHub(new Hub(null, new Scope($propagationContext)));

        $this->assertSame('', getTraceparent());
        $this->assertSame('', getBaggage());

        Scope::clearExternalPropagationContext();
    }

    public function testBaggageWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSampleRand(0.25);

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

        $this->assertSame('jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rand=0.25,jasnita-release=1.0.0,jasnita-environment=development', $baggage);
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
        $transactionContext->getMetadata()->setSampleRand(0.25);

        $transaction = startTransaction($transactionContext);

        $spanContext = new SpanContext();

        $span = $transaction->startChild($spanContext);

        $hub->setSpan($span);

        $baggage = getBaggage();

        $this->assertSame('jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1,jasnita-transaction=Test,jasnita-release=1.0.0,jasnita-environment=development,jasnita-sampled=true,jasnita-sample_rand=0.25', $baggage);
    }

    public function testGetOtlpTracesEndpointUrlFallsBackToDsn(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(OTLPIntegration::class)
            ->willReturn(null);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'https://public@example.com/1',
            ]));

        JasnitaSdk::setCurrentHub(new Hub($client));

        $this->assertSame('https://example.com/api/1/integration/otlp/v1/traces/', getOtlpTracesEndpointUrl());
    }

    public function testGetOtlpTracesEndpointUrlPrefersCollectorUrl(): void
    {
        $integration = new OTLPIntegration(false, 'http://collector:4318/v1/traces');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(OTLPIntegration::class)
            ->willReturn($integration);
        $client->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'https://public@example.com/1',
            ]));

        JasnitaSdk::setCurrentHub(new Hub($client));

        $this->assertSame('http://collector:4318/v1/traces', getOtlpTracesEndpointUrl());
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

    public function testContinueTraceWhenOrgMismatch(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]));

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'jasnita-org_id=2'
        );

        $newTraceId = (string) $transactionContext->getTraceId();
        $newSampleRand = $transactionContext->getMetadata()->getSampleRand();

        $this->assertNotSame('566e3688a61d4bc888951642d6f14a19', $newTraceId);
        $this->assertNotEmpty($newTraceId);
        $this->assertNull($transactionContext->getParentSpanId());
        $this->assertNull($transactionContext->getParentSampled());
        $this->assertNull($transactionContext->getMetadata()->getDynamicSamplingContext());
        $this->assertNotNull($newSampleRand);

        configureScope(function (Scope $scope) use ($newTraceId, $newSampleRand): void {
            $propagationContext = $scope->getPropagationContext();

            $this->assertSame($newTraceId, (string) $propagationContext->getTraceId());
            $this->assertNull($propagationContext->getParentSpanId());
            $this->assertNull($propagationContext->getDynamicSamplingContext());
            $this->assertSame($newSampleRand, $propagationContext->getSampleRand());
        });
    }

    public function testContinueTraceWhenOrgMatch(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]));

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'jasnita-org_id=1'
        );

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $transactionContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $transactionContext->getParentSpanId());
        $this->assertTrue($transactionContext->getParentSampled());

        configureScope(function (Scope $scope): void {
            $propagationContext = $scope->getPropagationContext();

            $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $propagationContext->getTraceId());
            $this->assertSame('566e3688a61d4bc8', (string) $propagationContext->getParentSpanId());

            $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();

            $this->assertNotNull($dynamicSamplingContext);
            $this->assertSame('1', $dynamicSamplingContext->get('org_id'));
        });
    }
}
