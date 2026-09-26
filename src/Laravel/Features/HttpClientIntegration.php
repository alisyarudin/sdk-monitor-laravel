<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features;

use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Laravel\Features\Concerns\TracksPushedScopesAndSpans;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanStatus;
use function Jasnita\Monitor\Sdk\getBaggage;
use function Jasnita\Monitor\Sdk\getTraceparent;

class HttpClientIntegration extends Feature
{
    use TracksPushedScopesAndSpans;

    private const FEATURE_KEY = 'http_client_requests';

    public function isApplicable(): bool
    {
        return $this->isTracingFeatureEnabled(self::FEATURE_KEY)
            || $this->isBreadcrumbFeatureEnabled(self::FEATURE_KEY);
    }

    public function onBoot(Dispatcher $events, Factory $factory): void
    {
        if ($this->isTracingFeatureEnabled(self::FEATURE_KEY)) {
            $events->listen(RequestSending::class, [$this, 'handleRequestSendingHandlerForTracing']);
            $events->listen(ResponseReceived::class, [$this, 'handleResponseReceivedHandlerForTracing']);
            $events->listen(ConnectionFailed::class, [$this, 'handleConnectionFailedHandlerForTracing']);

            // The `globalRequestMiddleware` functionality was introduced in Laravel 10.14
            if (method_exists($factory, 'globalRequestMiddleware')) {
                $factory->globalRequestMiddleware([$this, 'attachTracingHeadersToRequest']);
            }
        }

        if ($this->isBreadcrumbFeatureEnabled(self::FEATURE_KEY)) {
            $events->listen(ResponseReceived::class, [$this, 'handleResponseReceivedHandlerForBreadcrumb']);
            $events->listen(ConnectionFailed::class, [$this, 'handleConnectionFailedHandlerForBreadcrumb']);
        }
    }

    public function attachTracingHeadersToRequest(RequestInterface $request)
    {
        if ($this->shouldAttachTracingHeaders($request)) {
            return $request
                ->withHeader('baggage', getBaggage())
                ->withHeader('jasnita-trace', getTraceparent());
        }

        return $request;
    }

    public function handleRequestSendingHandlerForTracing(RequestSending $event): void
    {
        $parentSpan = JasnitaSdk::getCurrentHub()->getSpan();

        // If there is no sampled span there is no need to handle the event
        if ($parentSpan === null || !$parentSpan->getSampled()) {
            return;
        }

        $fullUri = $this->getFullUri($event->request->url());
        $partialUri = $this->getPartialUri($fullUri);

        $this->pushSpan(
            $parentSpan->startChild(
                SpanContext::make()
                    ->setOp('http.client')
                    ->setData([
                        'url' => $partialUri,
                        // See: (dokumentasi hulu)
                        'http.query' => $fullUri->getQuery(),
                        'http.fragment' => $fullUri->getFragment(),
                        'http.request.method' => $event->request->method(),
                        'http.request.body.size' => $event->request->toPsrRequest()->getBody()->getSize(),
                    ])
                    ->setOrigin('auto.http.client')
                    ->setDescription($event->request->method() . ' ' . $partialUri)
            )
        );
    }

    public function handleResponseReceivedHandlerForTracing(ResponseReceived $event): void
    {
        $span = $this->maybePopSpan();

        if ($span !== null) {
            $span->setData(array_merge($span->getData(), [
                // See: (dokumentasi hulu)
                'http.response.status_code' => $event->response->status(),
                'http.response.body.size' => $event->response->toPsrResponse()->getBody()->getSize(),
            ]));
            $span->setHttpStatus($event->response->status());
            $span->finish();
        }
    }

    public function handleConnectionFailedHandlerForTracing(ConnectionFailed $event): void
    {
        $this->maybeFinishSpan(SpanStatus::internalError());
    }

    public function handleResponseReceivedHandlerForBreadcrumb(ResponseReceived $event): void
    {
        $level = Breadcrumb::LEVEL_INFO;

        if ($event->response->clientError()) {
            $level = Breadcrumb::LEVEL_WARNING;
        } elseif ($event->response->serverError()) {
            $level = Breadcrumb::LEVEL_ERROR;
        }

        $fullUri = $this->getFullUri($event->request->url());

        Integration::addBreadcrumb(new Breadcrumb(
            $level,
            Breadcrumb::TYPE_HTTP,
            'http',
            null,
            [
                'url' => $this->getPartialUri($fullUri),
                // See: (dokumentasi hulu)
                'http.query' => $fullUri->getQuery(),
                'http.fragment' => $fullUri->getFragment(),
                'http.request.method' => $event->request->method(),
                'http.response.status_code' => $event->response->status(),
                'http.request.body.size' => $event->request->toPsrRequest()->getBody()->getSize(),
                'http.response.body.size' => $event->response->toPsrResponse()->getBody()->getSize(),
            ]
        ));
    }

    public function handleConnectionFailedHandlerForBreadcrumb(ConnectionFailed $event): void
    {
        $fullUri = $this->getFullUri($event->request->url());

        Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_HTTP,
            'http',
            null,
            [
                'url' => $this->getPartialUri($fullUri),
                // See: (dokumentasi hulu)
                'http.query' => $fullUri->getQuery(),
                'http.fragment' => $fullUri->getFragment(),
                'http.request.method' => $event->request->method(),
                'http.request.body.size' => $event->request->toPsrRequest()->getBody()->getSize(),
            ]
        ));
    }

    /**
     * Construct a full URI.
     *
     * @param string $url
     *
     * @return UriInterface
     */
    private function getFullUri(string $url): UriInterface
    {
        return new Uri($url);
    }

    /**
     * Construct a partial URI, excluding the authority, query and fragment parts.
     *
     * @param UriInterface $uri
     *
     * @return string
     */
    private function getPartialUri(UriInterface $uri): string
    {
        return (string)Uri::fromParts([
            'scheme' => $uri->getScheme(),
            'host' => $uri->getHost(),
            'port' => $uri->getPort(),
            'path' => $uri->getPath(),
        ]);
    }

    private function shouldAttachTracingHeaders(RequestInterface $request): bool
    {
        $client = JasnitaSdk::getCurrentHub()->getClient();
        if ($client === null) {
            return false;
        }

        $sdkOptions = $client->getOptions();

        // Check if the request destination is allow listed in the trace_propagation_targets option.
        return $sdkOptions->getTracePropagationTargets() === null
            || in_array($request->getUri()->getHost(), $sdkOptions->getTracePropagationTargets());
    }
}
