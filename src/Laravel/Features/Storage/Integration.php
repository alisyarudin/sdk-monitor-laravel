<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features\Storage;

use Illuminate\Contracts\Filesystem\Cloud as CloudFilesystem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use Jasnita\Monitor\Laravel\Features\Feature;

class Integration extends Feature
{
    private const FEATURE_KEY = 'storage';

    private const STORAGE_DRIVER_NAME = 'jasnita';

    public function isApplicable(): bool
    {
        // Since we only register the driver this feature is always applicable
        return true;
    }

    public function register(): void
    {
        $this->container()->afterResolving(FilesystemManager::class, function (FilesystemManager $filesystemManager): void {
            // Store constants and default feature flags in local variables because `FilesystemManager::extend()`
            // re-binds the closure scope to `FilesystemManager` which causes `self::` and `$this` to resolve
            // on `FilesystemManager` instead of the `Integration` class.
            $driverName = self::STORAGE_DRIVER_NAME;
            $defaultRecordSpans = $this->isTracingFeatureEnabled(self::FEATURE_KEY);
            $defaultRecordBreadcrumbs = $this->isBreadcrumbFeatureEnabled(self::FEATURE_KEY);

            $filesystemManager->extend(
                $driverName,
                function (Application $application, array $config) use ($filesystemManager, $driverName, $defaultRecordSpans, $defaultRecordBreadcrumbs): Filesystem {
                    if (empty($config['jasnita_disk_name'])) {
                        throw new RuntimeException(sprintf('Missing `jasnita_disk_name` config key for `%s` filesystem driver.', $driverName));
                    }

                    if (empty($config['jasnita_original_driver'])) {
                        throw new RuntimeException(sprintf('Missing `jasnita_original_driver` config key for `%s` filesystem driver.', $driverName));
                    }

                    if ($config['jasnita_original_driver'] === $driverName) {
                        throw new RuntimeException(sprintf('`jasnita_original_driver` for Jasnita storage integration cannot be the `%s` driver.', $driverName));
                    }

                    $disk = $config['jasnita_disk_name'];

                    $config['driver'] = $config['jasnita_original_driver'];
                    unset($config['jasnita_original_driver']);

                    $diskResolver = (function (string $disk, array $config) {
                        // This is a "hack" to make sure that the original driver is resolved by the FilesystemManager
                        $oldConfig = config("filesystems.disks.{$disk}");

                        config(["filesystems.disks.{$disk}" => $config]);

                        /** @var FilesystemManager $this */
                        $resolved = $this->resolve($disk);

                        config(["filesystems.disks.{$disk}" => $oldConfig]);

                        return $resolved;
                    })->bindTo($filesystemManager, FilesystemManager::class);

                    /** @var Filesystem $originalFilesystem */
                    $originalFilesystem = $diskResolver($disk, $config);

                    $defaultData = ['disk' => $disk, 'driver' => $config['driver']];

                    $recordSpans = $config['jasnita_enable_spans'] ?? $defaultRecordSpans;
                    $recordBreadcrumbs = $config['jasnita_enable_breadcrumbs'] ?? $defaultRecordBreadcrumbs;

                    if ($originalFilesystem instanceof AwsS3V3Adapter) {
                        return new JasnitaS3V3Adapter($originalFilesystem, $defaultData, $recordSpans, $recordBreadcrumbs);
                    }

                    if ($originalFilesystem instanceof FilesystemAdapter) {
                        return new JasnitaFilesystemAdapter($originalFilesystem, $defaultData, $recordSpans, $recordBreadcrumbs);
                    }

                    if ($originalFilesystem instanceof CloudFilesystem) {
                        return new JasnitaCloudFilesystem($originalFilesystem, $defaultData, $recordSpans, $recordBreadcrumbs);
                    }

                    return new JasnitaFilesystem($originalFilesystem, $defaultData, $recordSpans, $recordBreadcrumbs);
                }
            );
        });
    }

    /**
     * Decorates the configuration for a single disk with Jasnita driver configuration.

     * This replaces the driver with a custom driver that will capture performance traces and breadcrumbs.
     *
     * The custom driver will be an instance of @see \Jasnita\Monitor\Laravel\Features\Storage\JasnitaS3V3Adapter
     * if the original driver is an @see \Illuminate\Filesystem\AwsS3V3Adapter,
     * and an instance of @see \Jasnita\Monitor\Laravel\Features\Storage\JasnitaFilesystemAdapter
     * if the original driver is an @see \Illuminate\Filesystem\FilesystemAdapter.
     * If the original driver is neither of those, it will be @see \Jasnita\Monitor\Laravel\Features\Storage\JasnitaFilesystem
     * or @see \Jasnita\Monitor\Laravel\Features\Storage\JasnitaCloudFilesystem based on the contract of the original driver.
     *
     * You might run into problems if you expect another specific driver class.
     *
     * @param array<string, mixed> $diskConfig
     *
     * @return array<string, mixed>
     */
    public static function configureDisk(string $diskName, array $diskConfig, bool $enableSpans = true, bool $enableBreadcrumbs = true): array
    {
        $currentDriver = $diskConfig['driver'];

        if ($currentDriver !== self::STORAGE_DRIVER_NAME) {
            $diskConfig['driver'] = self::STORAGE_DRIVER_NAME;
            $diskConfig['jasnita_disk_name'] = $diskName;
            $diskConfig['jasnita_original_driver'] = $currentDriver;
            $diskConfig['jasnita_enable_spans'] = $enableSpans;
            $diskConfig['jasnita_enable_breadcrumbs'] = $enableBreadcrumbs;
        }

        return $diskConfig;
    }

    /**
     * Decorates the configuration for all disks with Jasnita driver configuration.
     *
     * @see self::configureDisk()
     *
     * @param array<string, array<string, mixed>> $diskConfigs
     *
     * @return array<string, array<string, mixed>>
     */
    public static function configureDisks(array $diskConfigs, bool $enableSpans = true, bool $enableBreadcrumbs = true): array
    {
        $diskConfigsWithJasnitaDriver = [];
        foreach ($diskConfigs as $diskName => $diskConfig) {
            $diskConfigsWithJasnitaDriver[$diskName] = static::configureDisk($diskName, $diskConfig, $enableSpans, $enableBreadcrumbs);
        }

        return $diskConfigsWithJasnitaDriver;
    }
}
