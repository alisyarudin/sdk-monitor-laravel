<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\ExceptionMechanism;

final class ExceptionMechanismTest extends TestCase
{
    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(
        array $constructorArgs,
        string $expectedType,
        bool $expectedHandled,
        array $expectedData
    ): void {
        $exceptionMechanism = new ExceptionMechanism(...$constructorArgs);

        $this->assertSame($expectedType, $exceptionMechanism->getType());
        $this->assertSame($expectedHandled, $exceptionMechanism->isHandled());
        $this->assertSame($expectedData, $exceptionMechanism->getData());
    }

    public static function constructorDataProvider(): iterable
    {
        yield [
            [
                ExceptionMechanism::TYPE_GENERIC,
                true,
            ],
            ExceptionMechanism::TYPE_GENERIC,
            true,
            [],
        ];

        yield [
            [
                'custom',
                false,
                ['key' => 'value'],
            ],
            'custom',
            false,
            ['key' => 'value'],
        ];
    }

    public function testSetData(): void
    {
        $exceptionDataBag = new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true, ['replace' => 'me']);
        $exceptionDataBag->setData(['new' => 'value']);
        $this->assertSame(['new' => 'value'], $exceptionDataBag->getData());
    }
}
