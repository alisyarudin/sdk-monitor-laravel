--TEST--
Test that when handling an OOM error with large breadcrumbs, breadcrumb metadata is stripped to prevent secondary OOM during serialization
--SKIPIF--
<?php
if (PHP_VERSION_ID >= 80500) {
    die('skip - only works for PHP 8.4 and below');
}
--INI--
memory_limit=67108864
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use Jasnita\Monitor\Sdk\Breadcrumb;
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
        $breadcrumbs = $event->getBreadcrumbs();
        echo 'Breadcrumb count: ' . \count($breadcrumbs) . \PHP_EOL;

        if (\count($breadcrumbs) > 0) {
            $firstBreadcrumb = $breadcrumbs[0];
            echo 'First breadcrumb category: ' . $firstBreadcrumb->getCategory() . \PHP_EOL;
            echo 'First breadcrumb has metadata: ' . (empty($firstBreadcrumb->getMetadata()) ? 'no' : 'yes') . \PHP_EOL;
        }

        $this->payloadSerializer->serialize($event);

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

// Add 100 breadcrumbs with ~100KB metadata each to simulate the real-world scenario
$hub = JasnitaSdk::getCurrentHub();
$hub->configureScope(function (\Jasnita\Monitor\Sdk\State\Scope $scope): void {
    for ($i = 0; $i < 100; ++$i) {
        $scope->addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'db.query',
            'SELECT * FROM large_table WHERE id = ?',
            ['bindings' => str_repeat('x', 100 * 1024)]
        ));
    }
});

// Trigger OOM - the remaining memory after breadcrumbs is limited
$array = [];
for ($i = 0; $i < 100000000; ++$i) {
    $array[] = 'jasnita';
}
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Breadcrumb count: 100
First breadcrumb category: db.query
First breadcrumb has metadata: no
Transport called
