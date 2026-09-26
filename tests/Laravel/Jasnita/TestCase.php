<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Jasnita\Monitor\Sdk\Tracing\Transaction;
use Illuminate\Config\Repository;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use ReflectionMethod;
use Jasnita\Monitor\Sdk\Breadcrumb;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\EventType;
use Jasnita\Monitor\Sdk\State\Scope;
use ReflectionProperty;
use Jasnita\Monitor\Laravel\Tracing;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as LaravelTestCase;

abstract class TestCase extends LaravelTestCase
{
    private static $hasSetupGlobalEventProcessor = false;

    protected $loadEnvironmentVariables = false;

    protected $setupConfig = [
        // Set config here before refreshing the app to set it in the container before Jasnita is loaded
        // or use the `$this->resetApplicationWithConfig([ /* config */ ]);` helper method
    ];

    protected $defaultSetupConfig = [];

    /** @var array<int, array{0: Event, 1: EventHint|null}> */
    protected static $lastJasnitaEvents = [];

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        self::$lastJasnitaEvents = [];

        $this->setupGlobalEventProcessor();

        tap($app['config'], function (Repository $config) {
            // This key has no meaning, it's just a randomly generated one but it's required for the app to boot properly
            $config->set('app.key', 'base64:JfXL2QpYC1+szaw+CdT6SHXG8zjdTkKM/ctPWoTWbXU=');

            $config->set('jasnita.before_send', static function (Event $event, ?EventHint $hint) {
                self::$lastJasnitaEvents[] = [$event, $hint];

                return null;
            });

            $config->set('jasnita.before_send_transaction', static function (Event $event, ?EventHint $hint) {
                self::$lastJasnitaEvents[] = [$event, $hint];

                return null;
            });

            if ($config->get('jasnita_test.override_dsn') !== true) {
                $config->set('jasnita.dsn', 'https://publickey@jasnita.dev/123');
            }

            foreach ($this->defaultSetupConfig as $key => $value) {
                $config->set($key, $value);
            }

            foreach ($this->setupConfig as $key => $value) {
                $config->set($key, $value);
            }
        });

        $app->extend(ExceptionHandler::class, function (ExceptionHandler $handler) {
            return new TestCaseExceptionHandler($handler);
        });
    }

    /** @param Application $app */
    protected function envWithoutDsnSet($app): void
    {
        $app['config']->set('jasnita.dsn', null);
        $app['config']->set('jasnita_test.override_dsn', true);
    }

    /** @param Application $app */
    protected function envSamplingAllTransactions($app): void
    {
        $app['config']->set('jasnita.traces_sample_rate', 1.0);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
            Tracing\ServiceProvider::class,
        ];
    }

    protected function resetApplicationWithConfig(array $config): void
    {
        $this->setupConfig = $config;

        $this->refreshApplication();
    }

    protected function dispatchLaravelEvent($event, array $payload = []): void
    {
        $this->app['events']->dispatch($event, $payload);
    }

    protected function getJasnitaHubFromContainer(): HubInterface
    {
        return $this->app->make('jasnita');
    }

    protected function getJasnitaClientFromContainer(): ClientInterface
    {
        return $this->getJasnitaHubFromContainer()->getClient();
    }

    protected function getCurrentJasnitaScope(): Scope
    {
        $hub = $this->getJasnitaHubFromContainer();

        $method = new ReflectionMethod($hub, 'getScope');
        if (\PHP_VERSION_ID < 80100)
        {
            // This method is no-op starting from PHP 8.1; see also https://wiki.php.net/rfc/deprecations_php_8_5#deprecate_reflectionsetaccessible
            $method->setAccessible(true);
        }

        return $method->invoke($hub);
    }

    /** @return array<array-key, \Jasnita\Monitor\Sdk\Breadcrumb> */
    protected function getCurrentJasnitaBreadcrumbs(): array
    {
        $scope = $this->getCurrentJasnitaScope();

        $property = new ReflectionProperty($scope, 'breadcrumbs');
        if (\PHP_VERSION_ID < 80100)
        {
            // This method is no-op starting from PHP 8.1; see also https://wiki.php.net/rfc/deprecations_php_8_5#deprecate_reflectionsetaccessible
            $property->setAccessible(true);
        }

        return $property->getValue($scope);
    }

    protected function getLastJasnitaBreadcrumb(): ?Breadcrumb
    {
        $breadcrumbs = $this->getCurrentJasnitaBreadcrumbs();

        if (empty($breadcrumbs)) {
            return null;
        }

        return end($breadcrumbs);
    }

    protected function getLastJasnitaEvent(): ?Event
    {
        if (empty(self::$lastJasnitaEvents)) {
            return null;
        }

        return end(self::$lastJasnitaEvents)[0];
    }

    protected function getLastEventJasnitaHint(): ?EventHint
    {
        if (empty(self::$lastJasnitaEvents)) {
            return null;
        }

        return end(self::$lastJasnitaEvents)[1];
    }

    /** @return array<int, array{0: Event, 1: EventHint|null}> */
    protected function getCapturedJasnitaEvents(): array
    {
        return self::$lastJasnitaEvents;
    }

    protected function assertJasnitaEventCount(int $count): void
    {
        $this->assertCount($count, array_filter(self::$lastJasnitaEvents, static function (array $event) {
            return $event[0]->getType() === EventType::event();
        }));
    }

    protected function assertJasnitaCheckInCount(int $count): void
    {
        $this->assertCount($count, array_filter(self::$lastJasnitaEvents, static function (array $event) {
            return $event[0]->getType() === EventType::checkIn();
        }));
    }

    protected function assertJasnitaTransactionCount(int $count): void
    {
        $this->assertCount($count, array_filter(self::$lastJasnitaEvents, static function (array $event) {
            return $event[0]->getType() === EventType::transaction();
        }));
    }

    protected function startTransaction(): Transaction
    {
        $hub = $this->getJasnitaHubFromContainer();

        $transaction = $hub->startTransaction(new TransactionContext);
        $transaction->initSpanRecorder();
        $transaction->setSampled(true);

        $this->getCurrentJasnitaScope()->setSpan($transaction);

        return $transaction;
    }

    private function setupGlobalEventProcessor(): void
    {
        if (self::$hasSetupGlobalEventProcessor) {
            return;
        }

        Scope::addGlobalEventProcessor(static function (Event $event, ?EventHint $hint) {
            // Regular events and transactions are handled by the `before_send` and `before_send_transaction` callbacks
            if (in_array($event->getType(), [EventType::event(), EventType::transaction()], true)) {
                return $event;
            }

            self::$lastJasnitaEvents[] = [$event, $hint];

            return null;
        });

        self::$hasSetupGlobalEventProcessor = true;
    }
}
