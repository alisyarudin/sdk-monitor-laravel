<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Metrics\Types;

use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Jasnita\Monitor\Sdk\Unit;

/**
 * @internal
 */
final class CounterMetric extends Metric
{
    /**
     * @var string
     */
    public const TYPE = 'counter';

    /**
     * @var int|float
     */
    private $value;

    /**
     * @param int|float                                 $value
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function __construct(
        string $name,
        $value,
        TraceId $traceId,
        SpanId $spanId,
        array $attributes,
        float $timestamp,
        ?Unit $unit
    ) {
        parent::__construct($name, $traceId, $spanId, $timestamp, $attributes, $unit);

        $this->value = $value;
    }

    /**
     * @param int|float $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * @return int|float
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
