<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Illuminate\Support\Facades\Artisan;
use Jasnita\Monitor\Laravel\ServiceProvider;
use Illuminate\Routing\Events\RouteMatched;

class ServiceProviderWithoutDsnTest extends \Orchestra\Testbench\TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('jasnita.dsn', null);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    public function testIsBound(): void
    {
        $this->assertTrue(app()->bound('jasnita'));
    }

    /**
     * @depends testIsBound
     */
    public function testDsnIsNotSet(): void
    {
        $this->assertNull(app('jasnita')->getClient()->getOptions()->getDsn());
    }

    /**
     * @depends testIsBound
     */
    public function testDidNotRegisterEvents(): void
    {
        $this->assertEquals(false, app('events')->hasListeners(RouteMatched::class));
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
