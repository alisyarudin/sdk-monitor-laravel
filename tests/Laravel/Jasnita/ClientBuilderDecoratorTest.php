<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Jasnita\Monitor\Sdk\ClientBuilder;

class ClientBuilderDecoratorTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->extend(ClientBuilder::class, function (ClientBuilder $clientBuilder) {
            $clientBuilder->getOptions()->setEnvironment('from_service_container');

            return $clientBuilder;
        });
    }

    public function testClientHasEnvironmentSetFromDecorator(): void
    {
        $this->assertEquals(
            'from_service_container',
            $this->getJasnitaClientFromContainer()->getOptions()->getEnvironment()
        );
    }
}
