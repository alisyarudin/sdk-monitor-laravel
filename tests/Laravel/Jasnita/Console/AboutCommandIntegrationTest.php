<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Console;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Artisan;
use Jasnita\Monitor\Sdk\Client;
use Jasnita\Monitor\Laravel\Tests\TestCase;
use Jasnita\Monitor\Laravel\Version;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\State\HubInterface;

class AboutCommandIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AboutCommand::class)) {
            $this->markTestSkipped('The about command is only available in Laravel 9.0+');
        }

        parent::setUp();
    }

    public function testAboutCommandContainsExpectedData(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.release' => '1.2.3',
            'jasnita.environment' => 'testing',
            'jasnita.traces_sample_rate' => 0.95,
        ]);

        $expectedData = [
            'environment' => 'testing',
            'release' => '1.2.3',
            'sample_rate_errors' => '100%',
            'sample_rate_profiling' => 'NOT SET',
            'sample_rate_performance_monitoring' => '95%',
            'send_default_pii' => 'DISABLED',
            'php_sdk_version' => Client::SDK_VERSION,
            'laravel_sdk_version' => Version::SDK_VERSION,
        ];

        $actualData = $this->runArtisanAboutAndReturnJasnitaData();

        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $actualData);
            $this->assertEquals($value, $actualData[$key]);
        }
    }

    public function testAboutCommandContainsExpectedDataWithoutHubClient(): void
    {
        $this->app->bind(HubInterface::class, static function () {
            return new Hub(null);
        });

        $expectedData = [
            'enabled' => 'NOT CONFIGURED',
            'php_sdk_version' => Client::SDK_VERSION,
            'laravel_sdk_version' => Version::SDK_VERSION,
        ];

        $actualData = $this->runArtisanAboutAndReturnJasnitaData();

        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $actualData);
            $this->assertEquals($value, $actualData[$key]);
        }
    }

    private function runArtisanAboutAndReturnJasnitaData(): array
    {
        $this->withoutMockingConsoleOutput();

        $this->artisan(AboutCommand::class, ['--json' => null]);

        $output = Artisan::output();

        // This might seem like a weird thing to do, but it's necessary to make sure that that the command didn't have any side effects on the container
        $this->refreshApplication();

        $aboutOutput = json_decode($output, true);

        $this->assertArrayHasKey('jasnita', $aboutOutput);

        return $aboutOutput['jasnita'];
    }
}
