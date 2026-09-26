<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Features;

use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Jasnita\Monitor\Laravel\Tests\TestCase;

class RouteIntegrationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->group(['prefix' => 'jasnita'], function (Router $router) {
            $router->get('/ok', function () {
                return 'ok';
            });

            $router->get('/abort/{code}', function (int $code) {
                abort($code);
            });
        });
    }

    /** @define-env envSamplingAllTransactions */
    #[DefineEnvironment('envSamplingAllTransactions')]
    public function testTransactionIsRecordedForRoute(): void
    {
        $this->get('/jasnita/ok')->assertOk();

        $this->assertJasnitaTransactionCount(1);
    }

    /** @define-env envSamplingAllTransactions */
    #[DefineEnvironment('envSamplingAllTransactions')]
    public function testTransactionIsRecordedForNotFound(): void
    {
        $this->get('/jasnita/abort/404')->assertNotFound();

        $this->assertJasnitaTransactionCount(1);
    }

    /** @define-env envSamplingAllTransactions */
    #[DefineEnvironment('envSamplingAllTransactions')]
    public function testTransactionIsDroppedForUndefinedRoute(): void
    {
        $this->get('/jasnita/non-existent-route')->assertNotFound();

        $this->assertJasnitaTransactionCount(0);
    }
}
