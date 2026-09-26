--TEST--
Test that the FatalErrorListenerIntegration integration captures only the errors allowed by the error_types option
--SKIPIF--
<?php
if (PHP_VERSION_ID >= 80500) {
    die('skip - only works for PHP 8.4 and below');
}
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Integration\FatalErrorListenerIntegration;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Transport\Result;
use Jasnita\Monitor\Sdk\Transport\ResultStatus;
use Jasnita\Monitor\Sdk\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

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

$options = new Options([
    'default_integrations' => false,
    'integrations' => [
        new FatalErrorListenerIntegration(),
    ],
]);

$client = (new ClientBuilder($options))
    ->setTransport($transport)
    ->getClient();

JasnitaSdk::getCurrentHub()->bindClient($client);

final class TestClass implements \JsonSerializable
{
}
?>
--EXPECTF--
Fatal error: Class Jasnita\Monitor\Sdk\Tests\TestClass contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (JsonSerializable::jsonSerialize) in %s on line %d
Transport called
