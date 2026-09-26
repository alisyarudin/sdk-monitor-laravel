<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Transport;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Jasnita\Monitor\Sdk\HttpClient\HttpClientFactoryInterface;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Transport\DefaultTransportFactory;
use Jasnita\Monitor\Sdk\Transport\HttpTransport;
use Jasnita\Monitor\Sdk\Transport\NullTransport;

final class DefaultTransportFactoryTest extends TestCase
{
    public function testCreateReturnsNullTransportWhenDsnOptionIsNotConfigured(): void
    {
        $factory = new DefaultTransportFactory(
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(HttpClientFactoryInterface::class)
        );

        $this->assertInstanceOf(NullTransport::class, $factory->create(new Options()));
    }

    public function testCreateReturnsHttpTransportWhenDsnOptionIsConfigured(): void
    {
        $options = new Options(['dsn' => 'http://public@example.com/jasnita/1']);

        /** @var HttpClientFactoryInterface&MockObject $httpClientFactory */
        $httpClientFactory = $this->createMock(HttpClientFactoryInterface::class);
        $httpClientFactory->expects($this->once())
            ->method('create')
            ->with($options)
            ->willReturn($this->createMock(HttpAsyncClientInterface::class));

        $factory = new DefaultTransportFactory(
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $httpClientFactory
        );

        $this->assertInstanceOf(HttpTransport::class, $factory->create($options));
    }
}
