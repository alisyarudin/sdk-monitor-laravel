<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Tracing\DynamicSamplingContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionMetadata;
use Jasnita\Monitor\Sdk\Tracing\TransactionSource;

final class TransactionMetadataTest extends TestCase
{
    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(TransactionMetadata $transactionMetadata, $expectedSamplingRate, ?DynamicSamplingContext $expectedDynamicSamplingContext, ?TransactionSource $expectedSource): void
    {
        $this->assertSame($expectedSamplingRate, $transactionMetadata->getSamplingRate());
        $this->assertSame($expectedDynamicSamplingContext, $transactionMetadata->getDynamicSamplingContext());
        $this->assertSame($expectedSource, $transactionMetadata->getSource());
    }

    public static function constructorDataProvider(): \Generator
    {
        $samplingContext = DynamicSamplingContext::fromHeader('jasnita-public_key=public,jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-sample_rate=1');
        $source = TransactionSource::custom();

        yield [
            new TransactionMetadata(),
            null,
            null,
            $source,
        ];

        yield [
            new TransactionMetadata(1),
            1,
            null,
            $source,
        ];

        yield [
            new TransactionMetadata(
                null,
                $samplingContext
            ),
            null,
            $samplingContext,
            $source,
        ];

        yield [
            new TransactionMetadata(
                null,
                null,
                $source
            ),
            null,
            null,
            $source,
        ];

        yield [
            new TransactionMetadata(
                0.5,
                $samplingContext,
                $source
            ),
            0.5,
            $samplingContext,
            TransactionSource::custom(),
        ];
    }

    public function testGettersAndSetters(): void
    {
        $samplingContext = DynamicSamplingContext::fromHeader('jasnita-public_key=public,jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-sample_rate=1');
        $source = TransactionSource::task();

        $transactionMetadata = new TransactionMetadata();
        $transactionMetadata->setSamplingRate(0);
        $transactionMetadata->setDynamicSamplingContext($samplingContext);
        $transactionMetadata->setSource($source);

        $this->assertSame(0, $transactionMetadata->getSamplingRate());
        $this->assertSame($samplingContext, $transactionMetadata->getDynamicSamplingContext());
        $this->assertSame($source, $transactionMetadata->getSource());
    }
}
