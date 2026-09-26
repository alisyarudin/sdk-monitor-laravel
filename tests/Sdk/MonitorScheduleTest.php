<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\MonitorSchedule;
use Jasnita\Monitor\Sdk\MonitorScheduleUnit;

final class MonitorScheduleTest extends TestCase
{
    public function testConstructor(): void
    {
        $monitorSchedule = new MonitorSchedule(
            MonitorSchedule::TYPE_CRONTAB,
            '* * * * *'
        );

        $this->assertEquals(MonitorSchedule::TYPE_CRONTAB, $monitorSchedule->getType());
        $this->assertEquals('* * * * *', $monitorSchedule->getValue());
        $this->assertNull($monitorSchedule->getUnit());
    }

    public function testConvenienceCrontabConstructor(): void
    {
        $monitorSchedule = MonitorSchedule::crontab('* * * * *');

        $this->assertEquals(MonitorSchedule::TYPE_CRONTAB, $monitorSchedule->getType());
        $this->assertEquals('* * * * *', $monitorSchedule->getValue());
        $this->assertNull($monitorSchedule->getUnit());
    }

    public function testConvenienceIntervalConstructor(): void
    {
        $monitorSchedule = MonitorSchedule::interval(10, MonitorScheduleUnit::minute());

        $this->assertEquals(MonitorSchedule::TYPE_INTERVAL, $monitorSchedule->getType());
        $this->assertEquals(10, $monitorSchedule->getValue());
        $this->assertEquals(MonitorScheduleUnit::minute(), $monitorSchedule->getUnit());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $expectedData): void
    {
        $monitorSchedule = new MonitorSchedule(
            MonitorSchedule::TYPE_CRONTAB,
            '* * * * *'
        );
        $monitorSchedule->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $monitorSchedule->$getterMethod());
    }

    public static function gettersAndSettersDataProvider(): array
    {
        return [
            ['getType', 'setType', MonitorSchedule::TYPE_INTERVAL],
            ['getValue', 'setValue', '* * * * *'],
            ['getUnit', 'setUnit', MonitorScheduleUnit::hour()],
        ];
    }
}
