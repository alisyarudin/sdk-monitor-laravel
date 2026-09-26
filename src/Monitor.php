<?php

namespace Jasnita\Monitor;

use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Severity;
use Jasnita\Monitor\Sdk\State\Scope;
use Throwable;

/**
 * API kecil untuk kode aplikasi, supaya kode klien tidak bergantung pada
 * susunan internal SDK (src/Sdk) yang dihasilkan ulang tiap pembaruan hulu.
 */
class Monitor
{
    /** @return string|null id event */
    public function captureException(Throwable $e)
    {
        $id = \Jasnita\Monitor\Sdk\captureException($e);

        return $id === null ? null : (string) $id;
    }

    /** @return string|null id event */
    public function captureMessage($message, $level = 'info')
    {
        $id = \Jasnita\Monitor\Sdk\captureMessage((string) $message, new Severity($level));

        return $id === null ? null : (string) $id;
    }

    public function setUser(array $user)
    {
        \Jasnita\Monitor\Sdk\configureScope(function (Scope $scope) use ($user) {
            $scope->setUser($user);
        });
    }

    public function setTag($key, $value)
    {
        \Jasnita\Monitor\Sdk\configureScope(function (Scope $scope) use ($key, $value) {
            $scope->setTag((string) $key, (string) $value);
        });
    }

    public function setContext($name, array $data)
    {
        \Jasnita\Monitor\Sdk\configureScope(function (Scope $scope) use ($name, $data) {
            $scope->setContext((string) $name, $data);
        });
    }

    /** Kirim semua yang masih tertahan (mis. di akhir command panjang). */
    public function flush($timeoutSeconds = 2)
    {
        $client = JasnitaSdk::getCurrentHub()->getClient();
        if ($client !== null) {
            $client->flush((int) $timeoutSeconds);
        }
    }
}
