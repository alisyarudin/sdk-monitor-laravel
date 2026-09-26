<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use ReflectionMethod;
use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Sdk\State\Scope;
use ReflectionProperty;
use Jasnita\Monitor\Laravel\Tracing;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as LaravelTestCase;

abstract class JasnitaLaravelTestCase extends LaravelTestCase
{
    protected $setupConfig = [
        // Set config here before refreshing the app to set it in the container before Jasnita is loaded
        // or use the `$this->resetApplicationWithConfig([ /* config */ ]);` helper method
    ];

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('jasnita.dsn', 'http://publickey:secretkey@jasnita.dev/123');

        foreach ($this->setupConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
            Tracing\ServiceProvider::class,
        ];
    }

    protected function resetApplicationWithConfig(array $config)
    {
        $this->setupConfig = $config;

        $this->refreshApplication();
    }

    protected function dispatchLaravelEvent($event, array $payload = [])
    {
        $dispatcher = $this->app['events'];

        // Laravel 5.4+ uses the dispatch method to dispatch/fire events
        return method_exists($dispatcher, 'dispatch')
            ? $dispatcher->dispatch($event, $payload)
            : $dispatcher->fire($event, $payload);
    }

    protected function getHubFromContainer(): HubInterface
    {
        return $this->app->make('jasnita');
    }

    protected function getCurrentScope(): Scope
    {
        $hub = $this->getHubFromContainer();

        $method = new ReflectionMethod($hub, 'getScope');
        $method->setAccessible(true);

        return $method->invoke($hub);
    }

    protected function getCurrentBreadcrumbs(): array
    {
        $scope = $this->getCurrentScope();

        $property = new ReflectionProperty($scope, 'breadcrumbs');
        $property->setAccessible(true);

        return $property->getValue($scope);
    }

    protected function getLastBreadcrumb(): ?Breadcrumb
    {
        $breadcrumbs = $this->getCurrentBreadcrumbs();

        if (empty($breadcrumbs)) {
            return null;
        }

        return end($breadcrumbs);
    }
}
