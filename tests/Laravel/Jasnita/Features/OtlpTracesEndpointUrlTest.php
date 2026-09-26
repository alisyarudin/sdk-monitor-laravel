<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Laravel\Tests\Features;

use Jasnita\Monitor\Sdk\Integration\OTLPIntegration;
use Jasnita\Monitor\Laravel\Tests\TestCase;

use function Jasnita\Monitor\Sdk\getOtlpTracesEndpointUrl;

class OtlpTracesEndpointUrlTest extends TestCase
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('jasnita.integrations', [
            OTLPIntegration::class,
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/jasnita-test/otlp-traces-endpoint-url', function () {
            return response()->json([
                'url' => getOtlpTracesEndpointUrl(),
            ]);
        });
    }

    public function testReturnsDsnDerivedOtlpTracesEndpointUrlDuringHttpRequest(): void
    {
        $dsn = $this->getJasnitaClientFromContainer()->getOptions()->getDsn();
        $this->assertNotNull($dsn);
        $this->assertNotNull($this->getJasnitaClientFromContainer()->getIntegration(OTLPIntegration::class));

        $response = $this->get('/jasnita-test/otlp-traces-endpoint-url');

        $response->assertOk();
        $response->assertJson([
            'url' => $dsn->getOtlpTracesEndpointUrl(),
        ]);
    }
}
