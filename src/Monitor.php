<?php

namespace Jasnita\Monitor;

use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;

/**
 * API kecil untuk kode aplikasi, supaya kode klien memanggil nama Jasnita
 * dan tidak bergantung langsung pada fungsi \Sentry\*.
 */
class Monitor
{
    /** @return string|null id event */
    public function captureException(Throwable $e)
    {
        $id = \Sentry\captureException($e);

        return $id === null ? null : (string) $id;
    }

    /** @return string|null id event */
    public function captureMessage($message, $level = 'info')
    {
        $id = \Sentry\captureMessage((string) $message, new Severity($level));

        return $id === null ? null : (string) $id;
    }

    public function setUser(array $user)
    {
        \Sentry\configureScope(function (Scope $scope) use ($user) {
            $scope->setUser($user);
        });
    }

    public function setTag($key, $value)
    {
        \Sentry\configureScope(function (Scope $scope) use ($key, $value) {
            $scope->setTag((string) $key, (string) $value);
        });
    }

    public function setContext($name, array $data)
    {
        \Sentry\configureScope(function (Scope $scope) use ($name, $data) {
            $scope->setContext((string) $name, $data);
        });
    }

    /** Kirim semua yang masih tertahan (mis. di akhir command panjang). */
    public function flush($timeoutSeconds = 2)
    {
        $client = \Sentry\SentrySdk::getCurrentHub()->getClient();
        if ($client !== null) {
            $client->flush((int) $timeoutSeconds);
        }
    }
}
