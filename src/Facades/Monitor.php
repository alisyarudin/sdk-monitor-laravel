<?php

namespace Jasnita\Monitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Monitor::captureException($e);
 * Monitor::captureMessage('Pembayaran tertunda', 'warning');
 * Monitor::setUser(['id' => $user->id]);
 * Monitor::setTag('tenant', $tenantId);
 *
 * @method static string|null captureException(\Throwable $e)
 * @method static string|null captureMessage(string $message, string $level = 'info')
 * @method static void setUser(array $user)
 * @method static void setTag(string $key, string $value)
 * @method static void setContext(string $name, array $data)
 * @method static void flush(int $timeoutSeconds = 2)
 */
class Monitor extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Jasnita\Monitor\Monitor::class;
    }
}
