--TEST--
Test that capturing an exception with a callable looking argument does not trigger an deprecation "Deprecated: Use of "static" in callables is deprecated"
--INI--
error_reporting=E_ALL
zend.exception_ignore_args=0
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use Exception;
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

$options = [
    'dsn' => 'http://public@example.com/jasnita/1',
];

$client = ClientBuilder::create($options)
    ->setTransport($transport)
    ->getClient();

JasnitaSdk::getCurrentHub()->bindClient($client);

class Foo {
    function __construct(string $bar) {
        JasnitaSdk::getCurrentHub()->captureException(new Exception('doh!'));
    }
}

new Foo('static::sort');

echo 'Triggered capture exception' . PHP_EOL;
?>
--EXPECT--
Transport called
Triggered capture exception
