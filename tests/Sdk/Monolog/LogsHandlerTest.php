<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Monolog;

use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Logs\Log;
use Jasnita\Monitor\Sdk\Logs\LogLevel;
use Jasnita\Monitor\Sdk\Logs\Logs;
use Jasnita\Monitor\Sdk\Monolog\LogsHandler;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\Tests\StubTransport;

final class LogsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        Logs::getInstance()->flush();
        $client = ClientBuilder::create([
            'enable_logs' => false,
            'before_send' => static function () {
                return null; // we don't need to send the event, we are just testing the Monolog handler
            },
        ])->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle($record, Log $expectedLog): void
    {
        $handler = new LogsHandler();
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();

        $this->assertCount(1, $logs);

        $log = $logs[0];

        $this->assertEquals($expectedLog->getBody(), $log->getBody());
        $this->assertEquals($expectedLog->getLevel(), $log->getLevel());
        $this->assertEquals(
            $expectedLog->attributes()->toSimpleArray(),
            array_filter(
                $log->attributes()->toSimpleArray(),
                static function (string $key) {
                    // We are not testing Jasnita's own attributes here, only the ones the user supplied so filter them out of the expected attributes
                    return !str_starts_with($key, 'jasnita.') && $key !== 'server.address';
                },
                \ARRAY_FILTER_USE_KEY
            )
        );
    }

    /**
     * @dataProvider monologLegacyLevelDataProvider
     */
    public function testFiltersAndMapsUsingLegacyMonologThreshold(int $threshold, int $recordLevel, int $expectedCount, ?LogLevel $expectedMappedLevel): void
    {
        $handler = new LogsHandler($threshold);
        $handler->handle(RecordFactory::create('foo bar', $recordLevel, 'channel.foo', [], []));

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount($expectedCount, $logs);

        if ($expectedMappedLevel !== null) {
            $this->assertEquals($expectedMappedLevel, $logs[0]->getLevel());
        }
    }

    /**
     * @dataProvider monologLevelDataProvider
     */
    public function testFiltersAndMapsUsingMonologEnumThreshold($threshold, $recordLevel, int $expectedCount, ?LogLevel $expectedMappedLevel): void
    {
        if (!class_exists(Level::class)) {
            $this->markTestSkipped('Test only works for Monolog >= 3');
        }

        $this->assertInstanceOf(Level::class, $threshold);
        $this->assertInstanceOf(Level::class, $recordLevel);

        $handler = new LogsHandler($threshold);
        $handler->handle(RecordFactory::create('foo bar', $recordLevel->value, 'channel.foo', [], []));

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount($expectedCount, $logs);

        if ($expectedMappedLevel !== null) {
            $this->assertEquals($expectedMappedLevel, $logs[0]->getLevel());
        }
    }

    public function testLogsHandlerDestructor(): void
    {
        $transport = new StubTransport();
        $client = ClientBuilder::create([
            'enable_logs' => false,
        ])->setTransport($transport)
            ->getClient();

        $hub = new Hub($client);
        JasnitaSdk::setCurrentHub($hub);

        $this->handleLogAndDrop();

        $this->assertCount(1, StubTransport::$events);
        $this->assertSame('I was dropped :(', StubTransport::$events[0]->getLogs()[0]->getBody());
    }

    private function handleLogAndDrop(): void
    {
        $handler = new LogsHandler();
        $handler->handle(RecordFactory::create('I was dropped :(', Logger::INFO, 'chanel.foo', [], []));
    }

    public function testOriginTagAppliedWithHandler(): void
    {
        $handler = new LogsHandler(LogLevel::warn());
        $handler->handle(RecordFactory::create('with origin', Logger::WARNING, 'channel.foo', [], []));

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertArrayHasKey('jasnita.origin', $log->attributes()->toSimpleArray());
        $this->assertSame('auto.log.monolog', $log->attributes()->toSimpleArray()['jasnita.origin']);
    }

    public function testOriginTagNotAppliedWhenUsingDirectly(): void
    {
        \Jasnita\Monitor\Sdk\logger()->info('No origin attribute');

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertSame('No origin attribute', $log->getBody());
        $this->assertArrayNotHasKey('jasnita.origin', $log->attributes()->toSimpleArray());
    }

    public static function handleDataProvider(): iterable
    {
        yield [
            RecordFactory::create(
                'foo bar',
                Logger::DEBUG,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::debug(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::info(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::NOTICE,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::info(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::warn(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::ERROR,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::error(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::CRITICAL,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::ALERT,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::EMERGENCY,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
                []
            ),
            (new Log(123, 'abc', LogLevel::info(), 'foo bar'))
                ->setAttribute('foo', 'bar')
                ->setAttribute('bar', 'baz'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [],
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ]
            ),
            (new Log(123, 'abc', LogLevel::info(), 'foo bar'))
                ->setAttribute('foo', 'bar')
                ->setAttribute('bar', 'baz'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [
                    'foo' => 'bar',
                ],
                [
                    'bar' => 'baz',
                ]
            ),
            (new Log(123, 'abc', LogLevel::info(), 'foo bar'))
                ->setAttribute('foo', 'bar')
                ->setAttribute('bar', 'baz'),
        ];
    }

    public static function monologLegacyLevelDataProvider(): iterable
    {
        yield 'NOTICE threshold drops INFO (both map to jasnita info)' => [
            Logger::NOTICE,
            Logger::INFO,
            0,
            null,
        ];

        yield 'NOTICE threshold keeps NOTICE (mapped to jasnita info)' => [
            Logger::NOTICE,
            Logger::NOTICE,
            1,
            LogLevel::info(),
        ];

        yield 'NOTICE threshold keeps WARNING (mapped to jasnita warn)' => [
            Logger::NOTICE,
            Logger::WARNING,
            1,
            LogLevel::warn(),
        ];

        yield 'ALERT threshold drops CRITICAL (both map to jasnita fatal)' => [
            Logger::ALERT,
            Logger::CRITICAL,
            0,
            null,
        ];

        yield 'ALERT threshold keeps ALERT (mapped to jasnita fatal)' => [
            Logger::ALERT,
            Logger::ALERT,
            1,
            LogLevel::fatal(),
        ];

        yield 'ALERT threshold keeps EMERGENCY (mapped to jasnita fatal)' => [
            Logger::ALERT,
            Logger::EMERGENCY,
            1,
            LogLevel::fatal(),
        ];

        yield 'EMERGENCY threshold drops ALERT (both map to jasnita fatal)' => [
            Logger::EMERGENCY,
            Logger::ALERT,
            0,
            null,
        ];

        yield 'EMERGENCY threshold keeps EMERGENCY (mapped to jasnita fatal)' => [
            Logger::EMERGENCY,
            Logger::EMERGENCY,
            1,
            LogLevel::fatal(),
        ];
    }

    public static function monologLevelDataProvider(): iterable
    {
        if (!class_exists(Level::class)) {
            yield 'Monolog < 3 (skipped)' => [null, null, 0, null];

            return;
        }

        yield 'NOTICE threshold drops INFO (both map to jasnita info)' => [
            Level::Notice,
            Level::Info,
            0,
            null,
        ];

        yield 'NOTICE threshold keeps NOTICE (mapped to jasnita info)' => [
            Level::Notice,
            Level::Notice,
            1,
            LogLevel::info(),
        ];

        yield 'NOTICE threshold keeps WARNING (mapped to jasnita warn)' => [
            Level::Notice,
            Level::Warning,
            1,
            LogLevel::warn(),
        ];

        yield 'ALERT threshold drops CRITICAL (both map to jasnita fatal)' => [
            Level::Alert,
            Level::Critical,
            0,
            null,
        ];

        yield 'ALERT threshold keeps ALERT (mapped to jasnita fatal)' => [
            Level::Alert,
            Level::Alert,
            1,
            LogLevel::fatal(),
        ];

        yield 'ALERT threshold keeps EMERGENCY (mapped to jasnita fatal)' => [
            Level::Alert,
            Level::Emergency,
            1,
            LogLevel::fatal(),
        ];

        yield 'EMERGENCY threshold drops ALERT (both map to jasnita fatal)' => [
            Level::Emergency,
            Level::Alert,
            0,
            null,
        ];

        yield 'EMERGENCY threshold keeps EMERGENCY (mapped to jasnita fatal)' => [
            Level::Emergency,
            Level::Emergency,
            1,
            LogLevel::fatal(),
        ];
    }
}
