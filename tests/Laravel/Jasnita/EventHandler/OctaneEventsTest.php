<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\EventHandler;

use ReflectionMethod;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Laravel\EventHandler;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Laravel\Tests\TestCase;

class OctaneEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        Integration::setTransaction(null);

        parent::tearDown();
    }

    public function testLongRunningProcessCleanupClearsStaticTransaction(): void
    {
        $handler = new EventHandler($this->app, []);

        Integration::setTransaction('/previous-request');

        $this->invokeHandlerMethod($handler, 'prepareScopeForTaskWithinLongRunningProcess');
        $this->invokeHandlerMethod($handler, 'cleanupScopeForTaskWithinLongRunningProcessWhen', true);

        $event = $this->getCurrentJasnitaScope()->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertNull($event->getTransaction());
    }

    private function invokeHandlerMethod(EventHandler $handler, string $method, ...$arguments)
    {
        $reflectionMethod = new ReflectionMethod($handler, $method);

        if (\PHP_VERSION_ID < 80100) {
            $reflectionMethod->setAccessible(true);
        }

        return $reflectionMethod->invoke($handler, ...$arguments);
    }
}
