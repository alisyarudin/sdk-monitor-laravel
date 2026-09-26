<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\Tracing\PropagationContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;

final class StrictTraceContinuationTest extends TestCase
{
    private const JASNITA_MONITOR_TRACE_HEADER = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';

    protected function setUp(): void
    {
        parent::setUp();

        JasnitaSdk::setCurrentHub(new Hub());
    }

    /**
     * @dataProvider strictTraceContinuationDataProvider
     */
    public function testPropagationContext(Options $options, string $baggage, bool $expectedContinueTrace): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn($options);

        JasnitaSdk::setCurrentHub(new Hub($client));

        $contexts = [
            PropagationContext::fromHeaders(self::JASNITA_MONITOR_TRACE_HEADER, $baggage),
            PropagationContext::fromEnvironment(self::JASNITA_MONITOR_TRACE_HEADER, $baggage),
        ];

        foreach ($contexts as $context) {
            if ($expectedContinueTrace) {
                $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertSame('566e3688a61d4bc8', (string) $context->getParentSpanId());
            } else {
                $this->assertNotSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertNotEmpty((string) $context->getTraceId());
                $this->assertNull($context->getParentSpanId());
                $this->assertNull($context->getDynamicSamplingContext());
            }
        }
    }

    /**
     * @dataProvider strictTraceContinuationDataProvider
     */
    public function testTransactionContext(Options $options, string $baggage, bool $expectedContinueTrace): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn($options);

        JasnitaSdk::setCurrentHub(new Hub($client));

        $contexts = [
            TransactionContext::fromHeaders(self::JASNITA_MONITOR_TRACE_HEADER, $baggage),
            TransactionContext::fromEnvironment(self::JASNITA_MONITOR_TRACE_HEADER, $baggage),
        ];

        foreach ($contexts as $context) {
            if ($expectedContinueTrace) {
                $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertSame('566e3688a61d4bc8', (string) $context->getParentSpanId());
                $this->assertTrue($context->getParentSampled());
            } else {
                $this->assertNotSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertNull($context->getParentSpanId());
                $this->assertNull($context->getParentSampled());
                $this->assertNull($context->getMetadata()->getDynamicSamplingContext());
            }
        }
    }

    public static function strictTraceContinuationDataProvider(): \Generator
    {
        // First 10 Test cases are modelled after: (dokumentasi hulu)
        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 1,
            ]),
            'jasnita-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 1,
            ]),
            '',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
            ]),
            'jasnita-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
            ]),
            '',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 2,
            ]),
            'jasnita-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]),
            'jasnita-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]),
            '',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
            ]),
            'jasnita-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
            ]),
            '',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'org_id' => 2,
            ]),
            'jasnita-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'http://public@o1.example.com/1',
            ]),
            'jasnita-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'http://public@o1.example.com/1',
                'org_id' => 2,
            ]),
            'jasnita-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'http://public@o1.example.com/1',
                'org_id' => 2,
            ]),
            'jasnita-org_id=2',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 1,
            ]),
            'jasnita-org_id=01',
            false,
        ];
    }
}
