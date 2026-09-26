<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Tests\Fixtures\OpenTelemetry;

final class TestClientDiscoverer
{
    public function available(): bool
    {
        return true;
    }

    /**
     * @param mixed $options
     */
    public function create($options)
    {
        return new StubOtelHttpClient();
    }
}
