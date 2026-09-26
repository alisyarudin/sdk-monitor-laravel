<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Orchestra\Testbench\TestCase;
use Jasnita\Monitor\Laravel\Facade;
use Jasnita\Monitor\Laravel\ServiceProvider;
use Jasnita\Monitor\Sdk\State\HubInterface;

class ServiceProviderWithCustomAliasTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('custom-jasnita.dsn', 'http://publickey@jasnita.dev/123');
        $app['config']->set('custom-jasnita.error_types', E_ALL ^ E_DEPRECATED ^ E_USER_DEPRECATED);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CustomJasnitaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'CustomJasnita' => CustomJasnitaFacade::class,
        ];
    }

    public function testIsBound(): void
    {
        $this->assertTrue(app()->bound('custom-jasnita'));
        $this->assertInstanceOf(HubInterface::class, app('custom-jasnita'));
        $this->assertSame(app('custom-jasnita'), CustomJasnitaFacade::getFacadeRoot());
    }

    /**
     * @depends testIsBound
     */
    public function testEnvironment(): void
    {
        $this->assertEquals('testing', app('custom-jasnita')->getClient()->getOptions()->getEnvironment());
    }

    /**
     * @depends testIsBound
     */
    public function testDsnWasSetFromConfig(): void
    {
        /** @var \Jasnita\Monitor\Sdk\Options $options */
        $options = app('custom-jasnita')->getClient()->getOptions();

        $this->assertEquals('http://jasnita.dev', $options->getDsn()->getScheme() . '://' . $options->getDsn()->getHost());
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
            app('custom-jasnita')->getClient()->getOptions()->getErrorTypes()
        );
    }
}

class CustomJasnitaServiceProvider extends ServiceProvider
{
    public static $abstract = 'custom-jasnita';
}

class CustomJasnitaFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'custom-jasnita';
    }
}
