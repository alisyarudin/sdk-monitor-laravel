<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Http;

use Illuminate\Container\Container;
use Psr\Http\Message\ServerRequestInterface;
use Jasnita\Monitor\Sdk\Integration\RequestFetcher;
use Jasnita\Monitor\Sdk\Integration\RequestFetcherInterface;

class LaravelRequestFetcher implements RequestFetcherInterface
{
    /**
     * They key in the container where a PSR-7 instance of the current request could be stored.
     */
    public const CONTAINER_PSR7_INSTANCE_KEY = 'jasnita-laravel.psr7.request';

    public function fetchRequest(): ?ServerRequestInterface
    {
        $container = Container::getInstance();

        // If there is no request bound to the container
        // we are not dealing with a HTTP request and there
        // is no request to fetch for us so we can exit early.
        if (!$container->bound('request')) {
            return null;
        }

        if ($container->bound(self::CONTAINER_PSR7_INSTANCE_KEY)) {
            return $container->make(self::CONTAINER_PSR7_INSTANCE_KEY);
        }

        return (new RequestFetcher)->fetchRequest();
    }
}
