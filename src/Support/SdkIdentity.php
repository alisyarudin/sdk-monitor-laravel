<?php

namespace Jasnita\Monitor\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Menandai setiap event sebagai kiriman Jasnita Monitor (bukan nama SDK
 * hulunya), supaya di dashboard terlihat agent dan versinya sendiri.
 *
 * Sengaja callable statis [kelas, metode], bukan closure: nilai ini masuk ke
 * config('sentry.before_send'), dan closure di config membuat
 * `php artisan config:cache` di aplikasi klien gagal.
 */
class SdkIdentity
{
    const NAME = 'jasnita.monitor.laravel';

    /** @var string|null */
    private static $version;

    public static function beforeSend(Event $event, EventHint $hint = null)
    {
        $event->setSdkIdentifier(self::NAME);
        $event->setSdkVersion(self::version());

        return $event;
    }

    public static function version()
    {
        if (self::$version === null) {
            self::$version = 'dev';
            if (class_exists('Composer\\InstalledVersions')
                && \Composer\InstalledVersions::isInstalled('jasnita/monitor-laravel')) {
                self::$version = (string) \Composer\InstalledVersions::getPrettyVersion('jasnita/monitor-laravel');
            }
        }

        return self::$version;
    }
}
