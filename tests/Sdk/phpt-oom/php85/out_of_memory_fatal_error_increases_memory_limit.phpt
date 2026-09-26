--TEST--
Test that when handling a out of memory error the memory limit is increased with 5 MiB and the event is serialized and ready to be sent
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip - only works for PHP 8.5 and above');
}
--INI--
memory_limit=67108864
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Serializer\PayloadSerializer;
use Jasnita\Monitor\Sdk\Serializer\PayloadSerializerInterface;
use Jasnita\Monitor\Sdk\Transport\Result;
use Jasnita\Monitor\Sdk\Transport\ResultStatus;
use Jasnita\Monitor\Sdk\Transport\TransportInterface;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$options = new Options([
    'dsn' => 'http://public@example.com/jasnita/1',
]);

$transport = new class(new PayloadSerializer($options)) implements TransportInterface {
    private $payloadSerializer;

    public function __construct(PayloadSerializerInterface $payloadSerializer)
    {
        $this->payloadSerializer = $payloadSerializer;
    }

    public function send(Event $event): Result
    {
        $serialized = $this->payloadSerializer->serialize($event);

        echo 'Transport called' . \PHP_EOL;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
};

$options->setTransport($transport);

$client = (new ClientBuilder($options))->getClient();

JasnitaSdk::init()->bindClient($client);

echo 'Before OOM memory limit: ' . \ini_get('memory_limit');

register_shutdown_function(function () {
    echo 'After OOM memory limit: ' . \ini_get('memory_limit');
});

$array = [];
for ($i = 0; $i < 100000000; ++$i) {
    $array[] = 'jasnita';
}
--EXPECTF--
Before OOM memory limit: 67108864
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Stack trace:
%A
Transport called
After OOM memory limit: 72351744
