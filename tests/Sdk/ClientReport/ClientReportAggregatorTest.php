<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\ClientReport;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Client;
use Jasnita\Monitor\Sdk\ClientReport\ClientReportAggregator;
use Jasnita\Monitor\Sdk\ClientReport\Reason;
use Jasnita\Monitor\Sdk\Options;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\Tests\StubLogger;
use Jasnita\Monitor\Sdk\Tests\StubTransport;
use Jasnita\Monitor\Sdk\Transport\DataCategory;

class ClientReportAggregatorTest extends TestCase
{
    protected function setUp(): void
    {
        ini_set('zend.exception_ignore_args', '0');
        StubTransport::$events = [];
        StubLogger::$logs = [];
        JasnitaSdk::init()->bindClient(new Client(new Options([
            'logger' => StubLogger::getInstance(),
        ]), StubTransport::getInstance()));
    }

    public function testAddClientReport(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::error(), Reason::beforeSend(), 10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertCount(1, StubTransport::$events);
        $reports = StubTransport::$events[0]->getClientReports();
        $this->assertCount(2, $reports);

        $report = $reports[0];
        $this->assertSame(DataCategory::profile()->getValue(), $report->getCategory());
        $this->assertSame(Reason::eventProcessor()->getValue(), $report->getReason());
        $this->assertSame(10, $report->getQuantity());

        $report = $reports[1];
        $this->assertSame(DataCategory::error()->getValue(), $report->getCategory());
        $this->assertSame(Reason::beforeSend()->getValue(), $report->getReason());
        $this->assertSame(10, $report->getQuantity());
    }

    public function testClientReportAggregation(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertCount(1, StubTransport::$events);
        $reports = StubTransport::$events[0]->getClientReports();
        $this->assertCount(1, $reports);

        $report = $reports[0];
        $this->assertSame(DataCategory::profile()->getValue(), $report->getCategory());
        $this->assertSame(Reason::eventProcessor()->getValue(), $report->getReason());
        $this->assertSame(40, $report->getQuantity());
    }

    public function testFlushDoesNotOverwriteLastEventId(): void
    {
        $hub = JasnitaSdk::getCurrentHub();
        $eventId = $hub->captureMessage('foo');

        $this->assertNotNull($eventId);
        $this->assertSame($eventId, $hub->getLastEventId());

        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertSame($eventId, $hub->getLastEventId());
    }

    public function testNegativeQuantityDiscarded(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), -10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertNotEmpty(StubLogger::$logs);
        $this->assertSame(['level' => 'debug', 'message' => 'Dropping Client report with category={category} and reason={reason} because quantity is zero or negative ({quantity})', 'context' => ['category' => 'profile', 'reason' => 'event_processor', 'quantity' => -10]], StubLogger::$logs[0]);
    }

    public function testZeroQuantityDiscarded(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 0);
        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertCount(1, StubLogger::$logs);
        $this->assertSame(['level' => 'debug', 'message' => 'Dropping Client report with category={category} and reason={reason} because quantity is zero or negative ({quantity})', 'context' => ['category' => 'profile', 'reason' => 'event_processor', 'quantity' => 0]], StubLogger::$logs[0]);
    }

    public function testNegativeQuantityDiscardedWhenNoClientIsBound(): void
    {
        JasnitaSdk::setCurrentHub(new Hub());

        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), -10);

        JasnitaSdk::setCurrentHub(new Hub(new Client(new Options([
            'logger' => StubLogger::getInstance(),
        ]), StubTransport::getInstance())));

        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertEmpty(StubLogger::$logs);
    }
}
