<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Logs;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Client;
use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Logs\LogLevel;
use Jasnita\Monitor\Sdk\Logs\LogsAggregator;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tests\StubLogger;
use Jasnita\Monitor\Sdk\Tests\StubTransport;
use Jasnita\Monitor\Sdk\Tracing\PropagationContext;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;
use Jasnita\Monitor\Sdk\Tracing\SpanId;
use Jasnita\Monitor\Sdk\Tracing\TraceId;
use Jasnita\Monitor\Sdk\UserDataBag;

final class LogsAggregatorTest extends TestCase
{
    /**
     * This test is kept simple to ensure the `LogAggregator` is able to handle attributes passed in different formats.
     *
     * Extensive testing of attributes is done in the `Attributes/*` test classes.
     *
     * @dataProvider attributesDataProvider
     */
    public function testAttributes(array $attributes, array $expected): void
    {
        $client = ClientBuilder::create()->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), 'Test message', [], $attributes);

        $logs = $aggregator->all();

        $this->assertCount(1, $logs);

        $log = $logs[0];

        $this->assertSame(
            $expected,
            array_filter(
                $log->attributes()->toSimpleArray(),
                static function (string $key) {
                    // We are not testing internal Jasnita attributes here, only the ones the user supplied
                    return !str_starts_with($key, 'jasnita.') && $key !== 'server.address';
                },
                \ARRAY_FILTER_USE_KEY
            )
        );
    }

    public static function attributesDataProvider(): \Generator
    {
        yield [
            [],
            [],
        ];

        yield [
            ['foo', 'bar'],
            [],
        ];

        yield [
            ['foo' => 'bar'],
            ['foo' => 'bar'],
        ];

        yield [
            ['foo' => ['bar']],
            ['foo' => '["bar"]'],
        ];
    }

    /**
     * @dataProvider messageFormattingDataProvider
     */
    public function testMessageFormatting(string $message, array $values, string $expected): void
    {
        $client = ClientBuilder::create()->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), $message, $values);

        $logs = $aggregator->all();

        $this->assertCount(1, $logs);

        $log = $logs[0];

        $this->assertSame($expected, $log->getBody());

        if (\count($values)) {
            $this->assertNotNull($log->attributes()->get('jasnita.message.template'));
        } else {
            $this->assertNull($log->attributes()->get('jasnita.message.template'));
        }
    }

    public static function messageFormattingDataProvider(): \Generator
    {
        yield [
            'Simple message without values',
            [],
            'Simple message without values',
        ];

        yield [
            'Message with a value: %s',
            ['value'],
            'Message with a value: value',
        ];

        yield [
            'Message with placeholders but no values: %s',
            [],
            'Message with placeholders but no values: %s',
        ];

        yield [
            'Message with placeholders but incorrect number of values: %s, %s',
            ['value'],
            'Message with placeholders but incorrect number of values: %s, %s',
        ];

        yield [
            'Message with a percentage: 42%',
            [],
            'Message with a percentage: 42%',
        ];

        // This test case is a bit of an odd one, you would not expect this to happen in practice unless the user intended
        // to format the message but did not add the proper placeholder. On PHP 8+ this will return the message as is, but
        // on PHP 7 it will return the message without the percentage sign because some processing is done by `vsprintf`.
        // You would however more likely expect the previous test case where no values are provided when no placeholders are present
        yield [
            'Message with a percentage: 42%',
            ['value'],
            \PHP_VERSION_ID >= 80000
                ? 'Message with a percentage: 42%'
                : 'Message with a percentage: 42',
        ];
    }

    public function testAttributesAreAddedToLogMessage(): void
    {
        $client = ClientBuilder::create([
            'send_default_pii' => true,
            'release' => '1.0.0',
            'environment' => 'production',
            'server_name' => 'web-server-01',
        ])->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $hub->configureScope(static function (Scope $scope) {
            $userDataBag = new UserDataBag();
            $userDataBag->setId('unique_id');
            $userDataBag->setEmail('foo@example.com');
            $userDataBag->setUsername('my_user');
            $scope->setUser($userDataBag);
        });

        $spanContext = new SpanContext();
        $spanContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $spanContext->setSpanId(new SpanId('566e3688a61d4bc8'));
        $span = new Span($spanContext);
        $hub->setSpan($span);

        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), 'User %s performed action %s', [
            'testuser', 'login',
        ]);

        $logs = $aggregator->all();
        $this->assertCount(1, $logs);

        $log = $logs[0];
        $attributes = $log->attributes();

        $this->assertSame('1.0.0', $attributes->get('jasnita.release')->getValue());
        $this->assertSame('production', $attributes->get('jasnita.environment')->getValue());
        $this->assertSame('web-server-01', $attributes->get('server.address')->getValue());
        $this->assertSame('User %s performed action %s', $attributes->get('jasnita.message.template')->getValue());
        $this->assertSame('566e3688a61d4bc8', $attributes->get('jasnita.trace.parent_span_id')->getValue());
        $this->assertSame('jasnita.monitor.php', $attributes->get('jasnita.sdk.name')->getValue());
        $this->assertSame(Client::SDK_VERSION, $attributes->get('jasnita.sdk.version')->getValue());
        $this->assertSame('unique_id', $attributes->get('user.id')->getValue());
        $this->assertSame('foo@example.com', $attributes->get('user.email')->getValue());
        $this->assertSame('my_user', $attributes->get('user.name')->getValue());
    }

    public function testUserAttributesCanBeSetManuallyWithDefaultPiiOff(): void
    {
        $client = ClientBuilder::create([
            'send_default_pii' => false,
        ])->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $hub->configureScope(static function (Scope $scope) {
            $userDataBag = new UserDataBag();
            $userDataBag->setId('unique_id');
            $userDataBag->setEmail('foo@example.com');
            $userDataBag->setUsername('my_user');
            $scope->setUser($userDataBag);
        });

        $aggregator = new LogsAggregator();
        $aggregator->add(LogLevel::info(), 'User performed action');

        $logs = $aggregator->all();
        $this->assertCount(1, $logs);

        $attributes = $logs[0]->attributes();

        $this->assertSame('unique_id', $attributes->get('user.id')->getValue());
        $this->assertSame('foo@example.com', $attributes->get('user.email')->getValue());
        $this->assertSame('my_user', $attributes->get('user.name')->getValue());
    }

    public function testBeforeSendLogExceptionDropsLogAndIsLogged(): void
    {
        StubLogger::$logs = [];
        $client = ClientBuilder::create([
            'before_send_log' => static function (): void {
                throw new \RuntimeException('test');
            },
            'logger' => StubLogger::getInstance(),
        ])->getClient();
        JasnitaSdk::setCurrentHub(new Hub($client));
        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), 'Test message');

        $this->assertEmpty($aggregator->all());
        $this->assertContains([
            'level' => 'error',
            'message' => 'The "before_send_log" callback failed with exception: "test".',
            'context' => [],
        ], StubLogger::$logs);
    }

    public function testFlushesImmediatelyWhenThresholdIsReached(): void
    {
        StubTransport::$events = [];

        $transport = new StubTransport();
        $client = ClientBuilder::create([
            'log_flush_threshold' => 2,
        ])->setTransport($transport)->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), 'First message');

        $this->assertCount(1, $aggregator->all());
        $this->assertCount(0, StubTransport::$events);

        $aggregator->add(LogLevel::warn(), 'Second message');

        $this->assertCount(0, $aggregator->all());
        $this->assertCount(1, StubTransport::$events);
        $this->assertCount(2, StubTransport::$events[0]->getLogs());
        $this->assertSame('First message', StubTransport::$events[0]->getLogs()[0]->getBody());
        $this->assertSame('Second message', StubTransport::$events[0]->getLogs()[1]->getBody());
    }

    public function testDoesNotFlushImmediatelyWhenThresholdIsNull(): void
    {
        StubTransport::$events = [];

        $transport = new StubTransport();
        $client = ClientBuilder::create([
            'log_flush_threshold' => null,
        ])->setTransport($transport)->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), 'First message');
        $aggregator->add(LogLevel::warn(), 'Second message');

        $this->assertCount(2, $aggregator->all());
        $this->assertCount(0, StubTransport::$events);
    }

    public function testDoesNotUsePropagationContextSpanIdAsParentSpanIdWhenNoLocalSpanExists(): void
    {
        $client = ClientBuilder::create()->getClient();

        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('771a43a4192642f0b136d5159a501700'));
        $propagationContext->setSpanId(new SpanId('1234567890abcdef'));

        $hub = new Hub($client, new Scope($propagationContext));
        JasnitaSdk::setCurrentHub($hub);

        $aggregator = new LogsAggregator();
        $aggregator->add(LogLevel::info(), 'Test message');

        $logs = $aggregator->all();
        $this->assertCount(1, $logs);
        $this->assertSame('771a43a4192642f0b136d5159a501700', $logs[0]->getTraceId());

        $parentSpanId = $logs[0]->attributes()->get('jasnita.trace.parent_span_id');
        $this->assertNotNull($parentSpanId);
        // Log attributes normalize null values to the string "null".
        $this->assertSame('null', $parentSpanId->getValue());
    }

    public function testUsesExternalPropagationContextWhenNoLocalSpanExists(): void
    {
        $client = ClientBuilder::create()->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        $aggregator = new LogsAggregator();
        $aggregator->add(LogLevel::info(), 'Test message');

        $logs = $aggregator->all();
        $this->assertCount(1, $logs);
        $this->assertSame('771a43a4192642f0b136d5159a501700', $logs[0]->getTraceId());
        $this->assertSame('1234567890abcdef', $logs[0]->attributes()->get('jasnita.trace.parent_span_id')->getValue());

        Scope::clearExternalPropagationContext();
    }
}
