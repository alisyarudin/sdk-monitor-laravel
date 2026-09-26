<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Transport;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Response;
use Jasnita\Monitor\Sdk\ResponseStatus;

/**
 * This transport fakes the sending of events by just ignoring them.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class NullTransport implements TransportInterface
{
    /**
     * {@inheritdoc}
     */
    public function send(Event $event): PromiseInterface
    {
        return new FulfilledPromise(new Response(ResponseStatus::skipped(), $event));
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
