<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Features;

use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Application;
use Illuminate\Log\Context\Repository as ContextRepository;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use ReflectionClass;
use RuntimeException;
use Jasnita\Monitor\Sdk\CheckInStatus;
use Jasnita\Monitor\Laravel\Features\ConsoleSchedulingIntegration;
use Jasnita\Monitor\Laravel\Tests\TestCase;
use Jasnita\Monitor\Sdk\MonitorSchedule;

class ConsoleSchedulingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70400 && version_compare(Application::VERSION, '8', '>=') && version_compare(Application::VERSION, '9', '<')) {
            $this->markTestSkipped('These tests don\'t run on Laravel 8 with PHP 7.3.');
        }

        parent::setUp();
    }

    public function testScheduleMacro(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->call(function () {})
            ->jasnitaMonitor('test-monitor');

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Jasnita:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertJasnitaCheckInCount(2);

        $finishCheckInEvent = $this->getLastJasnitaEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertEquals('test-monitor', $finishCheckInEvent->getCheckIn()->getMonitorSlug());
    }

    /**
     * When a timezone was defined on a command this would fail with:
     * Jasnita\Monitor\Sdk\MonitorConfig::__construct(): Argument #4 ($timezone) must be of type ?string, DateTimeZone given
     * This test ensures that the timezone is properly converted to a string as expected.
     */
    public function testScheduleMacroWithTimeZone(): void
    {
        $expectedTimezone = 'UTC';

        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->call(function () {})
            ->timezone(new DateTimeZone($expectedTimezone))
            ->jasnitaMonitor('test-timezone-monitor');

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Jasnita:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertJasnitaCheckInCount(2);

        $finishCheckInEvent = $this->getLastJasnitaEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertEquals($expectedTimezone, $finishCheckInEvent->getCheckIn()->getMonitorConfig()->getTimezone());
    }

    public function testScheduleMacroWithScheduleOverride(): void
    {
        $expectedSchedule = '*/5 9-17 * * *';

        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->call(function () {})
            ->everyFiveMinutes()
            ->between('09:00', '17:59')
            ->jasnitaMonitor('test-monitor', null, null, true, null, null, $expectedSchedule);

        $this->assertEquals('*/5 * * * *', $scheduledEvent->getExpression());

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Jasnita:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertJasnitaCheckInCount(2);

        $finishCheckInEvent = $this->getLastJasnitaEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertNotNull($finishCheckInEvent->getCheckIn()->getMonitorConfig());
        $this->assertEquals(MonitorSchedule::TYPE_CRONTAB, $finishCheckInEvent->getCheckIn()->getMonitorConfig()->getSchedule()->getType());
        $this->assertEquals($expectedSchedule, $finishCheckInEvent->getCheckIn()->getMonitorConfig()->getSchedule()->getValue());
    }

    public function testScheduleMacroAutomaticSlugForCommand(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()->command('inspire')->jasnitaMonitor();

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Jasnita:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertJasnitaCheckInCount(2);

        $finishCheckInEvent = $this->getLastJasnitaEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertEquals('scheduled_artisan-inspire', $finishCheckInEvent->getCheckIn()->getMonitorSlug());
    }

    public function testScheduleMacroAutomaticSlugForJob(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()->job(ScheduledQueuedJob::class)->jasnitaMonitor();

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Jasnita:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertJasnitaCheckInCount(2);

        $finishCheckInEvent = $this->getLastJasnitaEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        // Scheduled is duplicated here because of the class name of the queued job, this is not a bug just unfortunate naming for the test class
        $this->assertEquals('scheduled_scheduledqueuedjob-features-tests-laravel-monitor-jasnita', $finishCheckInEvent->getCheckIn()->getMonitorSlug());
    }

    public function testScheduleMacroWithoutSlugCommandOrDescriptionOrName(): void
    {
        $this->expectException(RuntimeException::class);

        $this->getScheduler()->call(function () {})->jasnitaMonitor();
    }

    /** @define-env envWithoutDsnSet */
    #[DefineEnvironment('envWithoutDsnSet')]
    public function testScheduleMacroWithoutDsnSet(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()->call(function () {})->jasnitaMonitor('test-monitor');

        $scheduledEvent->run($this->app);

        $this->assertJasnitaCheckInCount(0);
    }

    public function testScheduleMacroIsRegistered(): void
    {
        if (!method_exists(Event::class, 'flushMacros')) {
            $this->markTestSkipped('Macroable::flushMacros() is not available in this Laravel version.');
        }

        Event::flushMacros();

        $this->refreshApplication();

        $this->assertTrue(Event::hasMacro('jasnitaMonitor'));
    }

    /** @define-env envWithoutDsnSet */
    #[DefineEnvironment('envWithoutDsnSet')]
    public function testScheduleMacroIsRegisteredWithoutDsnSet(): void
    {
        if (!method_exists(Event::class, 'flushMacros')) {
            $this->markTestSkipped('Macroable::flushMacros() is not available in this Laravel version.');
        }

        Event::flushMacros();

        $this->refreshApplication();

        $this->assertTrue(Event::hasMacro('jasnitaMonitor'));
    }

    /** @define-env envSamplingAllTransactions */
    #[DefineEnvironment('envSamplingAllTransactions')]
    public function testScheduledClosureCreatesTransaction(): void
    {
        $this->getScheduler()->call(function () {})->everyMinute();

        $this->artisan('schedule:run');

        $this->assertJasnitaTransactionCount(1);

        $transaction = $this->getLastJasnitaEvent();

        $this->assertEquals('Closure', $transaction->getTransaction());
    }

    /** @define-env envSamplingAllTransactions */
    #[DefineEnvironment('envSamplingAllTransactions')]
    public function testScheduledJobCreatesTransaction(): void
    {
        $this->getScheduler()->job(ScheduledQueuedJob::class)->everyMinute();

        $this->artisan('schedule:run');

        $this->assertJasnitaTransactionCount(1);

        $transaction = $this->getLastJasnitaEvent();

        $this->assertEquals(ScheduledQueuedJob::class, $transaction->getTransaction());
    }

    public function testBackgroundScheduledTaskUsesContextForCheckInId(): void
    {
        if (!class_exists(ContextRepository::class) || version_compare(app()->version(), '12.40.2', '<')) {
            $this->markTestSkipped('Laravel Context with hidden context passing to child processes requires Laravel 12.40.2+.');
        }

        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->command('inspire')
            ->runInBackground()
            ->jasnitaMonitor('test-background-monitor');

        // Get the integration instance
        $integration = $this->app->make(ConsoleSchedulingIntegration::class);

        // Use reflection to access private properties
        $integrationReflection = new ReflectionClass($integration);
        $checkInStoreProperty = $integrationReflection->getProperty('checkInStore');

        // Use reflection to access protected callback arrays on the Event
        $eventReflection = new ReflectionClass($scheduledEvent);
        $beforeCallbacksProperty = $eventReflection->getProperty('beforeCallbacks');
        $afterCallbacksProperty = $eventReflection->getProperty('afterCallbacks');

        // Run the before callbacks (this triggers startCheckIn)
        foreach ($beforeCallbacksProperty->getValue($scheduledEvent) as $callback) {
            $this->app->call($callback->bindTo($scheduledEvent));
        }

        // We should have 1 check-in event (the start)
        $this->assertJasnitaCheckInCount(1);

        $startCheckInEvent = $this->getLastJasnitaEvent();
        $startCheckInId = $startCheckInEvent->getCheckIn()->getId();

        // Clear the in-memory store to simulate being in a different process
        // This is what happens when a background task runs in a separate process
        $checkInStoreProperty->setValue($integration, []);

        // Set exitCode to 0 to simulate successful execution
        // (onSuccess callbacks check exitCode === 0)
        $scheduledEvent->exitCode = 0;

        // Run the success callbacks (this triggers finishCheckIn)
        // In a real background task, this would run in a separate process
        // but it should be able to retrieve the check-in ID from Context
        foreach ($afterCallbacksProperty->getValue($scheduledEvent) as $callback) {
            $this->app->call($callback->bindTo($scheduledEvent));
        }

        // We should now have 2 check-in events (start + finish)
        $this->assertJasnitaCheckInCount(2);

        $finishCheckInEvent = $this->getLastJasnitaEvent();
        $finishCheckInId = $finishCheckInEvent->getCheckIn()->getId();

        // The finish check-in should have the same ID as the start check-in
        // This verifies that the ID was correctly retrieved from Context
        $this->assertEquals($startCheckInId, $finishCheckInId);
        $this->assertEquals(CheckInStatus::ok(), $finishCheckInEvent->getCheckIn()->getStatus());
    }

    public function testBackgroundScheduledTaskOverlappingExecutionsHaveDistinctCheckInIds(): void
    {
        if (!class_exists(ContextRepository::class) || version_compare(app()->version(), '12.40.2', '<')) {
            $this->markTestSkipped('Laravel Context with hidden context passing to child processes requires Laravel 12.40.2+.');
        }

        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->command('inspire')
            ->runInBackground()
            ->jasnitaMonitor('test-overlapping-monitor');

        // Get the integration and context instances
        $integration = $this->app->make(ConsoleSchedulingIntegration::class);
        $context = $this->app->make(ContextRepository::class);

        // Use reflection to access private properties on integration
        $integrationReflection = new ReflectionClass($integration);
        $checkInStoreProperty = $integrationReflection->getProperty('checkInStore');

        // Use reflection to access protected callback arrays on the Event
        $eventReflection = new ReflectionClass($scheduledEvent);
        $beforeCallbacksProperty = $eventReflection->getProperty('beforeCallbacks');
        $afterCallbacksProperty = $eventReflection->getProperty('afterCallbacks');

        // Simulate Task A starting
        foreach ($beforeCallbacksProperty->getValue($scheduledEvent) as $callback) {
            $this->app->call($callback->bindTo($scheduledEvent));
        }

        $this->assertJasnitaCheckInCount(1);
        $taskAStartEvent = $this->getLastJasnitaEvent();
        $taskACheckInId = $taskAStartEvent->getCheckIn()->getId();

        // Capture Task A's context (simulates the context being passed to the spawned process)
        $taskAContext = $context->allHidden();

        // Clear in-memory store (simulates scheduler continuing after spawning Task A)
        $checkInStoreProperty->setValue($integration, []);

        // Simulate Task B starting (overlapping execution)
        foreach ($beforeCallbacksProperty->getValue($scheduledEvent) as $callback) {
            $this->app->call($callback->bindTo($scheduledEvent));
        }

        $this->assertJasnitaCheckInCount(2);
        $taskBStartEvent = $this->getLastJasnitaEvent();
        $taskBCheckInId = $taskBStartEvent->getCheckIn()->getId();

        // Task A and Task B should have different check-in IDs
        $this->assertNotEquals($taskACheckInId, $taskBCheckInId);

        // Capture Task B's context
        $taskBContext = $context->allHidden();

        // Clear in-memory store (prepare for finish simulations)
        $checkInStoreProperty->setValue($integration, []);

        // Set exitCode to 0 to simulate successful execution
        $scheduledEvent->exitCode = 0;

        // Simulate Task A finishing (restore Task A's context)
        $context->flush();
        $context->addHidden($taskAContext);
        foreach ($afterCallbacksProperty->getValue($scheduledEvent) as $callback) {
            $this->app->call($callback->bindTo($scheduledEvent));
        }

        $this->assertJasnitaCheckInCount(3);
        $taskAFinishEvent = $this->getLastJasnitaEvent();

        // Task A's finish should use Task A's check-in ID
        $this->assertEquals($taskACheckInId, $taskAFinishEvent->getCheckIn()->getId());

        // Clear in-memory store again
        $checkInStoreProperty->setValue($integration, []);

        // Simulate Task B finishing (restore Task B's context)
        $context->flush();
        $context->addHidden($taskBContext);
        foreach ($afterCallbacksProperty->getValue($scheduledEvent) as $callback) {
            $this->app->call($callback->bindTo($scheduledEvent));
        }

        $this->assertJasnitaCheckInCount(4);
        $taskBFinishEvent = $this->getLastJasnitaEvent();

        // Task B's finish should use Task B's check-in ID
        $this->assertEquals($taskBCheckInId, $taskBFinishEvent->getCheckIn()->getId());
    }

    private function getScheduler(): Schedule
    {
        return $this->app->make(Schedule::class);
    }
}

class ScheduledQueuedJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
    }
}
