<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

class ServiceProviderWithEnvironmentFromConfigTest extends TestCase
{
    public function testJasnitaEnvironmentDefaultsToLaravelEnvironment(): void
    {
        $this->assertEquals('testing', app()->environment());
    }

    public function testEmptyJasnitaEnvironmentDefaultsToLaravelEnvironment(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.environment' => '',
        ]);

        $this->assertEquals('testing', $this->getJasnitaClientFromContainer()->getOptions()->getEnvironment());

        $this->resetApplicationWithConfig([
            'jasnita.environment' => null,
        ]);

        $this->assertEquals('testing', $this->getJasnitaClientFromContainer()->getOptions()->getEnvironment());
    }

    public function testJasnitaEnvironmentDefaultGetsOverriddenByConfig(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.environment' => 'override_env',
        ]);

        $this->assertEquals('override_env', $this->getJasnitaClientFromContainer()->getOptions()->getEnvironment());
    }
}
