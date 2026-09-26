<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Laravel;

use Jasnita\Monitor\Laravel\Tests\TestCase;
use Jasnita\Monitor\Sdk\Logger\DebugFileLogger;
use Jasnita\Monitor\Sdk\State\HubInterface;

class LaravelContainerConfigOptionsTest extends TestCase
{
    public function testOrgIdIsNullByDefault(): void
    {
        $orgId = app(HubInterface::class)->getClient()->getOptions()->getOrgId();

        $this->assertNull($orgId);
    }

    public function testOrgIdIsResolvedFromConfig(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.org_id' => 42,
        ]);

        $orgId = app(HubInterface::class)->getClient()->getOptions()->getOrgId();

        $this->assertSame(42, $orgId);
    }

    public function testStrictTraceContinuationIsDisabledByDefault(): void
    {
        $enabled = app(HubInterface::class)->getClient()->getOptions()->isStrictTraceContinuationEnabled();

        $this->assertFalse($enabled);
    }

    public function testStrictTraceContinuationIsResolvedFromConfig(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.strict_trace_continuation' => true,
        ]);

        $enabled = app(HubInterface::class)->getClient()->getOptions()->isStrictTraceContinuationEnabled();

        $this->assertTrue($enabled);
    }

    public function testLogFlushThresholdIsNullByDefault(): void
    {
        $logFlushThreshold = app(HubInterface::class)->getClient()->getOptions()->getLogFlushThreshold();

        $this->assertNull($logFlushThreshold);
    }

    public function testLogFlushThresholdIsResolvedFromConfig(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.log_flush_threshold' => 2,
        ]);

        $logFlushThreshold = app(HubInterface::class)->getClient()->getOptions()->getLogFlushThreshold();

        $this->assertSame(2, $logFlushThreshold);
    }

    public function testLoggerIsNullByDefault(): void
    {
        $logger = app(HubInterface::class)->getClient()->getOptions()->getLogger();

        $this->assertNull($logger);
    }

    public function testLoggerIsResolvedFromDefaultSingleton(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.logger' => DebugFileLogger::class,
        ]);

        $logger = app(HubInterface::class)->getClient()->getOptions()->getLogger();

        $this->assertInstanceOf(DebugFileLogger::class, $logger);
    }
}
