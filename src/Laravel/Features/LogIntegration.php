<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features;

use Illuminate\Support\Facades\Log;
use Jasnita\Monitor\Laravel\LogChannel;
use Jasnita\Monitor\Laravel\Logs\LogChannel as LogsLogChannel;

class LogIntegration extends Feature
{
    public function isApplicable(): bool
    {
        return true;
    }

    public function register(): void
    {
        Log::extend('jasnita', function ($app, array $config) {
            return (new LogChannel($app))($config);
        });

        Log::extend('jasnita_logs', function ($app, array $config) {
            return (new LogsLogChannel($app))($config);
        });
    }
}
