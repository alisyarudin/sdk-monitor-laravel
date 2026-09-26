<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\HttpClient;

final class Request
{
    /**
     * @var string
     */
    private $stringBody;

    public function hasStringBody(): bool
    {
        return $this->stringBody !== null;
    }

    public function getStringBody(): ?string
    {
        return $this->stringBody;
    }

    public function setStringBody(string $stringBody): void
    {
        $this->stringBody = $stringBody;
    }
}
