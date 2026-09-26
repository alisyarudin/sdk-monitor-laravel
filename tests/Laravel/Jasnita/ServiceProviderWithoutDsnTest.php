<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Jasnita\Monitor\Laravel\ServiceProvider;
use Illuminate\Routing\Events\RouteMatched;

class ServiceProviderWithoutDsnTest extends \Orchestra\Testbench\TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('jasnita.dsn', null);
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    public function testIsBound()
    {
        $this->assertTrue(app()->bound('jasnita'));
    }

    /**
     * @depends testIsBound
     */
    public function testDsnIsNotSet()
    {
        $this->assertNull(app('jasnita')->getClient()->getOptions()->getDsn());
    }

    /**
     * @depends testIsBound
     */
    public function testDidNotRegisterEvents()
    {
        $this->assertEquals(false, app('events')->hasListeners('router.matched') && app('events')->hasListeners(RouteMatched::class));
    }
}
