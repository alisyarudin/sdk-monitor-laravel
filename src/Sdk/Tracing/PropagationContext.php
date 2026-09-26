<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tracing;

use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\Traits\TraceHeaderParserTrait;

final class PropagationContext
{
    use TraceHeaderParserTrait;

    /**
     * @var TraceId The trace id
     */
    private $traceId;

    /**
     * @var SpanId The span id
     */
    private $spanId;

    /**
     * @var SpanId|null The parent span id
     */
    private $parentSpanId;

    /**
     * @var bool|null The parent's sampling decision
     */
    private $parentSampled;

    /**
     * @var float|null
     */
    private $sampleRand;

    /**
     * @var DynamicSamplingContext|null The dynamic sampling context
     */
    private $dynamicSamplingContext;

    private function __construct()
    {
    }

    public static function fromDefaults(): self
    {
        $context = new self();

        $context->traceId = TraceId::generate();
        $context->spanId = SpanId::generate();
        $context->parentSpanId = null;
        $context->parentSampled = null;
        $context->sampleRand = round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(), 6);
        $context->dynamicSamplingContext = null;

        return $context;
    }

    public static function fromHeaders(string $jasnitaTraceHeader, string $baggageHeader): self
    {
        return self::parseTraceparentAndBaggage($jasnitaTraceHeader, $baggageHeader);
    }

    public static function fromEnvironment(string $jasnitaTrace, string $baggage): self
    {
        return self::parseTraceparentAndBaggage($jasnitaTrace, $baggage);
    }

    /**
     * Returns a string that can be used for the `jasnita-trace` header & meta tag.
     */
    public function toTraceparent(): string
    {
        return \sprintf('%s-%s', (string) $this->traceId, (string) $this->spanId);
    }

    /**
     * Returns a string that can be used for the W3C `traceparent` header & meta tag.
     *
     * @deprecated since version 4.12. To be removed in version 5.0.
     */
    public function toW3CTraceparent(): string
    {
        return '';
    }

    /**
     * Returns a string that can be used for the `baggage` header & meta tag.
     */
    public function toBaggage(): string
    {
        if ($this->dynamicSamplingContext === null) {
            $hub = JasnitaSdk::getCurrentHub();
            $client = $hub->getClient();

            if ($client !== null) {
                $options = $client->getOptions();

                if ($options !== null) {
                    $hub->configureScope(function (Scope $scope) use ($options) {
                        $this->dynamicSamplingContext = DynamicSamplingContext::fromOptions($options, $scope);
                    });
                }
            }
        }

        return (string) $this->dynamicSamplingContext;
    }

    /**
     * @return array{trace_id: string, span_id: string, parent_span_id?: string}
     */
    public function getTraceContext(): array
    {
        $result = [
            'trace_id' => (string) $this->traceId,
            'span_id' => (string) $this->spanId,
        ];

        if ($this->parentSpanId !== null) {
            $result['parent_span_id'] = (string) $this->parentSpanId;
        }

        return $result;
    }

    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(TraceId $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setParentSpanId(?SpanId $parentSpanId): void
    {
        $this->parentSpanId = $parentSpanId;
    }

    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    public function setSpanId(SpanId $spanId): self
    {
        $this->spanId = $spanId;

        return $this;
    }

    public function getDynamicSamplingContext(): ?DynamicSamplingContext
    {
        return $this->dynamicSamplingContext;
    }

    public function setDynamicSamplingContext(DynamicSamplingContext $dynamicSamplingContext): self
    {
        $this->dynamicSamplingContext = $dynamicSamplingContext;

        return $this;
    }

    public function getSampleRand(): ?float
    {
        return $this->sampleRand;
    }

    public function setSampleRand(?float $sampleRand): self
    {
        $this->sampleRand = $sampleRand;

        return $this;
    }

    private static function parseTraceparentAndBaggage(string $traceparent, string $baggage): self
    {
        $context = self::fromDefaults();
        $parsedData = self::parseTraceAndBaggageHeaders($traceparent, $baggage);

        if ($parsedData['traceId'] !== null) {
            $context->traceId = $parsedData['traceId'];
        }

        if ($parsedData['parentSpanId'] !== null) {
            $context->parentSpanId = $parsedData['parentSpanId'];
        }

        if ($parsedData['parentSampled'] !== null) {
            $context->parentSampled = $parsedData['parentSampled'];
        }

        if ($parsedData['dynamicSamplingContext'] !== null) {
            $context->dynamicSamplingContext = $parsedData['dynamicSamplingContext'];
        }

        if ($parsedData['sampleRand'] !== null) {
            $context->sampleRand = $parsedData['sampleRand'];
        }

        return $context;
    }
}
