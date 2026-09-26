<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Logs\Logs;
use Jasnita\Monitor\Sdk\Metrics\TraceMetrics;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Transport\Result;
use Jasnita\Monitor\Sdk\Transport\ResultStatus;

final class JasnitaSdkTest extends TestCase
{
    public function testInit(): void
    {
        $hub1 = JasnitaSdk::init();
        $hub2 = JasnitaSdk::getCurrentHub();

        $this->assertSame($hub1, $hub2);
        $this->assertNotSame(JasnitaSdk::init(), JasnitaSdk::init());
    }

    public function testGetCurrentHub(): void
    {
        JasnitaSdk::init();

        $hub2 = JasnitaSdk::getCurrentHub();
        $hub3 = JasnitaSdk::getCurrentHub();

        $this->assertSame($hub2, $hub3);
    }

    public function testSetCurrentHub(): void
    {
        $hub = new Hub();

        $this->assertSame($hub, JasnitaSdk::setCurrentHub($hub));
        $this->assertSame($hub, JasnitaSdk::getCurrentHub());
    }

    public function testStartAndEndContextIsolateScopeData(): void
    {
        JasnitaSdk::init();

        JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
            $scope->setTag('baseline', 'yes');
        });

        JasnitaSdk::startContext();

        JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
            $scope->setTag('request', 'yes');
        });

        JasnitaSdk::endContext();

        $event = Event::createEvent();

        JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$event): void {
            $event = $scope->applyToEvent($event);
        });

        $this->assertArrayHasKey('baseline', $event->getTags());
        $this->assertArrayNotHasKey('request', $event->getTags());
    }

    public function testStartContextDoesNotInheritBaselineSpan(): void
    {
        JasnitaSdk::init();

        $baselineSpan = new Span(new SpanContext());
        JasnitaSdk::getCurrentHub()->setSpan($baselineSpan);

        JasnitaSdk::startContext();
        $contextHub = JasnitaSdk::getCurrentHub();

        $this->assertNull($contextHub->getSpan());

        JasnitaSdk::endContext();

        $this->assertSame($baselineSpan, JasnitaSdk::getCurrentHub()->getSpan());
    }

    public function testStartContextUsesProvidedHubAsIs(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();
        $span = new Span(new SpanContext());
        $hub = new Hub();
        $hub->setSpan($span);
        $traceparent = '';
        $hub->configureScope(static function (Scope $scope) use (&$traceparent): void {
            $traceparent = $scope->getPropagationContext()->toTraceparent();
        });

        JasnitaSdk::startContext($hub);

        $this->assertSame($hub, JasnitaSdk::getCurrentHub());
        $this->assertSame($span, JasnitaSdk::getCurrentHub()->getSpan());
        $this->assertSame($traceparent, $this->getCurrentScopeTraceparent());

        JasnitaSdk::endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testStartContextCreatesFreshPropagationContext(): void
    {
        JasnitaSdk::init();

        $globalTraceparent = $this->getCurrentScopeTraceparent();

        JasnitaSdk::startContext();
        $firstContextTraceparent = $this->getCurrentScopeTraceparent();
        JasnitaSdk::endContext();

        JasnitaSdk::startContext();
        $secondContextTraceparent = $this->getCurrentScopeTraceparent();
        JasnitaSdk::endContext();

        $this->assertNotSame($globalTraceparent, $firstContextTraceparent);
        $this->assertNotSame($firstContextTraceparent, $secondContextTraceparent);
    }

    public function testWithContextResetsSpanAndTransactionAcrossInvocations(): void
    {
        JasnitaSdk::init();

        JasnitaSdk::withContext(function (): void {
            $transaction = JasnitaSdk::getCurrentHub()->startTransaction(new \Jasnita\Monitor\Sdk\Tracing\TransactionContext('request-1'));
            JasnitaSdk::getCurrentHub()->setSpan($transaction);

            $this->assertSame($transaction, JasnitaSdk::getCurrentHub()->getSpan());
            $this->assertSame($transaction, JasnitaSdk::getCurrentHub()->getTransaction());
        });

        JasnitaSdk::withContext(function (): void {
            $this->assertNull(JasnitaSdk::getCurrentHub()->getSpan());
            $this->assertNull(JasnitaSdk::getCurrentHub()->getTransaction());
        });
    }

    public function testNestedStartContextIsNoOp(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();

        JasnitaSdk::startContext();
        $firstContextHub = JasnitaSdk::getCurrentHub();

        JasnitaSdk::startContext();
        $secondContextHub = JasnitaSdk::getCurrentHub();

        $this->assertNotSame($globalHub, $firstContextHub);
        $this->assertSame($firstContextHub, $secondContextHub);

        JasnitaSdk::endContext();
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());

        JasnitaSdk::endContext();
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testNestedStartContextIgnoresProvidedHub(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();

        JasnitaSdk::startContext();
        $contextHub = JasnitaSdk::getCurrentHub();

        JasnitaSdk::startContext(new Hub());

        $this->assertSame($contextHub, JasnitaSdk::getCurrentHub());

        JasnitaSdk::endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testRuntimeContextStorageIsolatesConcurrentExecutions(): void
    {
        $storage = new StubRuntimeContextStorage();
        JasnitaSdk::setRuntimeContextStorage($storage);
        $globalHub = JasnitaSdk::init();

        $storage->switchTo('first');
        JasnitaSdk::startContext();

        $firstContext = JasnitaSdk::getCurrentRuntimeContext();
        $firstLogsAggregator = $firstContext->getLogsAggregator();
        $firstMetricsAggregator = $firstContext->getMetricsAggregator();
        $firstHub = new Hub();

        JasnitaSdk::setCurrentHub($firstHub);

        $this->assertSame($firstHub, $firstContext->getHub());

        $firstHub->configureScope(static function (Scope $scope): void {
            $scope->setTag('execution', 'first');
        });

        $storage->switchTo('second');
        JasnitaSdk::startContext();

        $secondContext = JasnitaSdk::getCurrentRuntimeContext();
        $secondHub = $secondContext->getHub();

        $secondHub->configureScope(static function (Scope $scope): void {
            $scope->setTag('execution', 'second');
        });

        $this->assertNotSame($firstContext, $secondContext);
        $this->assertNotSame($firstHub, $secondHub);
        $this->assertNotSame($firstLogsAggregator, $secondContext->getLogsAggregator());
        $this->assertNotSame($firstMetricsAggregator, $secondContext->getMetricsAggregator());

        $storage->switchTo('first');

        $this->assertSame($firstContext, JasnitaSdk::getCurrentRuntimeContext());
        $this->assertSame('first', $this->getCurrentScopeTag('execution'));

        $storage->switchTo('second');

        $this->assertSame($secondContext, JasnitaSdk::getCurrentRuntimeContext());
        $this->assertSame('second', $this->getCurrentScopeTag('execution'));

        JasnitaSdk::endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());

        $storage->switchTo('first');

        $this->assertSame($firstContext, JasnitaSdk::getCurrentRuntimeContext());

        JasnitaSdk::endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testRuntimeContextStorageCanReleaseAbandonedExecutions(): void
    {
        $storage = new StubRuntimeContextStorage();
        JasnitaSdk::setRuntimeContextStorage($storage);
        $globalHub = JasnitaSdk::init();

        $storage->switchTo('abandoned');
        JasnitaSdk::startContext();

        $abandonedContext = JasnitaSdk::getCurrentRuntimeContext();

        $storage->release('abandoned');

        $this->assertNotSame($abandonedContext, JasnitaSdk::getCurrentRuntimeContext());
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testRepeatedEndContextWithRuntimeContextStorageIsNoOp(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->once())
            ->method('flush')
            ->willReturn(new Result(ResultStatus::success()));

        $storage = new StubRuntimeContextStorage();
        JasnitaSdk::setRuntimeContextStorage($storage);
        $globalHub = JasnitaSdk::init();
        $globalHub->bindClient($client);

        $storage->switchTo('request');
        JasnitaSdk::startContext();
        JasnitaSdk::endContext();

        $this->assertNull($storage->get());

        JasnitaSdk::endContext();

        $this->assertNull($storage->get());
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testInitClearsContextStoredByPreviousManager(): void
    {
        /** @var ClientInterface&MockObject $firstClient */
        $firstClient = $this->createMock(ClientInterface::class);
        $firstClient->expects($this->never())
            ->method('flush');

        /** @var ClientInterface&MockObject $secondClient */
        $secondClient = $this->createMock(ClientInterface::class);
        $secondClient->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());
        $secondClient->expects($this->once())
            ->method('flush')
            ->willReturn(new Result(ResultStatus::success()));

        $storage = new StubRuntimeContextStorage();
        JasnitaSdk::setRuntimeContextStorage($storage);
        JasnitaSdk::init()->bindClient($firstClient);

        $storage->switchTo('request');
        JasnitaSdk::startContext();
        $previousHub = JasnitaSdk::getCurrentHub();

        $freshHub = JasnitaSdk::init();

        $this->assertNull($storage->get());
        $this->assertNotSame($previousHub, $freshHub);

        $freshHub->bindClient($secondClient);

        JasnitaSdk::endContext();

        $this->assertNull($storage->get());

        JasnitaSdk::startContext();

        $this->assertNotNull($storage->get());
        $this->assertSame($secondClient, JasnitaSdk::getCurrentHub()->getClient());

        JasnitaSdk::endContext();
    }

    public function testReplacingRuntimeContextStorageDiscardsContextFromPreviousStorage(): void
    {
        $firstStorage = new StubRuntimeContextStorage();
        $secondStorage = new StubRuntimeContextStorage();

        JasnitaSdk::setRuntimeContextStorage($firstStorage);
        $globalHub = JasnitaSdk::init();
        JasnitaSdk::startContext();

        $this->assertNotNull($firstStorage->get());

        JasnitaSdk::setRuntimeContextStorage($secondStorage);

        $this->assertNull($firstStorage->get());
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());

        JasnitaSdk::startContext();

        $this->assertNull($firstStorage->get());
        $this->assertSame(JasnitaSdk::getCurrentRuntimeContext(), $secondStorage->get());

        JasnitaSdk::endContext();
    }

    public function testUnregisteringRuntimeContextStorageRestoresProcessLocalContext(): void
    {
        $storage = new StubRuntimeContextStorage();

        JasnitaSdk::setRuntimeContextStorage($storage);
        $globalHub = JasnitaSdk::init();
        JasnitaSdk::startContext();

        $this->assertNotNull($storage->get());

        JasnitaSdk::setRuntimeContextStorage(null);

        $this->assertNull($storage->get());
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());

        JasnitaSdk::startContext();

        $this->assertNull($storage->get());
        $this->assertNotSame($globalHub, JasnitaSdk::getCurrentHub());

        JasnitaSdk::endContext();

        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testEndContextFlushesClientTransportWithOptionalTimeout(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->once())
            ->method('flush')
            ->with(12)
            ->willReturn(new Result(ResultStatus::success()));

        JasnitaSdk::init()->bindClient($client);

        JasnitaSdk::startContext();
        JasnitaSdk::endContext(12);
    }

    public function testFlushFlushesClientTransport(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('flush')
            ->with(null)
            ->willReturn(new Result(ResultStatus::success()));

        JasnitaSdk::init()->bindClient($client);

        JasnitaSdk::flush();
    }

    public function testEndContextFlushesResourcesIndependently(): void
    {
        StubLogger::$logs = [];

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options(['logger' => StubLogger::getInstance()]));
        $client->expects($this->exactly(2))
            ->method('captureEvent')
            ->willReturnCallback(static function (Event $event): void {
                throw new \RuntimeException('Failed capturing ' . (string) $event->getType());
            });
        $client->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('Failed flushing transport'));

        JasnitaSdk::init()->bindClient($client);
        JasnitaSdk::startContext();

        Logs::getInstance()->info('log');
        TraceMetrics::getInstance()->count('metric', 1);

        JasnitaSdk::endContext();

        $errors = array_filter(StubLogger::$logs, static function (array $log): bool {
            return $log['level'] === 'error';
        });

        $this->assertSame([
            'Failed to flush logs while ending a runtime context.',
            'Failed to flush trace metrics while ending a runtime context.',
            'Failed to flush the client transport while ending a runtime context.',
        ], array_column($errors, 'message'));
    }

    public function testWithContextReturnsCallbackResultAndRestoresGlobalHub(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();
        $callbackHub = null;

        $result = JasnitaSdk::withContext(static function () use (&$callbackHub): string {
            $callbackHub = JasnitaSdk::getCurrentHub();

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertNotNull($callbackHub);
        $this->assertNotSame($globalHub, $callbackHub);
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testNestedWithContextReusesOuterContext(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();
        $outerHub = null;
        $innerHub = null;
        $outerContextId = null;
        $innerContextId = null;

        JasnitaSdk::withContext(function () use (&$outerHub, &$innerHub, &$outerContextId, &$innerContextId, $globalHub): void {
            $outerHub = JasnitaSdk::getCurrentHub();
            $outerContextId = JasnitaSdk::getCurrentRuntimeContext()->getId();

            JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
                $scope->setTag('outer', 'yes');
            });

            JasnitaSdk::withContext(static function () use (&$innerHub, &$innerContextId): void {
                $innerHub = JasnitaSdk::getCurrentHub();
                $innerContextId = JasnitaSdk::getCurrentRuntimeContext()->getId();
            });

            $event = Event::createEvent();

            JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$event): void {
                $event = $scope->applyToEvent($event);
            });

            $this->assertNotSame($globalHub, JasnitaSdk::getCurrentHub());
            $this->assertSame('yes', $event->getTags()['outer'] ?? null);
            $this->assertSame($outerContextId, JasnitaSdk::getCurrentRuntimeContext()->getId());
        });

        $this->assertNotNull($outerHub);
        $this->assertNotNull($innerHub);
        $this->assertNotNull($outerContextId);
        $this->assertNotNull($innerContextId);
        $this->assertSame($outerHub, $innerHub);
        $this->assertSame($outerContextId, $innerContextId);
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    public function testWithContextEndsContextWhenCallbackThrows(): void
    {
        JasnitaSdk::init();

        $globalHub = JasnitaSdk::getCurrentHub();
        $callbackHub = null;

        try {
            JasnitaSdk::withContext(static function () use (&$callbackHub): void {
                $callbackHub = JasnitaSdk::getCurrentHub();

                throw new \RuntimeException('boom');
            });

            $this->fail('The callback exception should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertNotNull($callbackHub);
        $this->assertNotSame($globalHub, $callbackHub);
        $this->assertSame($globalHub, JasnitaSdk::getCurrentHub());
    }

    private function getCurrentScopeTraceparent(): string
    {
        $traceparent = '';

        JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$traceparent): void {
            $traceparent = $scope->getPropagationContext()->toTraceparent();
        });

        return $traceparent;
    }

    private function getCurrentScopeTag(string $key): ?string
    {
        $value = null;

        JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope) use ($key, &$value): void {
            $event = $scope->applyToEvent(Event::createEvent());
            $value = $event !== null ? $event->getTags()[$key] ?? null : null;
        });

        return $value;
    }
}
