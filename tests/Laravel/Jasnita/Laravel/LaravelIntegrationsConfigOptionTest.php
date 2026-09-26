<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Laravel;

use Illuminate\Contracts\Container\BindingResolutionException;
use RuntimeException;
use Jasnita\Monitor\Sdk\Integration\IntegrationInterface;
use Jasnita\Monitor\Sdk\Integration\OTLPIntegration;
use Jasnita\Monitor\Sdk\Integration\ErrorListenerIntegration;
use Jasnita\Monitor\Sdk\Integration\ExceptionListenerIntegration;
use Jasnita\Monitor\Sdk\Integration\FatalErrorListenerIntegration;
use Jasnita\Monitor\Laravel\Tests\TestCase;

class LaravelIntegrationsConfigOptionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->singleton('custom-jasnita-integration', static function () {
            return new IntegrationsOptionTestIntegrationStub;
        });
    }

    public function testCustomIntegrationIsResolvedFromContainerByAlias(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                'custom-jasnita-integration',
            ],
        ]);

        $this->assertNotNull($this->getJasnitaClientFromContainer()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
    }

    public function testCustomIntegrationIsResolvedFromContainerByClass(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                IntegrationsOptionTestIntegrationStub::class,
            ],
        ]);

        $this->assertNotNull($this->getJasnitaClientFromContainer()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
    }

    public function testOtlpIntegrationClassIsRegisteredFromIntegrationsConfig(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                OTLPIntegration::class,
            ],
        ]);

        $this->assertNotNull($this->getJasnitaClientFromContainer()->getIntegration(OTLPIntegration::class));
    }

    public function testCustomIntegrationByInstance(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                new IntegrationsOptionTestIntegrationStub,
            ],
        ]);

        $this->assertNotNull($this->getJasnitaClientFromContainer()->getIntegration(IntegrationsOptionTestIntegrationStub::class));
    }

    public function testCustomIntegrationThrowsExceptionIfNotResolvable(): void
    {
        $this->expectException(BindingResolutionException::class);

        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                'this-will-not-resolve',
            ],
        ]);
    }

    public function testIncorrectIntegrationEntryThrowsException(): void
    {
        $this->expectException(RuntimeException::class);

        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                static function () {
                },
            ],
        ]);
    }

    public function testDisabledIntegrationsAreNotPresent(): void
    {
        $client = $this->getJasnitaClientFromContainer();

        $this->assertNull($client->getIntegration(ErrorListenerIntegration::class));
        $this->assertNull($client->getIntegration(ExceptionListenerIntegration::class));
        $this->assertNull($client->getIntegration(FatalErrorListenerIntegration::class));
    }

    public function testDisabledIntegrationsAreNotPresentWithCustomIntegrations(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.integrations' => [
                new IntegrationsOptionTestIntegrationStub,
            ],
        ]);

        $client = $this->getJasnitaClientFromContainer();

        $this->assertNotNull($client->getIntegration(IntegrationsOptionTestIntegrationStub::class));

        $this->assertNull($client->getIntegration(ErrorListenerIntegration::class));
        $this->assertNull($client->getIntegration(ExceptionListenerIntegration::class));
        $this->assertNull($client->getIntegration(FatalErrorListenerIntegration::class));
    }
}

class IntegrationsOptionTestIntegrationStub implements IntegrationInterface
{
    public function setupOnce(): void
    {
    }
}
