<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tracing;

use Illuminate\Contracts\View\Engine;
use Illuminate\View\Factory;
use Jasnita\Monitor\Laravel\Integration;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Tracing\SpanContext;

final class ViewEngineDecorator implements Engine
{
    public const SHARED_KEY = '__jasnita_tracing_view_name';

    /** @var Engine */
    private $engine;

    /** @var Factory */
    private $viewFactory;

    public function __construct(Engine $engine, Factory $viewFactory)
    {
        $this->engine = $engine;
        $this->viewFactory = $viewFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function get($path, array $data = []): string
    {
        $parentSpan = Integration::currentTracingSpan();

        if ($parentSpan === null) {
            return $this->engine->get($path, $data);
        }

        $context = new SpanContext();
        $context->setOp('view.render');
        $context->setDescription($this->viewFactory->shared(self::SHARED_KEY, basename($path)));

        $span = $parentSpan->startChild($context);

        JasnitaSdk::getCurrentHub()->setSpan($span);

        $result = $this->engine->get($path, $data);

        $span->finish();

        JasnitaSdk::getCurrentHub()->setSpan($parentSpan);

        return $result;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->engine, $name], $arguments);
    }
}
