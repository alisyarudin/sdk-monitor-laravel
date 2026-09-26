<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\HttpClient\Authentication;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Client;
use Jasnita\Monitor\Sdk\HttpClient\Authentication\JasnitaAuthentication;
use Jasnita\Monitor\Sdk\Options;

final class JasnitaAuthenticationTest extends TestCase
{
    public function testAuthenticateWithSecretKey(): void
    {
        $configuration = new Options(['dsn' => 'http://public:secret@example.com/jasnita/1']);
        $authentication = new JasnitaAuthentication($configuration, 'jasnita.php.test', '1.2.3');
        $request = new Request('POST', 'http://www.example.com', []);
        $expectedHeader = sprintf(
            'Jasnita jasnita_version=%s, jasnita_client=%s, jasnita_key=public, jasnita_secret=secret',
            Client::PROTOCOL_VERSION,
            'jasnita.php.test/1.2.3'
        );

        $this->assertFalse($request->hasHeader('X-Jasnita-Auth'));

        $request = $authentication->authenticate($request);

        $this->assertTrue($request->hasHeader('X-Jasnita-Auth'));
        $this->assertSame($expectedHeader, $request->getHeaderLine('X-Jasnita-Auth'));
    }

    public function testAuthenticateWithoutSecretKey(): void
    {
        $configuration = new Options(['dsn' => 'http://public@example.com/jasnita/1']);
        $authentication = new JasnitaAuthentication($configuration, 'jasnita.php.test', '1.2.3');
        $request = new Request('POST', 'http://www.example.com', []);
        $expectedHeader = sprintf(
            'Jasnita jasnita_version=%s, jasnita_client=%s, jasnita_key=public',
            Client::PROTOCOL_VERSION,
            'jasnita.php.test/1.2.3'
        );

        $this->assertFalse($request->hasHeader('X-Jasnita-Auth'));

        $request = $authentication->authenticate($request);

        $this->assertTrue($request->hasHeader('X-Jasnita-Auth'));
        $this->assertSame($expectedHeader, $request->getHeaderLine('X-Jasnita-Auth'));
    }

    public function testAuthenticateWithoutDsnOptionSet(): void
    {
        $authentication = new JasnitaAuthentication(new Options(), 'jasnita.php.test', '1.2.3');
        $request = new Request('POST', 'http://www.example.com', []);
        $request = $authentication->authenticate($request);

        $this->assertFalse($request->hasHeader('X-Jasnita-Auth'));
    }
}
