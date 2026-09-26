<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Integration;

use Exception;
use Illuminate\Support\Facades\Context;
use Jasnita\Monitor\Sdk\EventType;
use Jasnita\Monitor\Laravel\Integration\LaravelContextIntegration;
use Jasnita\Monitor\Laravel\Tests\TestCase;
use function Jasnita\Monitor\Sdk\captureException;

class LaravelContextIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Laravel introduced contexts in version 11.');
        }

        parent::setUp();
    }

    public function testLaravelContextIntegrationIsRegistered(): void
    {
        $integration = $this->getJasnitaHubFromContainer()->getIntegration(LaravelContextIntegration::class);

        $this->assertInstanceOf(LaravelContextIntegration::class, $integration);
    }

    public function testExceptionIsCapturedWithLaravelContext(): void
    {
        $this->setupTestContext();

        captureException(new Exception('Context test'));

        $event = $this->getLastJasnitaEvent();

        $this->assertNotNull($event);
        $this->assertEquals($event->getType(), EventType::event());
        $this->assertContextIsCaptured($event->getContexts());
    }

    public function testExceptionIsCapturedWithoutLaravelContextIfEmpty(): void
    {
        captureException(new Exception('Context test'));

        $event = $this->getLastJasnitaEvent();

        $this->assertNotNull($event);
        $this->assertEquals($event->getType(), EventType::event());
        $this->assertArrayNotHasKey('laravel', $event->getContexts());
    }

    public function testExceptionIsCapturedWithoutLaravelContextIfOnlyHidden(): void
    {
        Context::addHidden('hidden', 'value');

        captureException(new Exception('Context test'));

        $event = $this->getLastJasnitaEvent();

        $this->assertNotNull($event);
        $this->assertEquals($event->getType(), EventType::event());
        $this->assertArrayNotHasKey('laravel', $event->getContexts());
    }

    public function testTransactionIsCapturedWithLaravelContext(): void
    {
        $this->setupTestContext();

        $transaction = $this->startTransaction();
        $transaction->setSampled(true);
        $transaction->finish();

        $event = $this->getLastJasnitaEvent();

        $this->assertNotNull($event);
        $this->assertEquals($event->getType(), EventType::transaction());
        $this->assertContextIsCaptured($event->getContexts());
    }

    private function setupTestContext(): void
    {
        Context::flush();
        Context::add('foo', 'bar');
        Context::addHidden('hidden', 'value');
    }

    private function assertContextIsCaptured(array $context): void
    {
        $this->assertArrayHasKey('laravel', $context);
        $this->assertArrayHasKey('foo', $context['laravel']);
        $this->assertArrayNotHasKey('hidden', $context['laravel']);
        $this->assertEquals('bar', $context['laravel']['foo']);
    }
}
