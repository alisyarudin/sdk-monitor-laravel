<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\ExceptionMechanism;
use Jasnita\Monitor\Sdk\Frame;
use Jasnita\Monitor\Sdk\Stacktrace;

final class EventHintTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $exception = new \Exception();
        $mechanism = new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, false);
        $stacktrace = new Stacktrace([
            new Frame('function_1', 'path/to/file_1', 10),
        ]);

        $hint = EventHint::fromArray([
            'exception' => $exception,
            'mechanism' => $mechanism,
            'stacktrace' => $stacktrace,
            'extra' => ['foo' => 'bar'],
        ]);

        $this->assertSame($exception, $hint->exception);
        $this->assertSame($mechanism, $hint->mechanism);
        $this->assertSame($stacktrace, $hint->stacktrace);
        $this->assertSame(['foo' => 'bar'], $hint->extra);
    }

    /**
     * @dataProvider createFromArrayWithInvalidValuesDataProvider
     */
    public function testCreateFromArrayWithInvalidValues(array $hintData, string $expectedExceptionMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        EventHint::fromArray($hintData);
    }

    public static function createFromArrayWithInvalidValuesDataProvider(): \Generator
    {
        yield [
            ['exception' => 'foo'],
            'The value of the "exception" field must be an instance of a class implementing the "Throwable" interface. Got: "string".',
        ];

        yield [
            ['mechanism' => 'foo'],
            'The value of the "mechanism" field must be an instance of the "Jasnita\\Monitor\\Sdk\\ExceptionMechanism" class. Got: "string".',
        ];

        yield [
            ['stacktrace' => 'foo'],
            'The value of the "stacktrace" field must be an instance of the "Jasnita\\Monitor\\Sdk\\Stacktrace" class. Got: "string".',
        ];

        yield [
            ['extra' => 'foo'],
            'The value of the "extra" field must be an array. Got: "string".',
        ];
    }
}
