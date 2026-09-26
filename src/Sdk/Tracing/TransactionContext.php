<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tracing;

final class TransactionContext extends SpanContext
{
    private const TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    public const DEFAULT_NAME = '<unlabeled transaction>';

    /**
     * @var string Name of the transaction
     */
    private $name;

    /**
     * @var bool|null The parent's sampling decision
     */
    private $parentSampled;

    /**
     * @var TransactionMetadata The transaction metadata
     */
    private $metadata;

    /**
     * Constructor.
     *
     * @param string                   $name          The name of the transaction
     * @param bool|null                $parentSampled The parent's sampling decision
     * @param TransactionMetadata|null $metadata      The transaction metadata
     */
    public function __construct(
        string $name = self::DEFAULT_NAME,
        ?bool $parentSampled = null,
        ?TransactionMetadata $metadata = null
    ) {
        $this->name = $name;
        $this->parentSampled = $parentSampled;
        $this->metadata = $metadata ?? new TransactionMetadata();
    }

    /**
     * Gets the name of the transaction.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the transaction.
     *
     * @param string $name The name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the parent's sampling decision.
     */
    public function getParentSampled(): ?bool
    {
        return $this->parentSampled;
    }

    /**
     * Sets the parent's sampling decision.
     *
     * @param bool|null $parentSampled The decision
     */
    public function setParentSampled(?bool $parentSampled): void
    {
        $this->parentSampled = $parentSampled;
    }

    /**
     * Gets the transaction metadata.
     */
    public function getMetadata(): TransactionMetadata
    {
        return $this->metadata;
    }

    /**
     * Sets the transaction metadata.
     *
     * @param TransactionMetadata $metadata The transaction metadata
     */
    public function setMetadata(TransactionMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Sets the transaction source.
     *
     * @param TransactionSource $transactionSource The transaction source
     */
    public function setSource(TransactionSource $transactionSource): void
    {
        $this->metadata->setSource($transactionSource);
    }

    /**
     * Returns a context populated with the data of the given header.
     *
     * @param string $header The jasnita-trace header from the request
     *
     * @deprecated since version 3.9, to be removed in 4.0
     */
    public static function fromJasnitaTrace(string $header): self
    {
        $context = new self();

        if (!preg_match(self::TRACEPARENT_HEADER_REGEX, $header, $matches)) {
            return $context;
        }

        if (!empty($matches['trace_id'])) {
            $context->traceId = new TraceId($matches['trace_id']);
        }

        if (!empty($matches['span_id'])) {
            $context->parentSpanId = new SpanId($matches['span_id']);
        }

        if (isset($matches['sampled'])) {
            $context->parentSampled = '1' === $matches['sampled'];
        }

        return $context;
    }

    /**
     * Returns a context populated with the data of the given environment variables.
     *
     * @param string $jasnitaTrace The jasnita-trace value from the environment
     * @param string $baggage     The baggage header value from the environment
     */
    public static function fromEnvironment(string $jasnitaTrace, string $baggage): self
    {
        return self::parseTraceAndBaggage($jasnitaTrace, $baggage);
    }

    /**
     * Returns a context populated with the data of the given headers.
     *
     * @param string $jasnitaTraceHeader The jasnita-trace header from an incoming request
     * @param string $baggageHeader     The baggage header from an incoming request
     */
    public static function fromHeaders(string $jasnitaTraceHeader, string $baggageHeader): self
    {
        return self::parseTraceAndBaggage($jasnitaTraceHeader, $baggageHeader);
    }

    private static function parseTraceAndBaggage(string $jasnitaTrace, string $baggage): self
    {
        $context = new self();
        $hasJasnitaTrace = false;

        if (preg_match(self::TRACEPARENT_HEADER_REGEX, $jasnitaTrace, $matches)) {
            if (!empty($matches['trace_id'])) {
                $context->traceId = new TraceId($matches['trace_id']);
                $hasJasnitaTrace = true;
            }

            if (!empty($matches['span_id'])) {
                $context->parentSpanId = new SpanId($matches['span_id']);
                $hasJasnitaTrace = true;
            }

            if (isset($matches['sampled'])) {
                $context->parentSampled = '1' === $matches['sampled'];
                $hasJasnitaTrace = true;
            }
        }

        $samplingContext = DynamicSamplingContext::fromHeader($baggage);

        if ($hasJasnitaTrace && !$samplingContext->hasEntries()) {
            // The request comes from an old SDK which does not support Dynamic Sampling.
            // Propagate the Dynamic Sampling Context as is, but frozen, even without jasnita-* entries.
            $samplingContext->freeze();
            $context->getMetadata()->setDynamicSamplingContext($samplingContext);
        }

        if ($hasJasnitaTrace && $samplingContext->hasEntries()) {
            // The baggage header contains Dynamic Sampling Context data from an upstream SDK.
            // Propagate this Dynamic Sampling Context.
            $context->getMetadata()->setDynamicSamplingContext($samplingContext);
        }

        return $context;
    }
}
