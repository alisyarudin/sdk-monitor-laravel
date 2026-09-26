<?php

namespace Jasnita\Monitor;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Jasnita\Monitor\Console\InstallCommand;
use Jasnita\Monitor\Console\TestCommand;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Laravel\ServiceProvider as SdkServiceProvider;
use Jasnita\Monitor\Laravel\Tracing\ServiceProvider as SdkTracingServiceProvider;
use Jasnita\Monitor\Sdk\State\Scope;
use Throwable;

/**
 * Pintu masuk paket. Menerjemahkan config/jasnita-monitor.php ke konfigurasi
 * SDK internal (config 'jasnita', kode di src/Sdk & src/Laravel yang
 * dihasilkan tools/rebrand.php), mendaftarkan provider SDK, lalu memasang
 * pelapor exception.
 *
 * Aplikasi klien cukup mengenal JASNITA_MONITOR_* dan facade Monitor.
 */
class MonitorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jasnita-monitor.php', 'jasnita-monitor');
        $this->app->singleton(Monitor::class);

        // Diterapkan di register(), bukan boot(): client SDK dibuat saat
        // pertama kali dipakai, dan semua register() berjalan sebelum boot()
        // mana pun.
        $this->applySdkConfig();

        // Provider SDK didaftarkan dari sini (bukan package discovery) supaya
        // selalu SETELAH config di atas diterapkan.
        $this->app->register(SdkServiceProvider::class);
        $this->app->register(SdkTracingServiceProvider::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/jasnita-monitor.php' => $this->app->configPath('jasnita-monitor.php'),
            ], 'jasnita-monitor-config');

            $this->commands([InstallCommand::class, TestCommand::class]);
        }

        if (! $this->enabled()) {
            return;
        }

        $this->applyTags();

        if ($this->app['config']->get('jasnita-monitor.auto_report_exceptions', true)) {
            $this->registerExceptionReporter();
        }
    }

    public function enabled()
    {
        return (string) $this->app['config']->get('jasnita-monitor.dsn') !== '';
    }

    private function applySdkConfig()
    {
        $config = $this->app['config'];
        $m = $config->get('jasnita-monitor', []);

        $sdk = [
            // DSN kosong = SDK tidak mengirim apa pun (mis. di local).
            'dsn'                => isset($m['dsn']) && $m['dsn'] !== '' ? $m['dsn'] : null,
            'environment'        => isset($m['environment']) ? $m['environment'] : null,
            'release'            => isset($m['release']) ? $m['release'] : null,
            'sample_rate'        => isset($m['sample_rate']) ? (float) $m['sample_rate'] : 1.0,
            'traces_sample_rate' => isset($m['traces_sample_rate']) ? (float) $m['traces_sample_rate'] : 0.1,
            'send_default_pii'   => ! empty($m['send_default_pii']),
            'ignore_exceptions'  => isset($m['ignore_exceptions']) ? (array) $m['ignore_exceptions'] : [],
            // Server Jasnita Monitor belum memproses log & metrik; SDK tidak
            // perlu mengirim data yang akan dibuang.
            'enable_logs'        => false,
            'enable_metrics'     => false,
        ];

        $advanced = isset($m['advanced']) && is_array($m['advanced']) ? $m['advanced'] : [];

        // Nilai config/jasnita.php (bila dipublish aplikasi) tetap dihormati
        // untuk kunci yang tidak kita atur; kunci kita menang.
        $config->set('jasnita', array_replace_recursive(
            (array) $config->get('jasnita', []),
            $sdk,
            $advanced
        ));
    }

    private function applyTags()
    {
        $tags = (array) $this->app['config']->get('jasnita-monitor.tags', []);
        if ($tags === []) {
            return;
        }

        \Jasnita\Monitor\Sdk\configureScope(function (Scope $scope) use ($tags) {
            $scope->setTags(array_map('strval', $tags));
        });
    }

    /**
     * Laravel 8+ punya Handler::reportable(); yang sama dipakai
     * Integration::handles() di bootstrap/app.php Laravel 11. Memasangnya di
     * sini berarti klien tidak perlu mengubah berkas apa pun.
     *
     * Laravel 6/7 belum punya reportable(): pasang manual di Handler::report()
     * (lihat README).
     */
    private function registerExceptionReporter()
    {
        $installed = false;
        $install = function ($handler) use (&$installed) {
            if ($installed || ! method_exists($handler, 'reportable')) {
                return;
            }
            $installed = true;
            $handler->reportable(function (Throwable $e) {
                Integration::captureUnhandledException($e);
            });
        };

        // Handler bisa saja sudah di-resolve sebelum provider ini boot; bila
        // belum, pasang begitu ia dibuat.
        if ($this->app->resolved(ExceptionHandler::class)) {
            $install($this->app->make(ExceptionHandler::class));
        } else {
            $this->app->afterResolving(ExceptionHandler::class, $install);
        }
    }
}
