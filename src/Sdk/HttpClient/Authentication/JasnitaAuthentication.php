<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\HttpClient\Authentication;

use Http\Message\Authentication as AuthenticationInterface;
use Psr\Http\Message\RequestInterface;
use Jasnita\Monitor\Sdk\Client;
use Jasnita\Monitor\Sdk\Options;

/**
 * This authentication method sends the requests along with a X-Jasnita-Auth
 * header.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class JasnitaAuthentication implements AuthenticationInterface
{
    /**
     * @var Options The Jasnita client configuration
     */
    private $options;

    /**
     * @var string The SDK identifier
     */
    private $sdkIdentifier;

    /**
     * @var string The SDK version
     */
    private $sdkVersion;

    /**
     * Constructor.
     *
     * @param Options $options       The Jasnita client configuration
     * @param string  $sdkIdentifier The Jasnita SDK identifier in use
     * @param string  $sdkVersion    The Jasnita SDK version in use
     */
    public function __construct(Options $options, string $sdkIdentifier, string $sdkVersion)
    {
        $this->options = $options;
        $this->sdkIdentifier = $sdkIdentifier;
        $this->sdkVersion = $sdkVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $dsn = $this->options->getDsn();

        if (null === $dsn) {
            return $request;
        }

        $data = [
            'jasnita_version' => Client::PROTOCOL_VERSION,
            'jasnita_client' => $this->sdkIdentifier . '/' . $this->sdkVersion,
            'jasnita_key' => $dsn->getPublicKey(),
        ];

        if (null !== $dsn->getSecretKey()) {
            $data['jasnita_secret'] = $dsn->getSecretKey();
        }

        $headers = [];

        foreach ($data as $headerKey => $headerValue) {
            $headers[] = $headerKey . '=' . $headerValue;
        }

        return $request->withHeader('X-Jasnita-Auth', 'Jasnita ' . implode(', ', $headers));
    }
}
