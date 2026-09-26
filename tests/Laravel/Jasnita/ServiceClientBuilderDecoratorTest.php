<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Jasnita\Monitor\Sdk\ClientBuilderInterface;
use Jasnita\Monitor\Laravel\ServiceProvider;

class ServiceClientBuilderDecoratorTest extends \Orchestra\Testbench\TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('jasnita.dsn', 'http://publickey:secretkey@jasnita.dev/123');

        $app->extend(ClientBuilderInterface::class, function (ClientBuilderInterface $clientBuilder) {
            $clientBuilder->getOptions()->setEnvironment('from_service_container');

            return $clientBuilder;
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    public function testClientHasCustomSerializer()
    {
        /** @var \Jasnita\Monitor\Sdk\Options $options */
        $options = $this->app->make('jasnita')->getClient()->getOptions();

        $this->assertEquals('from_service_container', $options->getEnvironment());
    }
}
