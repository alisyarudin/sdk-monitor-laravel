<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Transport;

use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\ResponseStatus;
use Jasnita\Monitor\Sdk\Transport\NullTransport;

final class NullTransportTest extends TestCase
{
    /**
     * @var NullTransport
     */
    private $transport;

    protected function setUp(): void
    {
        $this->transport = new NullTransport();
    }

    public function testSend(): void
    {
        $event = Event::createEvent();

        $promise = $this->transport->send($event);
        $promiseResult = $promise->wait();

        $this->assertSame(PromiseInterface::FULFILLED, $promise->getState());
        $this->assertSame(ResponseStatus::skipped(), $promiseResult->getStatus());
        $this->assertSame($event, $promiseResult->getEvent());
    }

    public function testClose(): void
    {
        $promise = $this->transport->close();

        $this->assertSame(PromiseInterface::FULFILLED, $promise->getState());
        $this->assertTrue($promise->wait());
    }
}
