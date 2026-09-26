<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use PHPUnit\Runner\BeforeTestHook as BeforeTestHookInterface;
use Jasnita\Monitor\Sdk\Integration\IntegrationRegistry;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Scope;

final class JasnitaSdkExtension implements BeforeTestHookInterface
{
    public function executeBeforeTest(string $test): void
    {
        $reflectionProperty = new \ReflectionProperty(JasnitaSdk::class, 'currentHub');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, null);
        $reflectionProperty->setAccessible(false);

        $reflectionProperty = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, []);
        $reflectionProperty->setAccessible(false);

        $reflectionProperty = new \ReflectionProperty(IntegrationRegistry::class, 'integrations');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(IntegrationRegistry::getInstance(), []);
        $reflectionProperty->setAccessible(false);
    }
}
