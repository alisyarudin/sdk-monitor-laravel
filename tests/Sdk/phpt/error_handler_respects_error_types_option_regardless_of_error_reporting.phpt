--TEST--
Test that the error handler reports errors set by the `error_types` option and not the `error_reporting` level.
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Response;
use Jasnita\Monitor\Sdk\ResponseStatus;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Transport\TransportFactoryInterface;
use Jasnita\Monitor\Sdk\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transportFactory = new class implements TransportFactoryInterface {
    public function create(Options $options): TransportInterface
    {
        return new class implements TransportInterface {
            public function send(Event $event): PromiseInterface
            {
                echo 'Transport called' . PHP_EOL;

                return new FulfilledPromise(new Response(ResponseStatus::success()));
            }

            public function close(?int $timeout = null): PromiseInterface
            {
                return new FulfilledPromise(true);
            }
        };
    }
};

error_reporting(E_ALL & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_ERROR);

$options = [
    'dsn' => 'http://public@example.com/jasnita/1',
    'error_types' => E_ALL & ~E_USER_WARNING,
];

$client = ClientBuilder::create($options)
    ->setTransportFactory($transportFactory)
    ->getClient();

JasnitaSdk::getCurrentHub()->bindClient($client);

echo 'Triggering E_USER_NOTICE error' . PHP_EOL;

trigger_error('Error thrown', E_USER_NOTICE);

echo 'Triggering E_USER_WARNING error (it should not be reported to Jasnita due to error_types option)' . PHP_EOL;

trigger_error('Error thrown', E_USER_WARNING);

echo 'Triggering E_USER_ERROR error (unsilenceable on PHP8)' . PHP_EOL;

trigger_error('Error thrown', E_USER_ERROR);
?>
--EXPECT--
Triggering E_USER_NOTICE error
Transport called
Triggering E_USER_WARNING error (it should not be reported to Jasnita due to error_types option)
Triggering E_USER_ERROR error (unsilenceable on PHP8)
Transport called
