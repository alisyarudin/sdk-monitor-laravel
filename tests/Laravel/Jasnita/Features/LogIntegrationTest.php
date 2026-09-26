<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Features;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Jasnita\Monitor\Laravel\Tests\TestCase;
use Jasnita\Monitor\Sdk\Severity;

class LogIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        tap($app['config'], static function (Repository $config) {
            $config->set('logging.channels.jasnita', [
                'driver' => 'jasnita',
            ]);

            $config->set('logging.channels.jasnita_error_level', [
                'driver' => 'jasnita',
                'level' => 'error',
            ]);
        });
    }

    public function testLogChannelIsRegistered(): void
    {
        $this->expectNotToPerformAssertions();

        Log::channel('jasnita');
    }

    /** @define-env envWithoutDsnSet */
    #[DefineEnvironment('envWithoutDsnSet')]
    public function testLogChannelIsRegisteredWithoutDsn(): void
    {
        $this->expectNotToPerformAssertions();

        Log::channel('jasnita');
    }

    public function testLogChannelGeneratesEvents(): void
    {
        $logger = Log::channel('jasnita');

        $logger->info('Jasnita Laravel info log message');

        $this->assertJasnitaEventCount(1);

        $event = $this->getLastJasnitaEvent();

        $this->assertEquals(Severity::info(), $event->getLevel());
        $this->assertEquals('Jasnita Laravel info log message', $event->getMessage());
    }

    public function testLogChannelGeneratesEventsOnlyForConfiguredLevel(): void
    {
        $logger = Log::channel('jasnita_error_level');

        $logger->info('Jasnita Laravel info log message');
        $logger->warning('Jasnita Laravel warning log message');
        $logger->error('Jasnita Laravel error log message');

        $this->assertJasnitaEventCount(1);

        $event = $this->getLastJasnitaEvent();

        $this->assertEquals(Severity::error(), $event->getLevel());
        $this->assertEquals('Jasnita Laravel error log message', $event->getMessage());
    }
}
