<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\HttpClient;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Agent\Transport\AgentClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\HttpClient\HttpClient;
use Jasnita\Monitor\Sdk\HttpClient\HttpClientInterface;
use Jasnita\Monitor\Sdk\HttpClient\Request;
use Jasnita\Monitor\Sdk\HttpClient\Response;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Serializer\PayloadSerializer;
use Jasnita\Monitor\Sdk\Tests\StubLogger;

final class AgentClientBuilderTest extends TestCase
{
    use TestServer;

    private const UNAVAILABLE_AGENT_HOST = '192.0.2.1';
    private const UNAVAILABLE_AGENT_PORT = 5148;

    protected function setUp(): void
    {
        parent::setUp();

        StubLogger::$logs = [];
    }

    protected function tearDown(): void
    {
        if ($this->serverProcess !== null) {
            $this->stopTestServer();
        }

        StubLogger::$logs = [];
    }

    public function testBuilderUsesFallbackClientByDefaultWhenLocalAgentIsUnavailable(): void
    {
        $testServer = $this->startTestServer();
        $dsn = "http://publicKey@{$testServer}/200";

        $envelope = $this->createEnvelope($dsn, 'Hello from builder default fallback test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $options = new Options(['dsn' => $dsn]);

        $client = AgentClientBuilder::create()
            ->setHost(self::UNAVAILABLE_AGENT_HOST)
            ->setPort(self::UNAVAILABLE_AGENT_PORT)
            ->getClient();
        $response = $client->sendRequest($request, $options);

        $serverOutput = $this->stopTestServer();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getError());
        $this->assertStringContainsString('Hello from builder default fallback test!', $serverOutput['body']);
    }

    public function testBuilderCanDisableFallbackClient(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from disabled fallback builder test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $logger = StubLogger::getInstance();
        $options = new Options(['logger' => $logger]);

        $client = AgentClientBuilder::create()
            ->setHost(self::UNAVAILABLE_AGENT_HOST)
            ->setPort(self::UNAVAILABLE_AGENT_PORT)
            ->disableFallbackClient()
            ->getClient();
        $response = $client->sendRequest($request, $options);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertTrue($response->hasError());
        $this->assertSame('Failed to send envelope to the local Jasnita agent and no fallback client is available.', $response->getError());
        $this->assertTrue($this->hasLogMessage('Failed to hand off envelope to local Jasnita agent.'));
        $this->assertFalse($this->hasLogMessage('Using fallback HTTP client because local Jasnita agent handoff failed.'));
    }

    public function testBuilderUsesCustomFallbackClientWhenConfigured(): void
    {
        $envelope = $this->createEnvelope('http://public@example.com/1', 'Hello from custom fallback builder test!');

        $request = new Request();
        $request->setStringBody($envelope);

        $options = new Options(['dsn' => 'http://public@example.com/1']);
        $fallbackResponse = new Response(201, [], '');

        /** @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $fallbackClient */
        $fallbackClient = $this->createMock(HttpClientInterface::class);
        $fallbackClient->expects($this->once())
            ->method('sendRequest')
            ->with($request, $options)
            ->willReturn($fallbackResponse);

        $client = AgentClientBuilder::create()
            ->setHost(self::UNAVAILABLE_AGENT_HOST)
            ->setPort(self::UNAVAILABLE_AGENT_PORT)
            ->setFallbackClient($fallbackClient)
            ->getClient();
        $response = $client->sendRequest($request, $options);

        $this->assertSame($fallbackResponse, $response);
    }

    public function testBuilderCreatesDefaultFallbackClientWithConfiguredSdkIdentifierAndVersion(): void
    {
        $client = AgentClientBuilder::create()
            ->setHost(self::UNAVAILABLE_AGENT_HOST)
            ->setPort(self::UNAVAILABLE_AGENT_PORT)
            ->setSdkIdentifier('jasnita.test')
            ->setSdkVersion('1.2.3-test')
            ->getClient();

        $fallbackClientFactoryProperty = new \ReflectionProperty($client, 'fallbackClientFactory');
        if (\PHP_VERSION_ID < 80100) {
            $fallbackClientFactoryProperty->setAccessible(true);
        }

        /** @var mixed $fallbackClientFactory */
        $fallbackClientFactory = $fallbackClientFactoryProperty->getValue($client);

        $this->assertIsCallable($fallbackClientFactory);
        $fallbackClient = $fallbackClientFactory();
        $this->assertInstanceOf(HttpClient::class, $fallbackClient);

        $sdkIdentifierProperty = new \ReflectionProperty($fallbackClient, 'sdkIdentifier');
        $sdkVersionProperty = new \ReflectionProperty($fallbackClient, 'sdkVersion');
        if (\PHP_VERSION_ID < 80100) {
            $sdkIdentifierProperty->setAccessible(true);
            $sdkVersionProperty->setAccessible(true);
        }

        $this->assertSame('jasnita.test', $sdkIdentifierProperty->getValue($fallbackClient));
        $this->assertSame('1.2.3-test', $sdkVersionProperty->getValue($fallbackClient));
    }

    private function createEnvelope(string $dsn, string $message): string
    {
        $options = new Options(['dsn' => $dsn]);

        $event = Event::createEvent();
        $event->setMessage($message);

        $serializer = new PayloadSerializer($options);

        return $serializer->serialize($event);
    }

    private function hasLogMessage(string $message): bool
    {
        foreach (StubLogger::$logs as $log) {
            if ($log['message'] === $message) {
                return true;
            }
        }

        return false;
    }
}
