<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Fixtures\OpenTelemetry;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class StubOtelHttpClient implements ClientInterface
{
    /**
     * @var RequestInterface[]
     */
    public static $requests = [];

    public static function reset(): void
    {
        self::$requests = [];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        self::$requests[] = $request;

        return new Response(200);
    }
}
