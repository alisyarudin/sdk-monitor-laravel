<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Mockery;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\Span;

class IntegrationMetaTagTest extends TestCase
{
    private const DANGEROUS_PAYLOAD = '</meta><script>alert("owned")</script>';

    protected function tearDown(): void
    {
        \Jasnita\Monitor\Sdk\configureScope(static function (Scope $scope): void {
            $scope->setSpan(null);
        });

        parent::tearDown();
    }

    public function testJasnitaTracingMetaEscapesDangerousTraceparentContent(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.traces_sample_rate' => 1.0,
        ]);

        $dangerousTraceparent = self::DANGEROUS_PAYLOAD;

        $this->setDangerousSpanValues($dangerousTraceparent, 'safe-baggage');

        $metaTag = Integration::jasnitaTracingMeta();
        $expected = sprintf(
            '<meta name="jasnita-trace" content="%s"/>',
            htmlspecialchars($dangerousTraceparent, ENT_QUOTES, 'UTF-8')
        );

        $this->assertSame($expected, $metaTag);
        $this->assertStringContainsString('&lt;script&gt;', $metaTag);
        $this->assertStringNotContainsString('<script>', $metaTag);
    }

    public function testJasnitaBaggageMetaEscapesDangerousBaggageContent(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.traces_sample_rate' => 1.0,
        ]);

        $dangerousBaggage = self::DANGEROUS_PAYLOAD;

        $this->setDangerousSpanValues('safe-traceparent', $dangerousBaggage);

        $metaTag = Integration::jasnitaBaggageMeta();
        $expected = sprintf(
            '<meta name="baggage" content="%s"/>',
            htmlspecialchars($dangerousBaggage, ENT_QUOTES, 'UTF-8')
        );

        $this->assertSame($expected, $metaTag);
        $this->assertStringContainsString('&lt;script&gt;', $metaTag);
        $this->assertStringNotContainsString('<script>', $metaTag);
    }

    public function testJasnitaTracingMetaReturnsAWellFormedMetaTag(): void
    {
        $meta = Integration::jasnitaTracingMeta();
        
        $this->assertStringStartsWith('<meta name="jasnita-trace" content="', $meta);
        $this->assertStringEndsWith('"/>', $meta);
    }

    public function testJasnitaBaggageMetaReturnsAWellFormedMetaTag(): void
    {
        $meta = Integration::jasnitaBaggageMeta();

        $this->assertStringStartsWith('<meta name="baggage" content="', $meta);
        $this->assertStringEndsWith('"/>', $meta);
    }

    private function setDangerousSpanValues(string $traceparent, string $baggage): void
    {
        $span = Mockery::mock(Span::class);
        $span->shouldReceive('toTraceparent')->andReturn($traceparent)->zeroOrMoreTimes();
        $span->shouldReceive('toBaggage')->andReturn($baggage)->zeroOrMoreTimes();

        \Jasnita\Monitor\Sdk\configureScope(static function (Scope $scope) use ($span): void {
            $scope->setSpan($span);
        });
    }
}
