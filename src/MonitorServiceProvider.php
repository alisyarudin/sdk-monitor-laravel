<?php

namespace Jasnita\Monitor;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Jasnita\Monitor\Console\InstallCommand;
use Jasnita\Monitor\Console\TestCommand;
use Jasnita\Monitor\Support\SdkIdentity;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Throwable;

/**
 * Menerjemahkan config/jasnita-monitor.php menjadi konfigurasi SDK di
 * bawahnya (sentry/sentry-laravel), lalu memasang pelapor exception.
 *
 * Aplikasi klien cukup mengenal JASNITA_MONITOR_*; mesin pengirimnya — yang
 * sudah teruji di banyak versi Laravel/PHP — tetap dirawat hulunya.
 */
class MonitorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jasnita-monitor.php', 'jasnita-monitor');
        $this->app->singleton(Monitor::class);

        // Diterapkan di register(), bukan boot(): client SDK dibuat saat
        // pertama kali dipakai, dan semua register() berjalan sebelum boot()
        // mana pun — jadi urutan provider paket tidak berpengaruh.
        $this->applySdkConfig();
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

        // Identitas agent Jasnita di setiap event — kecuali aplikasi sudah
        // memasang before_send sendiri; itu tidak ditimpa.
        $existing = (array) $config->get('sentry', []);
        foreach (['before_send', 'before_send_transaction'] as $hook) {
            if (empty($existing[$hook])) {
                $sdk[$hook] = [SdkIdentity::class, 'beforeSend'];
            }
        }

        $advanced = isset($m['advanced']) && is_array($m['advanced']) ? $m['advanced'] : [];

        // Nilai milik aplikasi (config/sentry.php yang dipublish) tetap
        // dihormati untuk kunci yang tidak kita atur; kunci kita menang.
        $config->set('sentry', array_replace_recursive(
            (array) $config->get('sentry', []),
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

        \Sentry\configureScope(function (Scope $scope) use ($tags) {
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
