--TEST--
Test that requiring a broken class (with a parser error) will not explode during serialization
--FILE--
<?php

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests;

use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Serializer\Serializer;
use Jasnita\Monitor\Sdk\Tests\Fixtures\code\BrokenClass;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

// issue present itself in backtrace serialization, see:
// - (dokumentasi hulu)
// - (dokumentasi hulu)
function testSerialization($value) {
    $serializer = new Serializer(new Options());

    echo json_encode($serializer->serialize($value));
}

testSerialization(BrokenClass::class . '::brokenMethod');
echo PHP_EOL;
testSerialization([BrokenClass::class, 'brokenMethod']);

?>
--EXPECT--
"Jasnita\\Monitor\\Sdk\\Tests\\Fixtures\\code\\BrokenClass::brokenMethod"
["Jasnita\\Monitor\\Sdk\\Tests\\Fixtures\\code\\BrokenClass","brokenMethod"]
