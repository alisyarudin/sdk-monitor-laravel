<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Features;

use Illuminate\Console\Events\CommandStarting;
use Jasnita\Monitor\Laravel\Tests\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ConsoleIntegrationTest extends TestCase
{
    public function testCommandBreadcrumbIsRecordedWhenEnabled(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.command_info' => true,
        ]);

        $this->assertTrue($this->app['config']->get('jasnita.breadcrumbs.command_info'));

        $this->dispatchCommandStartEvent();

        $lastBreadcrumb = $this->getLastJasnitaBreadcrumb();

        $this->assertEquals('Starting Artisan command: test:command', $lastBreadcrumb->getMessage());
        $this->assertEquals('--foo=bar', $lastBreadcrumb->getMetadata()['input']);
    }

    public function testCommandBreadcrumIsNotRecordedWhenDisabled(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.command_info' => false,
        ]);

        $this->assertFalse($this->app['config']->get('jasnita.breadcrumbs.command_info'));

        $this->dispatchCommandStartEvent();

        $this->assertEmpty($this->getCurrentJasnitaBreadcrumbs());
    }

    private function dispatchCommandStartEvent(): void
    {
        $this->dispatchLaravelEvent(
            new CommandStarting(
                'test:command',
                new ArgvInput(['artisan', '--foo=bar']),
                new BufferedOutput()
            )
        );
    }
}
