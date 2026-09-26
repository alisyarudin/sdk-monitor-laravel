<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tracing\Traits;

use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Tracing\DynamicSamplingContext;
use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;

/**
 * @internal
 */
trait TraceHeaderParserTrait
{
    /**
     * @var string The regex for parsing the jasnita-trace header
     */
    private static $jasnitaTraceparentHeaderRegex = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    /**
     * Parses the jasnita-trace and baggage headers and returns the extracted data.
     *
     * @param string $jasnitaTrace The jasnita-trace header value
     * @param string $baggage     The baggage header value
     *
     * @return array{
     *     traceId: TraceId|null,
     *     parentSpanId: SpanId|null,
     *     parentSampled: bool|null,
     *     dynamicSamplingContext: DynamicSamplingContext|null,
     *     sampleRand: float|null,
     *     parentSamplingRate: float|null
     * }
     */
    protected static function parseTraceAndBaggageHeaders(string $jasnitaTrace, string $baggage): array
    {
        $result = [
            'traceId' => null,
            'parentSpanId' => null,
            'parentSampled' => null,
            'dynamicSamplingContext' => null,
            'sampleRand' => null,
            'parentSamplingRate' => null,
        ];

        $hasJasnitaTrace = false;

        if (preg_match(self::$jasnitaTraceparentHeaderRegex, $jasnitaTrace, $matches)) {
            if (!empty($matches['trace_id'])) {
                $result['traceId'] = new TraceId($matches['trace_id']);
                $hasJasnitaTrace = true;
            }

            if (!empty($matches['span_id'])) {
                $result['parentSpanId'] = new SpanId($matches['span_id']);
                $hasJasnitaTrace = true;
            }

            if (isset($matches['sampled'])) {
                $result['parentSampled'] = $matches['sampled'] === '1';
                $hasJasnitaTrace = true;
            }
        }

        $samplingContext = DynamicSamplingContext::fromHeader($baggage);

        if ($hasJasnitaTrace && !self::shouldContinueTrace($samplingContext)) {
            $result['traceId'] = null;
            $result['parentSpanId'] = null;
            $result['parentSampled'] = null;

            return $result;
        }

        if ($hasJasnitaTrace) {
            // The request comes from an old SDK which does not support Dynamic Sampling.
            // Propagate the Dynamic Sampling Context as is, but frozen, even without jasnita-* entries.
            if (!$samplingContext->hasEntries()) {
                $samplingContext->freeze();
            }

            // The baggage header contains Dynamic Sampling Context data from an upstream SDK.
            // Propagate this Dynamic Sampling Context.
            $result['dynamicSamplingContext'] = $samplingContext;

            // Store the propagated traces sample rate
            if ($samplingContext->has('sample_rate')) {
                $result['parentSamplingRate'] = (float) $samplingContext->get('sample_rate');
            }
        }

        // Store the propagated trace sample rand or generate a new one
        if ($hasJasnitaTrace) {
            $incomingSampleRand = self::parseSampleRand($samplingContext);
            if ($incomingSampleRand !== null) {
                $result['sampleRand'] = $incomingSampleRand;
            } elseif ($samplingContext->has('sample_rate') && $result['parentSampled'] !== null) {
                if ($result['parentSampled'] === true) {
                    // [0, rate)
                    $result['sampleRand'] = round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() * (float) $samplingContext->get('sample_rate'), 6);
                } else {
                    // [rate, 1)
                    $result['sampleRand'] = round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() * (1 - (float) $samplingContext->get('sample_rate')) + (float) $samplingContext->get('sample_rate'), 6);
                }
            } elseif ($result['parentSampled'] !== null) {
                // [0, 1)
                $result['sampleRand'] = round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(), 6);
            }
        }

        return $result;
    }

    private static function parseSampleRand(DynamicSamplingContext $samplingContext): ?float
    {
        $sampleRand = $samplingContext->get('sample_rand');
        if ($sampleRand === null) {
            return null;
        }

        if (is_numeric($sampleRand)) {
            $sampleRandAsFloat = (float) $sampleRand;
            if ($sampleRandAsFloat >= 0.0 && $sampleRandAsFloat < 1.0) {
                return $sampleRandAsFloat;
            }
        }

        $hub = JasnitaSdk::getCurrentHub();
        $client = $hub->getClient();
        if ($client !== null) {
            $client->getOptions()->getLoggerOrNullLogger()->debug(
                'Ignoring invalid jasnita-sample_rand baggage value because it must be a numeric value in the range [0, 1).',
                ['sample_rand' => $sampleRand]
            );
        }

        return null;
    }

    private static function shouldContinueTrace(DynamicSamplingContext $samplingContext): bool
    {
        $hub = JasnitaSdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client === null) {
            return true;
        }

        $options = $client->getOptions();
        $clientOrgId = $options->getOrgId();
        if ($clientOrgId === null && $options->getDsn() !== null) {
            $clientOrgId = $options->getDsn()->getOrgId();
        }

        $baggageOrgId = $samplingContext->get('org_id');
        $logger = $options->getLoggerOrNullLogger();

        // both org IDs are set but are not equals
        if ($clientOrgId !== null && $baggageOrgId !== null && ((string) $clientOrgId !== $baggageOrgId)) {
            $logger->debug(
                \sprintf(
                    "Starting a new trace because org IDs don't match (incoming baggage org_id: %s, SDK org_id: %s)",
                    $baggageOrgId,
                    $clientOrgId
                )
            );

            return false;
        }

        // One org ID is not set and strict trace continuation is enabled
        if ($options->isStrictTraceContinuationEnabled() && ($clientOrgId === null) !== ($baggageOrgId === null)) {
            $logger->debug(
                \sprintf(
                    'Starting a new trace because strict trace continuation is enabled and one org ID is missing (incoming baggage org_id: %s, SDK org_id: %s)',
                    $baggageOrgId !== null ? $baggageOrgId : 'none',
                    $clientOrgId !== null ? (string) $clientOrgId : 'none'
                )
            );

            return false;
        }

        return true;
    }
}
