<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Jasnita\Monitor\Laravel\Facade;
use Jasnita\Monitor\Laravel\ServiceProvider;
use Jasnita\Monitor\Sdk\State\HubInterface;

class ServiceProviderTest extends \Orchestra\Testbench\TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('jasnita.dsn', 'http://publickey:secretkey@jasnita.dev/123');
        $app['config']->set('jasnita.error_types', E_ALL ^ E_DEPRECATED ^ E_USER_DEPRECATED);
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Jasnita' => Facade::class,
        ];
    }

    public function testIsBound()
    {
        $this->assertTrue(app()->bound('jasnita'));
        $this->assertInstanceOf(HubInterface::class, app('jasnita'));
        $this->assertSame(app('jasnita'), Facade::getFacadeRoot());
    }

    /**
     * @depends testIsBound
     */
    public function testEnvironment()
    {
        $this->assertEquals('testing', app('jasnita')->getClient()->getOptions()->getEnvironment());
    }

    /**
     * @depends testIsBound
     */
    public function testDsnWasSetFromConfig()
    {
        /** @var \Jasnita\Monitor\Sdk\Options $options */
        $options = app('jasnita')->getClient()->getOptions();

        $this->assertEquals('http://jasnita.dev', $options->getDsn()->getScheme() . '://' . $options->getDsn()->getHost());
        $this->assertEquals(123, $options->getDsn()->getProjectId());
        $this->assertEquals('publickey', $options->getDsn()->getPublicKey());
        $this->assertEquals('secretkey', $options->getDsn()->getSecretKey());
    }

    /**
     * @depends testIsBound
     */
    public function testErrorTypesWasSetFromConfig()
    {
        $this->assertEquals(
            E_ALL ^ E_DEPRECATED ^ E_USER_DEPRECATED,
            app('jasnita')->getClient()->getOptions()->getErrorTypes()
        );
    }
}
