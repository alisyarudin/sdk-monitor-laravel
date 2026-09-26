<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\Tracing\DynamicSamplingContext;
use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionMetadata;
use Jasnita\Monitor\Sdk\Tracing\TransactionSource;

final class TransactionContextTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $transactionContext = new TransactionContext();
        $transactionMetadata = new TransactionMetadata();
        $transactionSource = TransactionSource::custom();

        $this->assertSame('<unlabeled transaction>', $transactionContext->getName());
        $this->assertNull($transactionContext->getParentSampled());

        $transactionContext->setName('foo');
        $transactionContext->setParentSampled(true);
        $transactionContext->setMetadata($transactionMetadata);
        $transactionContext->setSource($transactionSource);

        $this->assertSame('foo', $transactionContext->getName());
        $this->assertTrue($transactionContext->getParentSampled());
        $this->assertSame($transactionMetadata, $transactionContext->getMetadata());
        $this->assertSame($transactionSource, $transactionContext->getMetadata()->getSource());
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromHeaders(string $jasnitaTraceHeader, string $baggageHeader, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedParentSampled, ?string $expectedDynamicSamplingContextClass, ?bool $expectedDynamicSamplingContextFrozen): void
    {
        $spanContext = TransactionContext::fromHeaders($jasnitaTraceHeader, $baggageHeader);

        $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        $this->assertSame($expectedParentSampled, $spanContext->getParentSampled());
        $this->assertInstanceOf($expectedDynamicSamplingContextClass, $spanContext->getMetadata()->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $spanContext->getMetadata()->getDynamicSamplingContext()->isFrozen());
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromEnvironment(string $jasnitaTrace, string $baggage, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedParentSampled, ?string $expectedDynamicSamplingContextClass, ?bool $expectedDynamicSamplingContextFrozen): void
    {
        $spanContext = TransactionContext::fromEnvironment($jasnitaTrace, $baggage);

        $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        $this->assertSame($expectedParentSampled, $spanContext->getParentSampled());
        $this->assertInstanceOf($expectedDynamicSamplingContextClass, $spanContext->getMetadata()->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $spanContext->getMetadata()->getDynamicSamplingContext()->isFrozen());
    }

    public static function tracingDataProvider(): iterable
    {
        yield [
            '0',
            '',
            null,
            null,
            false,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '1',
            '',
            null,
            null,
            true,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            false,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            null,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'jasnita-public_key=public,jasnita-trace_id=566e3688a61d4bc888951642d6f14a19,jasnita-sample_rate=1',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'foo=bar',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
            DynamicSamplingContext::class,
            true,
        ];
    }

    /**
     * @dataProvider invalidSampleRandDataProvider
     */
    public function testInvalidSampleRandIsIgnored(string $sampleRand): void
    {
        $context = TransactionContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'jasnita-sample_rate=0.4,jasnita-sample_rand=' . rawurlencode($sampleRand)
        );

        $generatedSampleRand = $context->getMetadata()->getSampleRand();

        $this->assertNotNull($generatedSampleRand);
        $this->assertGreaterThanOrEqual(0.0, $generatedSampleRand);
        $this->assertLessThan(0.4, $generatedSampleRand);
    }

    public function testSampleRandIsIgnoredWithoutJasnitaTraceHeader(): void
    {
        $context = TransactionContext::fromHeaders('', 'jasnita-sample_rand=-1.0');
        $sampleRand = $context->getMetadata()->getSampleRand();

        $this->assertNotNull($sampleRand);
        $this->assertGreaterThanOrEqual(0.0, $sampleRand);
        $this->assertLessThanOrEqual(1.0, $sampleRand);
    }

    public function testInvalidSampleRandIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains('Ignoring invalid jasnita-sample_rand baggage value'),
                ['sample_rand' => '-1.0']
            );

        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options(['logger' => $logger]));

        JasnitaSdk::setCurrentHub(new Hub($client));

        try {
            TransactionContext::fromHeaders(
                '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
                'jasnita-sample_rand=-1.0'
            );
        } finally {
            JasnitaSdk::setCurrentHub(new Hub());
        }
    }

    public static function invalidSampleRandDataProvider(): iterable
    {
        yield ['-1.0'];
        yield ['1'];
        yield ['2.0'];
        yield ['foo'];
    }

    public function testSampleRandRangeWhenParentNotSampledAndSampleRateProvided(): void
    {
        $context = TransactionContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            'jasnita-sample_rate=0.4'
        );

        $sampleRand = $context->getMetadata()->getSampleRand();

        $this->assertNotNull($sampleRand);
        // Should be within [rate, 1) and rounded to 6 decimals
        $this->assertGreaterThanOrEqual(0.4, $sampleRand);
        $this->assertLessThanOrEqual(0.999999, $sampleRand);
    }

    public function testParentSamplingRateIsIgnoredWithoutJasnitaTraceHeader(): void
    {
        $context = TransactionContext::fromHeaders('', 'jasnita-sample_rate=1');

        $this->assertNull($context->getMetadata()->getParentSamplingRate());
    }
}
