<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use RuntimeException;

trait ExpectsException
{
    protected function safeExpectException(string $class): void
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($class);

            return;
        }

        if (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException($class);

            return;
        }

        throw new RuntimeException('Could not expect an exception.');
    }
}
