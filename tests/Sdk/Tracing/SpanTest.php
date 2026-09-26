<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Tests\TestUtil\ClockMock;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Jasnita\Monitor\Sdk\Tracing\Transaction;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;

/**
 * @group time-sensitive
 */
final class SpanTest extends TestCase
{
    /**
     * @dataProvider finishDataProvider
     */
    public function testFinish(?float $currentTimestamp, ?float $endTimestamp, float $expectedEndTimestamp): void
    {
        ClockMock::withClockMock($currentTimestamp);

        $span = new Span();
        $span->finish($endTimestamp);

        $this->assertSame($expectedEndTimestamp, $span->getEndTimestamp());
    }

    public static function finishDataProvider(): iterable
    {
        yield [
            1598660006,
            null,
            1598660006,
        ];

        yield [
            1598660006,
            1598660332,
            1598660332,
        ];
    }

    public function testStartChild(): void
    {
        $spanContext2ParentSpanId = SpanId::generate();
        $spanContext2TraceId = TraceId::generate();

        $spanContext1 = (new SpanContext())
            ->setSampled(false)
            ->setSpanId(SpanId::generate())
            ->setTraceId(TraceId::generate());

        $spanContext2 = SpanContext::make()
            ->setSampled(true)
            ->setParentSpanId($spanContext2ParentSpanId)
            ->setTraceId($spanContext2TraceId);

        $span1 = new Span($spanContext1);
        $span2 = $span1->startChild($spanContext2);

        $this->assertSame($spanContext1->getSampled(), $span1->getSampled());
        $this->assertSame($spanContext1->getSpanId(), $span1->getSpanId());
        $this->assertSame($spanContext1->getTraceId(), $span1->getTraceId());

        $this->assertSame($spanContext1->getSampled(), $span2->getSampled());
        $this->assertSame($spanContext1->getSpanId(), $span2->getParentSpanId());
        $this->assertSame($spanContext1->getTraceId(), $span2->getTraceId());
    }

    /**
     * @dataProvider toTraceparentDataProvider
     */
    public function testToTraceparent(?bool $sampled, string $expectedValue): void
    {
        $span = new Span();
        $span->setSpanId(new SpanId('566e3688a61d4bc8'));
        $span->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $span->setSampled($sampled);

        $this->assertSame($expectedValue, $span->toTraceparent());
    }

    public static function toTraceparentDataProvider(): iterable
    {
        yield [
            null,
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
        ];

        yield [
            false,
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
        ];

        yield [
            true,
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
        ];
    }

    /**
     * @dataProvider toBaggageDataProvider
     */
    public function testToBaggage(string $baggageHeader, string $expectedValue): void
    {
        $context = TransactionContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            $baggageHeader
        );
        $transaction = new Transaction($context);

        $this->assertSame($expectedValue, $transaction->toBaggage());
    }

    public static function toBaggageDataProvider(): iterable
    {
        yield [
            '',
            '',
        ];

        yield [
            'foo=bar,bar=baz',
            '',
        ];

        yield [
            'jasnita-public_key=public,jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1',
            'jasnita-public_key=public,jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1',
        ];

        yield [
            'jasnita-public_key=public,jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1,foo=bar,bar=baz',
            'jasnita-public_key=public,jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1',
        ];
    }

    public function testDataGetter(): void
    {
        $span = new Span();

        $initialData = [
            'foo' => 'bar',
            'baz' => 1,
        ];

        $span->setData($initialData);

        $this->assertSame($initialData, $span->getData());
        $this->assertSame('bar', $span->getData('foo'));
        $this->assertSame(1, $span->getData('baz'));
    }

    public function testDataIsMergedWhenSet(): void
    {
        $span = new Span();

        $span->setData([
            'foo' => 'bar',
            'baz' => 1,
        ]);

        $span->setData([
            'baz' => 2,
        ]);

        $this->assertSame(2, $span->getData('baz'));
        $this->assertSame('bar', $span->getData('foo'));
        $this->assertSame([
            'foo' => 'bar',
            'baz' => 2,
        ], $span->getData());
    }

    public function testOriginIsCopiedFromContext(): void
    {
        $context = SpanContext::make()->setOrigin('auto.testing');

        $span = new Span($context);

        $this->assertSame($context->getOrigin(), $span->getOrigin());
        $this->assertSame($context->getOrigin(), $span->getTraceContext()['origin']);
    }

    public function testFlagIsRecorded(): void
    {
        $span = new Span();

        $span->setFlag('feature', true);

        $this->assertSame(['flag.evaluation.feature' => true], $span->getData());
    }

    public function testFlagLimitRecorded(): void
    {
        $span = new Span();

        $expectedFlags = [
            'flag.evaluation.should-not-be-discarded' => true,
        ];

        $span->setFlag('should-not-be-discarded', true);

        foreach (range(1, Span::MAX_FLAGS - 1) as $i) {
            $span->setFlag("feature{$i}", true);

            $expectedFlags["flag.evaluation.feature{$i}"] = true;
        }

        $span->setFlag('should-be-discarded', true);

        $this->assertSame($expectedFlags, $span->getData());
    }
}
