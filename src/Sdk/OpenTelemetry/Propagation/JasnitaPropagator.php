<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\OpenTelemetry\Propagation;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

class JasnitaPropagator implements TextMapPropagatorInterface
{
    public const JASNITA_MONITOR_TRACE = 'jasnita-trace';

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

    public function fields(): array
    {
        return [self::JASNITA_MONITOR_TRACE];
    }

    /**
     * @param mixed $carrier
     */
    public function inject(&$carrier, ?PropagationSetterInterface $setter = null, ?ContextInterface $context = null): void
    {
        if ($setter === null) {
            $setter = ArrayAccessGetterSetter::getInstance();
        }

        if ($context === null) {
            $context = Context::getCurrent();
        }

        $spanContext = Span::fromContext($context)->getContext();

        if (!$spanContext->isValid()) {
            return;
        }

        $sampled = $spanContext->isSampled() ? '1' : '0';
        $jasnitaTrace = \sprintf('%s-%s-%s', $spanContext->getTraceId(), $spanContext->getSpanId(), $sampled);

        $setter->set($carrier, self::JASNITA_MONITOR_TRACE, $jasnitaTrace);
    }

    /**
     * @param mixed $carrier
     */
    public function extract($carrier, ?PropagationGetterInterface $getter = null, ?ContextInterface $context = null): ContextInterface
    {
        if ($getter === null) {
            $getter = ArrayAccessGetterSetter::getInstance();
        }

        if ($context === null) {
            $context = Context::getCurrent();
        }

        // Traceparent header has higher precedence over jasnita-trace header if traceparent propagator is enabled.
        if (!empty($getter->get($carrier, TraceContextPropagator::TRACEPARENT)) && $this->isTraceparentPropagatorEnabled()) {
            return $context;
        }

        $jasnitaTrace = $getter->get($carrier, self::JASNITA_MONITOR_TRACE);
        if ($jasnitaTrace === null) {
            return $context;
        }

        // Format: jasnita-trace = {trace-id}-{span-id}-{sampled flag (optional)}.
        $parts = explode('-', $jasnitaTrace);

        // If the header does not have at least 2 parts, it is invalid.
        if (\count($parts) < 2) {
            return $context;
        }

        [$traceId, $spanId] = $parts;
        $traceFlags = isset($parts[2]) && $parts[2] === '1' ? TraceFlags::SAMPLED : TraceFlags::DEFAULT;
        $spanContext = SpanContext::createFromRemoteParent($traceId, $spanId, $traceFlags);

        if (!$spanContext->isValid()) {
            return $context;
        }

        return $context->withContextValue(Span::wrap($spanContext));
    }

    private function isTraceparentPropagatorEnabled(): bool
    {
        return \in_array(TraceContextPropagator::TRACEPARENT, Globals::propagator()->fields());
    }
}
