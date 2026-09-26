<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tracing;

use Illuminate\Contracts\View\Engine;
use Illuminate\View\Factory;
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
        $parentSpan = JasnitaSdk::getCurrentHub()->getSpan();

        // If there is no sampled span there is no need to wrap the engine call
        if ($parentSpan === null || !$parentSpan->getSampled()) {
            return $this->engine->get($path, $data);
        }

        $span = $parentSpan->startChild(
            SpanContext::make()
                ->setOp('view.render')
                ->setOrigin('auto.view')
                ->setDescription($this->viewFactory->shared(self::SHARED_KEY, basename($path)))
        );

        JasnitaSdk::getCurrentHub()->setSpan($span);

        $result = $this->engine->get($path, $data);

        $span->finish();

        JasnitaSdk::getCurrentHub()->setSpan($parentSpan);

        return $result;
    }

    public function __call($name, $arguments)
    {
        return $this->engine->{$name}(...$arguments);
    }
}
