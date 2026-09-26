<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Jasnita\Monitor\Laravel;

use Illuminate\Log\LogManager;
use Monolog\Handler\FingersCrossedHandler;
use Jasnita\Monitor\Laravel\LogChannel;
use Jasnita\Monitor\Laravel\JasnitaHandler;
use Jasnita\Monitor\Laravel\Tests\JasnitaLaravelTestCase;

class LogChannelTest extends JasnitaLaravelTestCase
{
    public function test_creating_handler_without_action_level_config()
    {
        $this->skipIfLogManagerNotAvailable();

        $logChannel = new LogChannel($this->app);
        $logger = $logChannel([]);

        $this->assertContainsOnlyInstancesOf(JasnitaHandler::class, $logger->getHandlers());
    }

    public function test_creating_handler_with_action_level_config()
    {
        $this->skipIfLogManagerNotAvailable();

        $logChannel = new LogChannel($this->app);
        $logger = $logChannel(['action_level' => 'critical']);

        $this->assertContainsOnlyInstancesOf(FingersCrossedHandler::class, $logger->getHandlers());

        $currentHandler = current($logger->getHandlers());
        $this->assertInstanceOf(JasnitaHandler::class, $currentHandler->getHandler());

        $loggerWithoutActionLevel = $logChannel(['action_level' => null]);

        $this->assertContainsOnlyInstancesOf(JasnitaHandler::class, $loggerWithoutActionLevel->getHandlers());
    }

    private function skipIfLogManagerNotAvailable()
    {
        if (class_exists(LogManager::class)) {
            return;
        }

        $this->markTestSkipped('Laravel version <=5.5 does not contain the LogManager required for this functionality.');
    }
}
