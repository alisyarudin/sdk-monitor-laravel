<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Benchmark;

use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;

final class SpanBench
{
    /**
     * @var TransactionContext
     */
    private $context;

    /**
     * @var TransactionContext
     */
    private $contextWithTimestamp;

    public function __construct()
    {
        $this->context = TransactionContext::fromJasnitaTrace('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0');
        $this->contextWithTimestamp = TransactionContext::fromJasnitaTrace('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0');
        $this->contextWithTimestamp->setStartTimestamp(microtime(true));
    }

    /**
     * @Revs(100000)
     * @Iterations(10)
     */
    public function benchConstructor(): void
    {
        $span = new Span();
    }

    /**
     * @Revs(100000)
     * @Iterations(10)
     */
    public function benchConstructorWithInjectedContext(): void
    {
        $span = new Span($this->context);
    }

    /**
     * @Revs(100000)
     * @Iterations(10)
     */
    public function benchConstructorWithInjectedContextAndStartTimestamp(): void
    {
        $span = new Span($this->contextWithTimestamp);
    }
}
