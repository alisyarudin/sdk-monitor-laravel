<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Serializer;

use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\Serializer\AbstractSerializer;
use Jasnita\Monitor\Sdk\Serializer\RepresentationSerializer;

final class RepresentationSerializerTest extends AbstractSerializerTest
{
    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testArraysAreArrays(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $this->invokeSerialization($serializer, [1, 2, 3]);

        $this->assertSame(['1', '2', '3'], $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testIntsBecomeStrings(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $serializer->representationSerialize(1);

        $this->assertIsString($result);
        $this->assertSame('1', $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testFloatsBecomeStrings(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $serializer->representationSerialize(1.5);

        $this->assertIsString($result);
        $this->assertSame('1.5', $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testBooleansBecomeStrings(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $serializer->representationSerialize(true);

        $this->assertSame('true', $result);

        $result = $serializer->representationSerialize(false);

        $this->assertIsString($result);
        $this->assertSame('false', $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testNullsBecomeString(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $serializer->representationSerialize(null);

        $this->assertIsString($result);
        $this->assertSame('null', $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testSerializeRoundedFloat(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $serializer->representationSerialize((float) 1);

        $this->assertIsString($result);
        $this->assertSame('1.0', $result);

        $result = $serializer->representationSerialize(floor(5 / 2));

        $this->assertIsString($result);
        $this->assertSame('2.0', $result);

        $result = $serializer->representationSerialize(floor(12345.678901234));

        $this->assertIsString($result);
        $this->assertSame('12345.0', $result);
    }

    /**
     * @return RepresentationSerializer
     */
    protected function createSerializer(): AbstractSerializer
    {
        return new RepresentationSerializer(new Options());
    }
}
