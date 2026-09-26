<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Integration\ModulesIntegration;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;
use function Jasnita\Monitor\Sdk\withScope;

final class ModulesIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(bool $isIntegrationEnabled, bool $expectedEmptyModules): void
    {
        $integration = new ModulesIntegration();
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($isIntegrationEnabled ? $integration : null);

        JasnitaSdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope) use ($expectedEmptyModules): void {
            $event = $scope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);

            if ($expectedEmptyModules) {
                $this->assertEmpty($event->getModules());
            } else {
                $this->assertNotEmpty($event->getModules());
            }
        });
    }

    public static function invokeDataProvider(): \Generator
    {
        yield [
            false,
            true,
        ];

        yield [
            true,
            false,
        ];
    }
}
