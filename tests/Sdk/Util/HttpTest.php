<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Util;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Dsn;
use Jasnita\Monitor\Sdk\Util\Http;

final class HttpTest extends TestCase
{
    public function testGetJasnitaAuthHeader(): void
    {
        $dsn = Dsn::createFromString('http://public@example.com/1');

        $this->assertSame(
            'Jasnita jasnita_version=7, jasnita_client=jasnita.sdk.identifier/1.2.3, jasnita_key=public',
            Http::getJasnitaAuthHeader($dsn, 'jasnita.sdk.identifier', '1.2.3')
        );
    }

    /**
     * @dataProvider getRequestHeadersDataProvider
     */
    public function testGetRequestHeaders(Dsn $dsn, string $sdkIdentifier, string $sdkVersion, array $expectedResult): void
    {
        $this->assertSame($expectedResult, Http::getRequestHeaders($dsn, $sdkIdentifier, $sdkVersion));
    }

    public static function getRequestHeadersDataProvider(): \Generator
    {
        yield [
            Dsn::createFromString('http://public@example.com/1'),
            'jasnita.sdk.identifier',
            '1.2.3',
            [
                'Content-Type: application/x-jasnita-envelope',
                'X-Jasnita-Auth: ' . Http::getJasnitaAuthHeader(
                    Dsn::createFromString('http://public@example.com/1'),
                    'jasnita.sdk.identifier',
                    '1.2.3'
                ),
            ],
        ];
    }

    /**
     * @dataProvider parseResponseHeadersDataProvider
     */
    public function testParseResponseHeaders(string $headerline, $expectedResult): void
    {
        $responseHeaders = [];

        Http::parseResponseHeaders($headerline, $responseHeaders);

        $this->assertSame($expectedResult, $responseHeaders);
    }

    public static function parseResponseHeadersDataProvider(): \Generator
    {
        yield [
            'Content-Type: application/json',
            [
                'Content-Type' => [
                    'application/json',
                ],
            ],
        ];

        yield [
            'X-Jasnita-Rate-Limits: 60:transaction:key,2700:default;error;security:organization',
            [
                'X-Jasnita-Rate-Limits' => [
                    '60:transaction:key,2700:default;error;security:organization',
                ],
            ],
        ];

        yield [
            'Invalid',
            [],
        ];
    }
}
