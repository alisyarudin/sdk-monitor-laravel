<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\HttpClient;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Mock\Client as HttpMockClient;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\HttpClient\HttpClientFactory;
use Jasnita\Monitor\Sdk\Options;

final class HttpClientFactoryTest extends TestCase
{
    /**
     * @requires extension zlib
     * @dataProvider createDataProvider
     */
    public function testCreate(bool $isCompressionEnabled, string $expectedRequestBody): void
    {
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $mockHttpClient = new HttpMockClient();
        $httpClientFactory = new HttpClientFactory(
            null,
            null,
            $streamFactory,
            $mockHttpClient,
            'jasnita.php.test',
            '1.2.3'
        );

        $httpClient = $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/jasnita/1',
            'default_integrations' => false,
            'enable_compression' => $isCompressionEnabled,
        ]));

        $request = Psr17FactoryDiscovery::findRequestFactory()
            ->createRequest('POST', 'http://example.com/jasnita/foo')
            ->withBody($streamFactory->createStream('foo bar'));

        $httpClient->sendAsyncRequest($request);

        $httpRequest = $mockHttpClient->getLastRequest();

        $this->assertSame('http://example.com/jasnita/foo', (string) $httpRequest->getUri());
        $this->assertSame('jasnita.php.test/1.2.3', $httpRequest->getHeaderLine('User-Agent'));
        $this->assertSame('Jasnita jasnita_version=7, jasnita_client=jasnita.php.test/1.2.3, jasnita_key=public', $httpRequest->getHeaderLine('X-Jasnita-Auth'));
        $this->assertSame($expectedRequestBody, (string) $httpRequest->getBody());
    }

    public static function createDataProvider(): \Generator
    {
        yield [
            false,
            'foo bar',
        ];

        yield [
            true,
            gzcompress('foo bar', -1, \ZLIB_ENCODING_GZIP),
        ];
    }

    public function testCreateThrowsIfDsnOptionIsNotConfigured(): void
    {
        $httpClientFactory = new HttpClientFactory(
            null,
            null,
            Psr17FactoryDiscovery::findStreamFactory(),
            null,
            'jasnita.php.test',
            '1.2.3'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create an HTTP client without the Jasnita DSN set in the options.');

        $httpClientFactory->create(new Options(['default_integrations' => false]));
    }

    public function testCreateThrowsIfHttpProxyOptionIsUsedWithCustomHttpClient(): void
    {
        $httpClientFactory = new HttpClientFactory(
            null,
            null,
            Psr17FactoryDiscovery::findStreamFactory(),
            $this->createMock(HttpAsyncClientInterface::class),
            'jasnita.php.test',
            '1.2.3'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "http_proxy" option does not work together with a custom HTTP client.');

        $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/jasnita/1',
            'default_integrations' => false,
            'http_proxy' => 'http://example.com',
        ]));
    }
}
