<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\MonitorScheduleUnit;

final class MonitorScheduleUnitTest extends TestCase
{
    public function testMinute(): void
    {
        $monitorScheduleUnit = MonitorScheduleUnit::minute();

        $this->assertSame('minute', (string) $monitorScheduleUnit);
    }

    public function testHour(): void
    {
        $monitorScheduleUnit = MonitorScheduleUnit::hour();

        $this->assertSame('hour', (string) $monitorScheduleUnit);
    }

    public function testDay(): void
    {
        $monitorScheduleUnit = MonitorScheduleUnit::day();

        $this->assertSame('day', (string) $monitorScheduleUnit);
    }

    public function testWeek(): void
    {
        $monitorScheduleUnit = MonitorScheduleUnit::week();

        $this->assertSame('week', (string) $monitorScheduleUnit);
    }

    public function testMonth(): void
    {
        $monitorScheduleUnit = MonitorScheduleUnit::month();

        $this->assertSame('month', (string) $monitorScheduleUnit);
    }

    public function testYear(): void
    {
        $monitorScheduleUnit = MonitorScheduleUnit::year();

        $this->assertSame('year', (string) $monitorScheduleUnit);
    }
}
