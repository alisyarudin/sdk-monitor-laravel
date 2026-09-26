<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Util;

use Jasnita\Monitor\Sdk\Client;
use Jasnita\Monitor\Sdk\Dsn;

/**
 * @internal
 */
final class Http
{
    public static function getJasnitaAuthHeader(Dsn $dsn, string $sdkIdentifier, string $sdkVersion): string
    {
        $authHeader = [
            'jasnita_version=' . Client::PROTOCOL_VERSION,
            'jasnita_client=' . $sdkIdentifier . '/' . $sdkVersion,
            'jasnita_key=' . $dsn->getPublicKey(),
        ];

        return 'Jasnita ' . implode(', ', $authHeader);
    }

    /**
     * @return string[]
     */
    public static function getRequestHeaders(Dsn $dsn, string $sdkIdentifier, string $sdkVersion): array
    {
        return [
            'Content-Type: application/x-jasnita-envelope',
            'X-Jasnita-Auth: ' . self::getJasnitaAuthHeader($dsn, $sdkIdentifier, $sdkVersion),
        ];
    }

    /**
     * @param string[][] $headers
     *
     * @param-out string[][] $headers
     */
    public static function parseResponseHeaders(string $headerLine, array &$headers): int
    {
        if (strpos($headerLine, ':') === false) {
            return \strlen($headerLine);
        }

        [$name, $value] = explode(':', trim($headerLine), 2);

        $name = trim($name);
        $value = trim($value);

        if (isset($headers[$name])) {
            $headers[$name][] = $value;
        } else {
            $headers[$name] = (array) $value;
        }

        return \strlen($headerLine);
    }
}
