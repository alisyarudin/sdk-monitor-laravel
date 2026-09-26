<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use Jasnita\Monitor\Laravel\Facade;
use Jasnita\Monitor\Laravel\ServiceProvider;
use Jasnita\Monitor\Sdk\State\HubInterface;

class ServiceProviderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('jasnita.dsn', 'https://publickey@jasnita.dev/123');
        $app['config']->set('jasnita.error_types', E_ALL ^ E_DEPRECATED ^ E_USER_DEPRECATED);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Jasnita' => Facade::class,
        ];
    }

    public function testIsBound(): void
    {
        $this->assertTrue(app()->bound('jasnita'));
        $this->assertSame(app('jasnita'), Facade::getFacadeRoot());
        $this->assertInstanceOf(HubInterface::class, app('jasnita'));
    }

    /**
     * @depends testIsBound
     */
    public function testEnvironment(): void
    {
        $this->assertEquals('testing', app('jasnita')->getClient()->getOptions()->getEnvironment());
    }

    /**
     * @depends testIsBound
     */
    public function testDsnWasSetFromConfig(): void
    {
        /** @var \Jasnita\Monitor\Sdk\Options $options */
        $options = app('jasnita')->getClient()->getOptions();

        $this->assertEquals('https://jasnita.dev', $options->getDsn()->getScheme() . '://' . $options->getDsn()->getHost());
        $this->assertEquals(123, $options->getDsn()->getProjectId());
        $this->assertEquals('publickey', $options->getDsn()->getPublicKey());
    }

    /**
     * @depends testIsBound
     */
    public function testErrorTypesWasSetFromConfig(): void
    {
        $this->assertEquals(
            E_ALL ^ E_DEPRECATED ^ E_USER_DEPRECATED,
            app('jasnita')->getClient()->getOptions()->getErrorTypes()
        );
    }

    /**
     * @depends testIsBound
     */
    public function testArtisanCommandsAreRegistered(): void
    {
        $this->assertArrayHasKey('jasnita:test', Artisan::all());
        $this->assertArrayHasKey('jasnita:publish', Artisan::all());
    }
}
