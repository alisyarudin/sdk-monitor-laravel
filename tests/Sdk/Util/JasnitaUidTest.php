<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Util;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Util\JasnitaUid;

final class JasnitaUidTest extends TestCase
{
    public function testGenerate(): void
    {
        $result = JasnitaUid::generate();
        $pattern = '/^[0-9a-f]{8}[0-9a-f]{4}4[0-9a-f]{3}[89ab][0-9a-f]{3}[0-9a-f]{12}$/';
        $match = (bool) preg_match($pattern, $result);
        $this->assertTrue($match);
    }
}
