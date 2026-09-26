<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class SpanContextTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @dataProvider fromTraceparentDataProvider
     *
     * @group legacy
     */
    public function testFromTraceparent(string $header, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedSampled): void
    {
        $this->expectDeprecation('The Jasnita\\Monitor\\Sdk\\Tracing\\SpanContext::fromTraceparent() method is deprecated since version 3.1 and will be removed in 4.0. Use TransactionContext::fromHeaders() instead.');

        $spanContext = SpanContext::fromTraceparent($header);

        if (null !== $expectedSpanId) {
            $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        }

        if (null !== $expectedTraceId) {
            $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        }

        $this->assertSame($expectedSampled, $spanContext->getSampled());
    }

    public static function fromTraceparentDataProvider(): iterable
    {
        yield [
            '0',
            null,
            null,
            false,
        ];

        yield [
            '1',
            null,
            null,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            false,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            null,
        ];
    }
}
