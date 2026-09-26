<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Console;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Jasnita\Monitor\Laravel\Console\PublishCommand;

class PublishCommandTest extends TestCase
{
    public function testIsEnvKeySetTreatsRegexMetacharactersAsLiterals(): void
    {
        $command = new PublishCommand();

        $isEnvKeySetMethod = new ReflectionMethod($command, 'isEnvKeySet');
        if (\PHP_VERSION_ID < 80100) {
            $isEnvKeySetMethod->setAccessible(true);
        }

        $this->assertFalse((bool)$isEnvKeySetMethod->invoke(
            $command,
            'JASNITA.*KEY',
            "JASNITAAKEY=true\n"
        ));

        $this->assertTrue((bool)$isEnvKeySetMethod->invoke(
            $command,
            'JASNITA.*KEY',
            "JASNITA.*KEY=true\n"
        ));
    }

    public function testEnvKeyPatternEscapesRegexMetacharactersForReplacement(): void
    {
        $command = new PublishCommand();

        $getEnvKeyPatternMethod = new ReflectionMethod($command, 'getEnvKeyPattern');
        if (\PHP_VERSION_ID < 80100) {
            $getEnvKeyPatternMethod->setAccessible(true);
        }

        $pattern = $getEnvKeyPatternMethod->invoke($command, 'JASNITA.*KEY');

        $updatedContents = preg_replace(
            $pattern,
            "JASNITA.*KEY=new\n",
            "JASNITAAKEY=old\nJASNITA.*KEY=old\n"
        );

        $this->assertSame("JASNITAAKEY=old\nJASNITA.*KEY=new\n", $updatedContents);
    }
}
