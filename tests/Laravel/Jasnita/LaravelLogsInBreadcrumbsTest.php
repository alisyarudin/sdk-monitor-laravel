<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

class LaravelLogsInBreadcrumbsTest extends JasnitaLaravelTestCase
{
    public function testLaravelLogsAreRecordedWhenEnabled()
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.logs' => true,
        ]);

        $this->assertTrue($this->app['config']->get('jasnita.breadcrumbs.logs'));

        $this->dispatchLaravelEvent('illuminate.log', [
            $level = 'debug',
            $message = 'test message',
            $context = ['1'],
        ]);

        $lastBreadcrumb = $this->getLastBreadcrumb();

        $this->assertEquals($level, $lastBreadcrumb->getLevel());
        $this->assertEquals($message, $lastBreadcrumb->getMessage());
        $this->assertEquals($context, $lastBreadcrumb->getMetadata());
    }

    public function testLaravelLogsAreRecordedWhenDisabled()
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.logs' => false,
        ]);

        $this->assertFalse($this->app['config']->get('jasnita.breadcrumbs.logs'));

        $this->dispatchLaravelEvent('illuminate.log', [
            $level = 'debug',
            $message = 'test message',
            $context = ['1'],
        ]);

        $this->assertEmpty($this->getCurrentBreadcrumbs());
    }
}
