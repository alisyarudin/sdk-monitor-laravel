<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\ClientInterface;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\DynamicSamplingContext;
use Jasnita\Monitor\Sdk\Tracing\PropagationContext;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Jasnita\Monitor\Sdk\Tracing\Transaction;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionSource;
use Jasnita\Monitor\Sdk\UserDataBag;

final class DynamicSamplingContextTest extends TestCase
{
    /**
     * @dataProvider fromHeaderDataProvider
     */
    public function testFromHeader(
        string $header,
        ?string $expectedTraceId,
        ?string $expectedPublicKey,
        ?string $expectedSampleRate,
        ?string $expectedRelease,
        ?string $expectedEnvironment,
        ?string $expectedUserSegment,
        ?string $expectedTransaction
    ): void {
        $samplingContext = DynamicSamplingContext::fromHeader($header);

        $this->assertSame($expectedTraceId, $samplingContext->get('trace_id'));
        $this->assertSame($expectedPublicKey, $samplingContext->get('public_key'));
        $this->assertSame($expectedSampleRate, $samplingContext->get('sample_rate'));
        $this->assertSame($expectedRelease, $samplingContext->get('release'));
        $this->assertSame($expectedEnvironment, $samplingContext->get('environment'));
        $this->assertSame($expectedUserSegment, $samplingContext->get('user_segment'));
        $this->assertSame($expectedTransaction, $samplingContext->get('transaction'));
    }

    public static function fromHeaderDataProvider(): \Generator
    {
        yield [
            '',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            'jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1',
            'd49d9bf66f13450b81f65bc51cf49c03',
            'public',
            '1',
            null,
            null,
            null,
            null,
        ];

        yield [
            'jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1,jasnita-release=1.0.0,jasnita-environment=test,jasnita-user_segment=my_segment,jasnita-transaction=<unlabeled transaction>',
            'd49d9bf66f13450b81f65bc51cf49c03',
            'public',
            '1',
            '1.0.0',
            'test',
            'my_segment',
            '<unlabeled transaction>',
        ];
    }

    public function testFromTransaction(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'http://public@example.com/jasnita/1',
                'release' => '1.0.0',
                'environment' => 'test',
            ]));

        $user = new UserDataBag();
        $user->setSegment('my_segment');

        $scope = new Scope();
        $scope->setUser($user);

        $hub = new Hub($client, $scope);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('foo');

        $transaction = new Transaction($transactionContext, $hub);
        $transaction->getMetadata()->setSamplingRate(1.0);

        $samplingContext = DynamicSamplingContext::fromTransaction($transaction, $hub);

        $this->assertSame((string) $transaction->getTraceId(), $samplingContext->get('trace_id'));
        $this->assertSame((string) $transaction->getMetaData()->getSamplingRate(), $samplingContext->get('sample_rate'));
        $this->assertSame('foo', $samplingContext->get('transaction'));
        $this->assertSame('public', $samplingContext->get('public_key'));
        $this->assertSame('1.0.0', $samplingContext->get('release'));
        $this->assertSame('test', $samplingContext->get('environment'));
        $this->assertSame('my_segment', $samplingContext->get('user_segment'));
        $this->assertTrue($samplingContext->isFrozen());
    }

    public function testFromTransactionSourceUrl(): void
    {
        $hub = new Hub();

        $transactionContext = new TransactionContext();
        $transactionContext->setName('/foo/bar/123');
        $transactionContext->setSource(TransactionSource::url());

        $transaction = new Transaction($transactionContext, $hub);

        $samplingContext = DynamicSamplingContext::fromTransaction($transaction, $hub);

        $this->assertNull($samplingContext->get('transaction'));
    }

    public function testFromOptions(): void
    {
        $options = new Options([
            'dsn' => 'http://public@example.com/jasnita/1',
            'release' => '1.0.0',
            'environment' => 'test',
            'traces_sample_rate' => 0.5,
        ]);

        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('21160e9b836d479f81611368b2aa3d2c'));

        $user = new UserDataBag();
        $user->setSegment('my_segment');

        $scope = new Scope();
        $scope->setUser($user);
        $scope->setPropagationContext($propagationContext);

        $samplingContext = DynamicSamplingContext::fromOptions($options, $scope);

        $this->assertSame('21160e9b836d479f81611368b2aa3d2c', $samplingContext->get('trace_id'));
        $this->assertSame('0.5', $samplingContext->get('sample_rate'));
        $this->assertSame('public', $samplingContext->get('public_key'));
        $this->assertSame('1.0.0', $samplingContext->get('release'));
        $this->assertSame('test', $samplingContext->get('environment'));
        $this->assertSame('my_segment', $samplingContext->get('user_segment'));
        $this->assertTrue($samplingContext->isFrozen());
    }

    /**
     * @dataProvider getEntriesDataProvider
     */
    public function testGetEntries(DynamicSamplingContext $samplingContext, array $expectedDynamicSamplingContext): void
    {
        $this->assertSame($expectedDynamicSamplingContext, $samplingContext->getEntries());
    }

    public static function getEntriesDataProvider(): \Generator
    {
        yield [
            DynamicSamplingContext::fromHeader(''),
            [],
        ];

        yield [
            DynamicSamplingContext::fromHeader('jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1'),
            [
                'trace_id' => 'd49d9bf66f13450b81f65bc51cf49c03',
                'public_key' => 'public',
                'sample_rate' => '1',
            ],
        ];

        yield [
            DynamicSamplingContext::fromHeader('jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1,foo=bar;foo;bar;bar=baz'),
            [
                'trace_id' => 'd49d9bf66f13450b81f65bc51cf49c03',
                'public_key' => 'public',
                'sample_rate' => '1',
            ],
        ];
    }

    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(DynamicSamplingContext $samplingContext, string $expectedString): void
    {
        $this->assertSame($expectedString, (string) $samplingContext);
    }

    public static function toStringDataProvider(): \Generator
    {
        yield [
            DynamicSamplingContext::fromHeader(''),
            '',
        ];

        yield [
            DynamicSamplingContext::fromHeader('jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1'),
            'jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1',
        ];

        yield [
            DynamicSamplingContext::fromHeader('jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1,foo=bar;foo;bar;bar=baz'),
            'jasnita-trace_id=d49d9bf66f13450b81f65bc51cf49c03,jasnita-public_key=public,jasnita-sample_rate=1',
        ];

        yield [
            DynamicSamplingContext::fromHeader('foo=bar;foo;bar;bar=baz'),
            '',
        ];
    }
}
