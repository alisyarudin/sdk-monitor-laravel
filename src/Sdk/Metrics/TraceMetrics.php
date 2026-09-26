<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Metrics;

use Jasnita\Monitor\Sdk\EventId;
use Jasnita\Monitor\Sdk\Metrics\Types\CounterMetric;
use Jasnita\Monitor\Sdk\Metrics\Types\DistributionMetric;
use Jasnita\Monitor\Sdk\Metrics\Types\GaugeMetric;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Unit;

class TraceMetrics
{
    /**
     * @var self|null
     */
    private static $instance;

    public function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new TraceMetrics();
        }

        return self::$instance;
    }

    /**
     * @param int|float                                 $value
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function count(
        string $name,
        $value,
        array $attributes = [],
        ?Unit $unit = null
    ): void {
        $this->aggregator()->add(
            CounterMetric::TYPE,
            $name,
            $value,
            $attributes,
            $unit
        );
    }

    /**
     * @param int|float                                 $value
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function distribution(
        string $name,
        $value,
        array $attributes = [],
        ?Unit $unit = null
    ): void {
        $this->aggregator()->add(
            DistributionMetric::TYPE,
            $name,
            $value,
            $attributes,
            $unit
        );
    }

    /**
     * @param int|float                                 $value
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function gauge(
        string $name,
        $value,
        array $attributes = [],
        ?Unit $unit = null
    ): void {
        $this->aggregator()->add(
            GaugeMetric::TYPE,
            $name,
            $value,
            $attributes,
            $unit
        );
    }

    public function flush(): ?EventId
    {
        return $this->aggregator()->flush();
    }

    private function aggregator(): MetricsAggregator
    {
        return JasnitaSdk::getCurrentRuntimeContext()->getMetricsAggregator();
    }
}
