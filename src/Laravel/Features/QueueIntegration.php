<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Queue;
use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Laravel\Features\Concerns\TracksPushedScopesAndSpans;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\PropagationContext;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanStatus;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionSource;

use function Jasnita\Monitor\Sdk\continueTrace;
use function Jasnita\Monitor\Sdk\getBaggage;
use function Jasnita\Monitor\Sdk\getTraceparent;

class QueueIntegration extends Feature
{
    use TracksPushedScopesAndSpans {
        pushScope as private pushScopeTrait;
    }

    private const QUEUE_SPAN_OP_QUEUE_PUBLISH = 'queue.publish';

    private const QUEUE_PAYLOAD_BAGGAGE_DATA = 'jasnita_baggage_data';
    private const QUEUE_PAYLOAD_TRACE_PARENT_DATA = 'jasnita_trace_parent_data';
    private const QUEUE_PAYLOAD_PUBLISH_TIME = 'jasnita_publish_time';

    public function isApplicable(): bool
    {
        if (!$this->container()->bound('queue')) {
            return false;
        }

        return $this->isBreadcrumbFeatureEnabled('queue_info')
            || $this->isTracingFeatureEnabled('queue_jobs')
            || $this->isTracingFeatureEnabled('queue_job_transactions');
    }

    public function onBoot(Dispatcher $events): void
    {
        $events->listen(JobQueueing::class, [$this, 'handleJobQueueingEvent']);
        $events->listen(JobQueued::class, [$this, 'handleJobQueuedEvent']);

        $events->listen(JobProcessed::class, [$this, 'handleJobProcessedQueueEvent']);
        $events->listen(JobProcessing::class, [$this, 'handleJobProcessingQueueEvent']);
        $events->listen(WorkerStopping::class, [$this, 'handleWorkerStoppingQueueEvent']);
        $events->listen(JobExceptionOccurred::class, [$this, 'handleJobExceptionOccurredQueueEvent']);

        if ($this->isTracingFeatureEnabled('queue_jobs') || $this->isTracingFeatureEnabled('queue_job_transactions')) {
            Queue::createPayloadUsing(function (?string $connection, ?string $queue, ?array $payload): ?array {
                $parentSpan = JasnitaSdk::getCurrentHub()->getSpan();

                if ($parentSpan !== null && $parentSpan->getSampled()) {
                    $context = (new SpanContext)
                        ->setOp(self::QUEUE_SPAN_OP_QUEUE_PUBLISH)
                        ->setData([
                            'messaging.system' => 'laravel',
                            'messaging.message.id' => $payload['uuid'] ?? null,
                            'messaging.destination.name' => $this->normalizeQueueName($queue),
                            'messaging.destination.connection' => $connection,
                        ])
                        ->setDescription($queue);

                    $this->pushSpan($parentSpan->startChild($context));
                }

                if ($payload !== null) {
                    $payload[self::QUEUE_PAYLOAD_BAGGAGE_DATA] = getBaggage();
                    $payload[self::QUEUE_PAYLOAD_TRACE_PARENT_DATA] = getTraceparent();
                    $payload[self::QUEUE_PAYLOAD_PUBLISH_TIME] = microtime(true);
                }

                return $payload;
            });
        }
    }

    public function handleJobQueueingEvent(JobQueueing $event): void
    {
        $currentSpan = JasnitaSdk::getCurrentHub()->getSpan();

        // If there is no tracing span active there is no need to handle the event
        if ($currentSpan === null || $currentSpan->getOp() !== self::QUEUE_SPAN_OP_QUEUE_PUBLISH) {
            return;
        }

        $jobName = $event->job;

        if ($jobName instanceof Closure) {
            $jobName = 'Closure';
        } elseif (is_object($jobName)) {
            $jobName = get_class($jobName);
        }

        $currentSpan
            ->setDescription($jobName);
    }

    public function handleJobQueuedEvent(JobQueued $event): void
    {
        $this->maybeFinishSpan();
    }

    public function handleJobProcessedQueueEvent(JobProcessed $event): void
    {
        $this->maybeFinishSpan(SpanStatus::ok());

        $this->maybePopScope();
    }

    public function handleJobProcessingQueueEvent(JobProcessing $event): void
    {
        $this->maybePopScope();

        $this->pushScope();

        if ($this->isBreadcrumbFeatureEnabled('queue_info')) {
            $job = [
                'job' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
                'connection' => $event->connectionName,
            ];

            // Resolve name exists only from Laravel 5.3+
            if (method_exists($event->job, 'resolveName')) {
                $job['resolved'] = $event->job->resolveName();
            }

            Integration::addBreadcrumb(new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                'queue.job',
                'Processing queue job',
                $job
            ));
        }

        $parentSpan = JasnitaSdk::getCurrentHub()->getSpan();

        // If there is no tracing span active and we don't trace jobs as transactions there is no need to handle the event
        if ($parentSpan === null && !$this->isTracingFeatureEnabled('queue_job_transactions')) {
            return;
        }

        // If there is a parent span we can record the job as a child unless the parent is not sample or we are configured to not do so
        if ($parentSpan !== null && (!$parentSpan->getSampled() || !$this->isTracingFeatureEnabled('queue_jobs'))) {
            return;
        }

        $jobPayload = $event->job->payload();

        if ($parentSpan === null) {
            $baggage = $jobPayload[self::QUEUE_PAYLOAD_BAGGAGE_DATA] ?? null;
            $traceParent = $jobPayload[self::QUEUE_PAYLOAD_TRACE_PARENT_DATA] ?? null;

            $context = continueTrace($traceParent ?? '', $baggage ?? '');

            // If the parent transaction was not sampled we also stop the queue job from being recorded
            if ($context->getParentSampled() === false) {
                return;
            }
        } else {
            $context = new SpanContext;
        }

        $resolvedJobName = $event->job->resolveName();

        $jobPublishedAt = $jobPayload[self::QUEUE_PAYLOAD_PUBLISH_TIME] ?? null;

        $job = [
            'messaging.system' => 'laravel',

            'messaging.destination.name' => $this->normalizeQueueName($event->job->getQueue()),
            'messaging.destination.connection' => $event->connectionName,

            'messaging.message.id' => $jobPayload['uuid'] ?? null,
            'messaging.message.envelope.size' => strlen($event->job->getRawBody()),
            'messaging.message.body.size' => strlen(json_encode($jobPayload['data'] ?? [])),
            'messaging.message.retry.count' => $event->job->attempts() - 1,
            'messaging.message.receive.latency' => $jobPublishedAt !== null ? microtime(true) - $jobPublishedAt : null,
        ];

        if ($context instanceof TransactionContext) {
            $context->setName($resolvedJobName);
            $context->setSource(TransactionSource::task());
        }

        $context->setOp('queue.process');
        $context->setData($job);
        $context->setOrigin('auto.queue');
        $context->setStartTimestamp(microtime(true));

        // When the parent span is null we start a new transaction otherwise we start a child of the current span
        if ($parentSpan === null) {
            $span = JasnitaSdk::getCurrentHub()->startTransaction($context);
        } else {
            $span = $parentSpan->startChild($context);
        }

        $this->pushSpan($span);
    }

    public function handleWorkerStoppingQueueEvent(WorkerStopping $event): void
    {
        Integration::flushEvents();
    }

    public function handleJobExceptionOccurredQueueEvent(JobExceptionOccurred $event): void
    {
        $this->maybeFinishSpan(SpanStatus::internalError());

        Integration::flushEvents();
    }

    private function normalizeQueueName(?string $queue): string
    {
        if ($queue === null) {
            return '';
        }

        // SQS queues are sometimes formatted like: https://sqs.<region>.amazonaws.com/<id>/<queue_name>
        if (filter_var($queue, FILTER_VALIDATE_URL) !== false) {
            return Str::afterLast($queue, '/');
        }

        // Jobs pushed onto the Redis driver are formatted as queues:<queue>
        return Str::after($queue, 'queues:');
    }

    protected function pushScope(): void
    {
        $this->pushScopeTrait();

        // When a job starts, we want to make sure the scope is cleared of breadcrumbs
        // as well as setting a new propagation context.
        JasnitaSdk::getCurrentHub()->configureScope(static function (Scope $scope) {
            $scope->clearBreadcrumbs();
            $scope->setPropagationContext(PropagationContext::fromDefaults());
        });
    }
}
