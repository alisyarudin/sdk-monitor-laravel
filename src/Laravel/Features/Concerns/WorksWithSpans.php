<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features\Concerns;

use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Tracing\Span;

trait WorksWithSpans
{
    protected function getParentSpanIfSampled(): ?Span
    {
        $parentSpan = JasnitaSdk::getCurrentHub()->getSpan();

        // If the span is not available or not sampled we don't need to do anything
        if ($parentSpan === null || !$parentSpan->getSampled()) {
            return null;
        }

        return $parentSpan;
    }

    /** @param callable(Span $parentSpan): void $callback */
    protected function withParentSpanIfSampled(callable $callback): void
    {
        $parentSpan = $this->getParentSpanIfSampled();

        if ($parentSpan === null) {
            return;
        }

        $callback($parentSpan);
    }
}
