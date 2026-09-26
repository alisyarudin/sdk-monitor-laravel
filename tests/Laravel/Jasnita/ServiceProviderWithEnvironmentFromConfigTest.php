<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Sdk;

use Jasnita\Monitor\Laravel\Tests\JasnitaLaravelTestCase;

class ServiceProviderWithEnvironmentFromConfigTest extends JasnitaLaravelTestCase
{
    public function testJasnitaEnvironmentDefaultsToLaravelEnvironment()
    {
        $this->assertEquals('testing', app()->environment());
    }

    public function testEmptyJasnitaEnvironmentDefaultsToLaravelEnvironment()
    {
        $this->resetApplicationWithConfig([
            'jasnita.environment' => '',
        ]);

        $this->assertEquals('testing', $this->getHubFromContainer()->getClient()->getOptions()->getEnvironment());

        $this->resetApplicationWithConfig([
            'jasnita.environment' => null,
        ]);

        $this->assertEquals('testing', $this->getHubFromContainer()->getClient()->getOptions()->getEnvironment());
    }

    public function testJasnitaEnvironmentDefaultGetsOverriddenByConfig()
    {
        $this->resetApplicationWithConfig([
            'jasnita.environment' => 'not_testing',
        ]);

        $this->assertEquals('not_testing', $this->getHubFromContainer()->getClient()->getOptions()->getEnvironment());
    }
}
