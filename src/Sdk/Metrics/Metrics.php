<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Metrics;

use Jasnita\Monitor\Sdk\EventId;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Unit;

use function Jasnita\Monitor\Sdk\trace;

class_alias(Unit::class, '\Jasnita\Monitor\Sdk\Metrics\MetricsUnit');

/**
 * @deprecated use TraceMetrics instead
 */
class Metrics
{
    /**
     * @var self|null
     */
    private static $instance;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array<string, string> $tags
     *
     * @deprecated Use TraceMetrics::count() instead. To be removed in 5.x.
     */
    public function increment(
        string $key,
        float $value,
        ?Unit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    /**
     * @param array<string, string> $tags
     *
     * @deprecated Use TraceMetrics::distribution() instead. Metrics API is a no-op and will be removed in 5.x.
     */
    public function distribution(
        string $key,
        float $value,
        ?Unit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    /**
     * @param array<string, string> $tags
     *
     * @deprecated Use TraceMetrics::gauge() instead. To be removed in 5.x.
     */
    public function gauge(
        string $key,
        float $value,
        ?Unit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    /**
     * @param int|string            $value
     * @param array<string, string> $tags
     *
     * @deprecated Metrics are no longer supported. Metrics API is a no-op and will be removed in 5.x.
     */
    public function set(
        string $key,
        $value,
        ?Unit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    /**
     * @template T
     *
     * @param callable(): T         $callback
     * @param array<string, string> $tags
     *
     * @return T
     *
     * @deprecated Metrics are no longer supported. Metrics API is a no-op and will be removed in 5.x.
     */
    public function timing(
        string $key,
        callable $callback,
        array $tags = [],
        int $stackLevel = 0
    ) {
        return trace(
            static function () use ($callback) {
                return $callback();
            },
            SpanContext::make()
                ->setOp('metric.timing')
                ->setOrigin('auto.measure.metrics.timing')
                ->setDescription($key)
        );
    }

    /**
     * @deprecated Metrics are no longer supported. Metrics API is a no-op and will be removed in 5.x.
     */
    public function flush(): ?EventId
    {
        return null;
    }
}
