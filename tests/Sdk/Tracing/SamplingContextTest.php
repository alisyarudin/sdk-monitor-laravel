<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Jasnita\Monitor\Sdk\Tracing\SamplingContext;
use Jasnita\Monitor\Sdk\Tracing\TransactionContext;

final class SamplingContextTest extends TestCase
{
    public function testGetDefault(): void
    {
        $transactionContext = new TransactionContext(TransactionContext::DEFAULT_NAME, true);
        $samplingContext = SamplingContext::getDefault($transactionContext);

        $this->assertSame($transactionContext, $samplingContext->getTransactionContext());
        $this->assertSame($transactionContext->getParentSampled(), $samplingContext->getParentSampled());
    }
}
