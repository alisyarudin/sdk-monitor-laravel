<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanRecorder;

final class SpanRecorderTest extends TestCase
{
    public function testAdd(): void
    {
        $span1 = new class() extends Span {
            public function __construct()
            {
                parent::__construct();

                $this->spanRecorder = new SpanRecorder(1);
                $this->spanRecorder->add($this);
            }

            public function getSpanRecorder(): SpanRecorder
            {
                return $this->spanRecorder;
            }
        };

        $span2 = $span1->startChild(new SpanContext());
        $span3 = $span2->startChild(new SpanContext()); // this should not end up being recorded

        $this->assertSame([$span1, $span2], $span1->getSpanRecorder()->getSpans());
    }
}
