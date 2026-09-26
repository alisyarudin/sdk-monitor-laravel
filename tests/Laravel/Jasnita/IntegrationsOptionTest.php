<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Exception;
use RuntimeException;
use Jasnita\Monitor\Sdk\Integration\IntegrationInterface;
use Jasnita\Monitor\Sdk\Integration\ErrorListenerIntegration;
use Jasnita\Monitor\Sdk\Integration\ExceptionListenerIntegration;
use Jasnita\Monitor\Sdk\Integration\FatalErrorListenerIntegration;

class IntegrationsOptionTest extends JasnitaLaravelTestCase
{
    use ExpectsException;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton('custom-jasnita-integration', static function () {
            return new IntegrationsOptionTestIntegrationStub;
        });
    }

    public function testCustomIntegrationIsResolvedFromContainerByAlias()
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                'custom-jasnita-integration',
            ],
        ]);

        $this->assertNotNull($this->getHubFromContainer()->getClient()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
    }

    public function testCustomIntegrationIsResolvedFromContainerByClass()
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                IntegrationsOptionTestIntegrationStub::class,
            ],
        ]);

        $this->assertNotNull($this->getHubFromContainer()->getClient()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
    }

    public function testCustomIntegrationByInstance()
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                new IntegrationsOptionTestIntegrationStub,
            ],
        ]);

        $this->assertNotNull($this->getHubFromContainer()->getClient()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
    }

    /**
     * Throws \ReflectionException in <=5.8 and \Illuminate\Contracts\Container\BindingResolutionException since 6.0
     */
    public function testCustomIntegrationThrowsExceptionIfNotResolvable()
    {
        $this->safeExpectException(Exception::class);

        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                'this-will-not-resolve',
            ],
        ]);
    }

    public function testIncorrectIntegrationEntryThrowsException()
    {
        $this->safeExpectException(RuntimeException::class);

        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                static function () {
                },
            ],
        ]);
    }

    public function testDisabledIntegrationsAreNotPresent()
    {
        $integrations = $this->getHubFromContainer()->getClient()->getOptions()->getIntegrations();

        foreach ($integrations as $integration) {
            $this->ensureIsNotDisabledIntegration($integration);
        }

        $this->assertTrue(true, 'Not all disabled integrations are actually disabled.');
    }

    public function testDisabledIntegrationsAreNotPresentWithCustomIntegrations()
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                new IntegrationsOptionTestIntegrationStub,
            ],
        ]);

        $this->assertNotNull($this->getHubFromContainer()->getClient()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
        $this->assertNull($this->getHubFromContainer()->getClient()->getIntegration(ErrorListenerIntegration::class));
        $this->assertNull($this->getHubFromContainer()->getClient()->getIntegration(ExceptionListenerIntegration::class));
        $this->assertNull($this->getHubFromContainer()->getClient()->getIntegration(FatalErrorListenerIntegration::class));
    }
}

class IntegrationsOptionTestIntegrationStub implements IntegrationInterface
{
    public function setupOnce(): void
    {
    }
}
