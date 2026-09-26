--TEST--
Test that the ErrorListenerIntegration integration captures only the errors allowed by the `error_types` options
--INI--
error_reporting=E_ALL
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\Integration\ErrorListenerIntegration;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Response;
use Jasnita\Monitor\Sdk\ResponseStatus;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Transport\TransportFactoryInterface;
use Jasnita\Monitor\Sdk\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
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

$options = new Options([
    'error_types' => E_ALL & ~E_USER_WARNING,
    'default_integrations' => false,
    'integrations' => [
        new ErrorListenerIntegration(),
    ],
]);

$client = (new ClientBuilder($options))
    ->setTransportFactory($transportFactory)
    ->getClient();

JasnitaSdk::getCurrentHub()->bindClient($client);

trigger_error('Error thrown', E_USER_NOTICE);
trigger_error('Error thrown', E_USER_WARNING);
?>
--EXPECTF--
Transport called

Notice: Error thrown in %s on line %d

Warning: Error thrown in %s on line %d
