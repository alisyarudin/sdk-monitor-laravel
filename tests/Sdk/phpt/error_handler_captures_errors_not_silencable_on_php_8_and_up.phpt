--TEST--
Test that the error handler ignores silenced errors by default, but it reports them with the appropriate option enabled.
--SKIPIF--
<?php
if (\PHP_MAJOR_VERSION < 8) {
    die('Skip on PHP < 8 because it\'s not applicable.');
}
?>
--INI--
error_reporting=E_ALL
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Transport\Result;
use Jasnita\Monitor\Sdk\Transport\ResultStatus;
use Jasnita\Monitor\Sdk\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transport = new class implements TransportInterface {
    public function send(Event $event): Result
    {
        echo 'Transport called' . PHP_EOL;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
};

error_reporting(E_ALL & ~E_USER_ERROR & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$options = [
    'dsn' => 'http://public@example.com/jasnita/1',
    'error_types' => E_ALL,
    'capture_silenced_errors' => false,
];

$client = ClientBuilder::create($options)
    ->setTransport($transport)
    ->getClient();

JasnitaSdk::getCurrentHub()->bindClient($client);

echo 'Triggering "silenced" E_USER_ERROR error' . PHP_EOL;

@trigger_error('This E_USER_ERROR cannot be silenced', E_USER_ERROR);
?>
--EXPECT--
Triggering "silenced" E_USER_ERROR error
Transport called
