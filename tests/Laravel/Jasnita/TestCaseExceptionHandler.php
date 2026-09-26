<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Jasnita\Monitor\Laravel\Integration;

/**
 * This is a proxy class, so we can inject the Jasnita bits while running tests and handle exceptions like "normal".
 */
class TestCaseExceptionHandler implements ExceptionHandler
{
    /** @var ExceptionHandler */
    private $handler;

    public function __construct(ExceptionHandler $handler)
    {
        $this->handler = $handler;
    }

    public function report($e)
    {
        Integration::captureUnhandledException($e);

        $this->handler->report($e);
    }

    public function shouldReport($e)
    {
        return $this->handler->shouldReport($e);
    }

    public function render($request, $e)
    {
        return $this->handler->render($request, $e);
    }

    public function renderForConsole($output, $e)
    {
        $this->handler->renderForConsole($output, $e);
    }

    public function __call($name, $arguments)
    {
        return $this->handler->{$name}(...$arguments);
    }
}
