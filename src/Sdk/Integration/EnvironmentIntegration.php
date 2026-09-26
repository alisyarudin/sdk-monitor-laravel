<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Integration;

use Jasnita\Monitor\Sdk\Context\OsContext;
use Jasnita\Monitor\Sdk\Context\RuntimeContext;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Util\PHPVersion;

/**
 * This integration fills the event data with runtime and server OS information.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class EnvironmentIntegration implements IntegrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): Event {
            $integration = JasnitaSdk::getCurrentHub()->getIntegration(self::class);

            if (null !== $integration) {
                $event->setRuntimeContext($integration->updateRuntimeContext($event->getRuntimeContext()));
                $event->setOsContext($integration->updateServerOsContext($event->getOsContext()));
            }

            return $event;
        });
    }

    private function updateRuntimeContext(?RuntimeContext $runtimeContext): RuntimeContext
    {
        if (null === $runtimeContext) {
            $runtimeContext = new RuntimeContext('php');
        }

        if (null === $runtimeContext->getVersion()) {
            $runtimeContext->setVersion(PHPVersion::parseVersion());
        }

        return $runtimeContext;
    }

    private function updateServerOsContext(?OsContext $osContext): ?OsContext
    {
        if (!\function_exists('php_uname')) {
            return $osContext;
        }

        if (null === $osContext) {
            $osContext = new OsContext(php_uname('s'));
        }

        if (null === $osContext->getVersion()) {
            $osContext->setVersion(php_uname('r'));
        }

        if (null === $osContext->getBuild()) {
            $osContext->setBuild(php_uname('v'));
        }

        if (null === $osContext->getKernelVersion()) {
            $osContext->setKernelVersion(php_uname('a'));
        }

        if (null === $osContext->getMachineType()) {
            $osContext->setMachineType(php_uname('m'));
        }

        return $osContext;
    }
}
