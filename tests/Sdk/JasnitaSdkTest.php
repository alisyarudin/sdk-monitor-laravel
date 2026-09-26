<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;

final class JasnitaSdkTest extends TestCase
{
    public function testInit(): void
    {
        $hub1 = JasnitaSdk::init();
        $hub2 = JasnitaSdk::getCurrentHub();

        $this->assertSame($hub1, $hub2);
        $this->assertNotSame(JasnitaSdk::init(), JasnitaSdk::init());
    }

    public function testGetCurrentHub(): void
    {
        JasnitaSdk::init();

        $hub2 = JasnitaSdk::getCurrentHub();
        $hub3 = JasnitaSdk::getCurrentHub();

        $this->assertSame($hub2, $hub3);
    }

    public function testSetCurrentHub(): void
    {
        $hub = new Hub();

        $this->assertSame($hub, JasnitaSdk::setCurrentHub($hub));
        $this->assertSame($hub, JasnitaSdk::getCurrentHub());
    }
}
