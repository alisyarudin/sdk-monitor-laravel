<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\HttpClient;

use Jasnita\Monitor\Sdk\Options;

interface HttpClientInterface
{
    public function sendRequest(Request $request, Options $options): Response;
}
