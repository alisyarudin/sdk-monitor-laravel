<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Transport;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventType;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Response;
use Jasnita\Monitor\Sdk\ResponseStatus;
use Jasnita\Monitor\Sdk\Serializer\PayloadSerializerInterface;

/**
 * This transport sends the events using a syncronous HTTP client that will
 * delay sending of the requests until the shutdown of the application.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class HttpTransport implements TransportInterface
{
    /**
     * @var Options The Jasnita client options
     */
    private $options;

    /**
     * @var HttpAsyncClientInterface The HTTP client
     */
    private $httpClient;

    /**
     * @var StreamFactoryInterface The PSR-7 stream factory
     */
    private $streamFactory;

    /**
     * @var RequestFactoryInterface The PSR-7 request factory
     */
    private $requestFactory;

    /**
     * @var PayloadSerializerInterface The event serializer
     */
    private $payloadSerializer;

    /**
     * @var LoggerInterface A PSR-3 logger
     */
    private $logger;

    /**
     * @var RateLimiter The rate limiter
     */
    private $rateLimiter;

    /**
     * Constructor.
     *
     * @param Options                    $options           The Jasnita client configuration
     * @param HttpAsyncClientInterface   $httpClient        The HTTP client
     * @param StreamFactoryInterface     $streamFactory     The PSR-7 stream factory
     * @param RequestFactoryInterface    $requestFactory    The PSR-7 request factory
     * @param PayloadSerializerInterface $payloadSerializer The event serializer
     * @param LoggerInterface|null       $logger            An instance of a PSR-3 logger
     */
    public function __construct(
        Options $options,
        HttpAsyncClientInterface $httpClient,
        StreamFactoryInterface $streamFactory,
        RequestFactoryInterface $requestFactory,
        PayloadSerializerInterface $payloadSerializer,
        ?LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->httpClient = $httpClient;
        $this->streamFactory = $streamFactory;
        $this->requestFactory = $requestFactory;
        $this->payloadSerializer = $payloadSerializer;
        $this->logger = $logger ?? new NullLogger();
        $this->rateLimiter = new RateLimiter($this->logger);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event): PromiseInterface
    {
        $dsn = $this->options->getDsn();

        if (null === $dsn) {
            throw new \RuntimeException(sprintf('The DSN option must be set to use the "%s" transport.', self::class));
        }

        $eventType = $event->getType();

        if ($this->rateLimiter->isRateLimited($eventType)) {
            $this->logger->warning(
                sprintf('Rate limit exceeded for sending requests of type "%s".', (string) $eventType),
                ['event' => $event]
            );

            return new RejectedPromise(new Response(ResponseStatus::rateLimit(), $event));
        }

        if (
            $this->options->isTracingEnabled() ||
            EventType::transaction() === $eventType ||
            EventType::checkIn() === $eventType
        ) {
            $request = $this->requestFactory->createRequest('POST', $dsn->getEnvelopeApiEndpointUrl())
                ->withHeader('Content-Type', 'application/x-jasnita-envelope')
                ->withBody($this->streamFactory->createStream($this->payloadSerializer->serialize($event)));
        } else {
            $request = $this->requestFactory->createRequest('POST', $dsn->getStoreApiEndpointUrl())
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->payloadSerializer->serialize($event)));
        }

        try {
            /** @var ResponseInterface $response */
            $response = $this->httpClient->sendAsyncRequest($request)->wait();
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send the event to Jasnita. Reason: "%s".', $exception->getMessage()),
                ['exception' => $exception, 'event' => $event]
            );

            return new RejectedPromise(new Response(ResponseStatus::failed(), $event));
        }

        $sendResponse = $this->rateLimiter->handleResponse($event, $response);

        if (ResponseStatus::success() === $sendResponse->getStatus()) {
            return new FulfilledPromise($sendResponse);
        }

        return new RejectedPromise($sendResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
