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
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, null);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(JasnitaSdk::class, 'runtimeContextManager');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, null);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(JasnitaSdk::class, 'runtimeContextStorage');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, null);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        StubTransport::$events = [];

        $reflectionProperty = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, []);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(Scope::class, 'externalPropagationContextCallback');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, null);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(IntegrationRegistry::class, 'integrations');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(IntegrationRegistry::getInstance(), []);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }
    }
}
