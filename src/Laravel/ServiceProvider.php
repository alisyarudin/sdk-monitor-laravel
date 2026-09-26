<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel;

use Jasnita\Monitor\Sdk\Logs\Logs;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Foundation\Application as Laravel;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Laravel\Lumen\Application as Lumen;
use RuntimeException;
use Jasnita\Monitor\Sdk\ClientBuilder;
use Jasnita\Monitor\Sdk\Event;
use Jasnita\Monitor\Sdk\EventHint;
use Jasnita\Monitor\Sdk\Integration as SdkIntegration;
use Jasnita\Monitor\Laravel\Console\AboutCommandIntegration;
use Jasnita\Monitor\Laravel\Console\PublishCommand;
use Jasnita\Monitor\Laravel\Console\TestCommand;
use Jasnita\Monitor\Laravel\Features\Feature;
use Jasnita\Monitor\Laravel\Http\FlushEventsMiddleware;
use Jasnita\Monitor\Laravel\Http\LaravelRequestFetcher;
use Jasnita\Monitor\Laravel\Http\SetRequestIpMiddleware;
use Jasnita\Monitor\Laravel\Http\SetRequestMiddleware;
use Jasnita\Monitor\Laravel\Tracing\BacktraceHelper;
use Jasnita\Monitor\Laravel\Tracing\ServiceProvider as TracingServiceProvider;
use Jasnita\Monitor\Sdk\Logger\DebugFileLogger;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Jasnita\Monitor\Sdk\Serializer\RepresentationSerializer;
use Jasnita\Monitor\Sdk\State\Hub;
use Jasnita\Monitor\Sdk\State\HubInterface;
use Jasnita\Monitor\Sdk\State\Scope;
use Jasnita\Monitor\Sdk\Tracing\TransactionMetadata;
use Throwable;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * List of configuration options that are Laravel specific and should not be sent to the base PHP SDK.
     */
    protected const LARAVEL_SPECIFIC_OPTIONS = [
        // We do not want these settings to hit the PHP SDK because they are Laravel specific and the PHP SDK will throw errors
        'tracing',
        'breadcrumbs',
        // We resolve the integrations through the container later, so we initially do not pass it to the SDK yet
        'integrations',
        // We have this setting to allow us to capture the .env LOG_LEVEL for the jasnita_logs channel
        'logs_channel_level',
        // This is kept for backwards compatibility and can be dropped in a future breaking release
        'breadcrumbs.sql_bindings',

        // This config option is no longer in use but to prevent errors when upgrading we leave it here to be discarded
        'controllers_base_namespace',
    ];

    /**
     * List of options that should be resolved from the container instead of being passed directly to the SDK.
     */
    protected const OPTIONS_TO_RESOLVE_FROM_CONTAINER = [
        'logger',
    ];

    /**
     * List of features that are provided by the SDK.
     */
    protected const FEATURES = [
        Features\LogIntegration::class,
        Features\CacheIntegration::class,
        Features\QueueIntegration::class,
        Features\ConsoleIntegration::class,
        Features\Storage\Integration::class,
        Features\Ai\HttpRequestIntegration::class,
        Features\HttpClientIntegration::class,
        Features\FolioPackageIntegration::class,
        Features\NotificationsIntegration::class,
        Features\PennantPackageIntegration::class,
        Features\LivewirePackageIntegration::class,
        Features\ConsoleSchedulingIntegration::class,
        Features\AiIntegration::class,
    ];

    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        $this->app->make(HubInterface::class);

        $this->bootFeatures();

        // Only register if a DSN is set or Spotlight is enabled
        // No events can be sent without a DSN set or Spotlight enabled
        if ($this->hasDsnSet() || $this->hasSpotlightEnabled()) {
            $this->bindEvents();

            if ($this->app instanceof Lumen) {
                $this->app->middleware(SetRequestMiddleware::class);
                $this->app->middleware(SetRequestIpMiddleware::class);
                $this->app->middleware(FlushEventsMiddleware::class);
            } elseif ($this->app->bound(HttpKernelInterface::class)) {
                $httpKernel = $this->app->make(HttpKernelInterface::class);

                if ($httpKernel instanceof HttpKernel) {
                    $httpKernel->pushMiddleware(SetRequestMiddleware::class);
                    $httpKernel->pushMiddleware(SetRequestIpMiddleware::class);
                    $httpKernel->pushMiddleware(FlushEventsMiddleware::class);
                }
            }
        }

        if ($this->app->runningInConsole()) {
            if ($this->app instanceof Laravel) {
                $this->publishes([
                    __DIR__ . '/../../config/jasnita.php' => config_path(static::$abstract . '.php'),
                ], 'jasnita-config');
            }

            $this->registerArtisanCommands();
        }

        $this->registerAboutCommandIntegration();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        if ($this->app instanceof Lumen) {
            $this->app->configure(static::$abstract);
        }

        $this->mergeConfigFrom(__DIR__ . '/../../config/jasnita.php', static::$abstract);

        $this->app->singleton(DebugFileLogger::class, function () {
            return new DebugFileLogger(storage_path('logs/jasnita-monitor.log'));
        });

        $this->configureAndRegisterClient();

        $this->registerFeatures();

        $this->registerLogChannels();
    }

    /**
     * Bind to the Laravel event dispatcher to log events.
     */
    protected function bindEvents(): void
    {
        $userConfig = $this->getUserConfig();

        $handler = new EventHandler($this->app, $userConfig);

        try {
            /** @var \Illuminate\Contracts\Events\Dispatcher $dispatcher */
            $dispatcher = $this->app->make(Dispatcher::class);

            $handler->subscribe($dispatcher);

            if ($this->app->bound('octane')) {
                $handler->subscribeOctaneEvents($dispatcher);
            }

            if (isset($userConfig['send_default_pii']) && $userConfig['send_default_pii'] !== false) {
                $handler->subscribeAuthEvents($dispatcher);
            }

            if (isset($userConfig['enable_logs']) && $userConfig['enable_logs'] === true && method_exists($this->app, 'terminating')) {
                // Listen to the terminating event to flush the logs before the application ends
                // This ensures that all logs are sent to Jasnita even if the application ends unexpectedly
                // We need to check for method existence here for Lumen since this method was only introduced in Lumen 9.1.4
                $this->app->terminating(static function () {
                    Logs::getInstance()->flush();
                });
            }
        } catch (BindingResolutionException $e) {
            // If we cannot resolve the event dispatcher we also cannot listen to events
        }
    }

    /**
     * Bind and register all the features.
     */
    protected function registerFeatures(): void
    {
        // Register all the features as singletons, so there is only one instance of each feature in the application
        foreach (self::FEATURES as $feature) {
            $this->app->singleton($feature);
        }

        foreach (self::FEATURES as $feature) {
            try {
                /** @var Feature $featureInstance */
                $featureInstance = $this->app->make($feature);

                $featureInstance->register();
            } catch (Throwable $e) {
                // Ensure that features do not break the whole application
            }
        }
    }

    /**
     * Register the log channels.
     */
    protected function registerLogChannels(): void
    {
        $config = $this->app->make(Repository::class);

        $logChannels = $config->get('logging.channels', []);

        if (!array_key_exists('jasnita', $logChannels)) {
            $config->set('logging.channels.jasnita', [
                'driver' => 'jasnita',
            ]);
        }

        if (!array_key_exists('jasnita_logs', $logChannels)) {
            $config->set('logging.channels.jasnita_logs', [
                'driver' => 'jasnita_logs',
                'level' => $config->get('jasnita.logs_channel_level', 'debug'),
            ]);
        }
    }

    /**
     * Boot all the features.
     */
    protected function bootFeatures(): void
    {
        // Only register if a DSN is set or Spotlight is enabled
        // No events can be sent without a DSN set or Spotlight enabled
        $bootActive = $this->hasDsnSet() || $this->hasSpotlightEnabled();

        foreach (self::FEATURES as $feature) {
            try {
                /** @var Feature $featureInstance */
                $featureInstance = $this->app->make($feature);

                $bootActive
                    ? $featureInstance->boot()
                    : $featureInstance->bootInactive();
            } catch (Throwable $e) {
                // Ensure that features do not break the whole application
            }
        }
    }

    /**
     * Register the artisan commands.
     */
    protected function registerArtisanCommands(): void
    {
        $this->commands([
            TestCommand::class,
            PublishCommand::class,
        ]);
    }

    /**
     * Register the `php artisan about` command integration.
     */
    protected function registerAboutCommandIntegration(): void
    {
        // The about command is only available in Laravel 9 and up so we need to check if it's available to us
        if (!class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Jasnita', AboutCommandIntegration::class);
    }

    /**
     * Configure and register the Jasnita client with the container.
     */
    protected function configureAndRegisterClient(): void
    {
        $this->app->bind(ClientBuilder::class, function () {
            $basePath = base_path();
            $userConfig = $this->getUserConfig();

            foreach (static::LARAVEL_SPECIFIC_OPTIONS as $laravelSpecificOptionName) {
                unset($userConfig[$laravelSpecificOptionName]);
            }

            $options = \array_merge(
                [
                    'prefixes' => [$basePath],
                    'in_app_exclude' => [
                        "{$basePath}/vendor",
                        "{$basePath}/artisan",
                    ],
                ],
                $userConfig
            );

            // When we get no environment from the (user) configuration we default to the Laravel environment
            if (empty($options['environment'])) {
                $options['environment'] = $this->app->environment();
            }

            if ($this->app instanceof Lumen) {
                $wrapBeforeSend = function (?callable $userBeforeSend) {
                    return function (Event $event, ?EventHint $eventHint) use ($userBeforeSend) {
                        $request = $this->app->make(Request::class);

                        if ($request !== null) {
                            $route = $request->route();

                            if ($route !== null) {
                                [$routeName, $transactionSource] = Integration::extractNameAndSourceForLumenRoute($request->route(), $request->path());

                                $event->setTransaction($routeName);

                                $transactionMetadata = $event->getSdkMetadata('transaction_metadata');

                                if ($transactionMetadata instanceof TransactionMetadata) {
                                    $transactionMetadata->setSource($transactionSource);
                                }
                            }
                        }

                        if ($userBeforeSend !== null) {
                            return $userBeforeSend($event, $eventHint);
                        }

                        return $event;
                    };
                };

                $options['before_send'] = $wrapBeforeSend($options['before_send'] ?? null);
                $options['before_send_transaction'] = $wrapBeforeSend($options['before_send_transaction'] ?? null);
            }

            foreach (self::OPTIONS_TO_RESOLVE_FROM_CONTAINER as $option) {
                if (isset($options[$option]) && is_string($options[$option])) {
                    $options[$option] = $this->app->make($options[$option]);
                }
            }

            $clientBuilder = ClientBuilder::create($options);

            // Set the Laravel SDK identifier and version
            $clientBuilder->setSdkIdentifier(Version::SDK_IDENTIFIER);
            $clientBuilder->setSdkVersion(Version::SDK_VERSION);

            return $clientBuilder;
        });

        $this->app->singleton(HubInterface::class, function () {
            /** @var \Jasnita\Monitor\Sdk\ClientBuilder $clientBuilder */
            $clientBuilder = $this->app->make(ClientBuilder::class);

            $options = $clientBuilder->getOptions();

            $userConfig = $this->getUserConfig();

            /** @var array<array-key, class-string>|callable $userConfig */
            $userIntegrationOption = $userConfig['integrations'] ?? [];

            $userIntegrations = $this->resolveIntegrationsFromUserConfig(
                \is_array($userIntegrationOption)
                    ? $userIntegrationOption
                    : [],
                $userConfig['tracing']['default_integrations'] ?? true
            );

            $options->setIntegrations(static function (array $integrations) use ($options, $userIntegrations, $userIntegrationOption): array {
                if ($options->hasDefaultIntegrations()) {
                    // Remove the default error and fatal exception listeners to let Laravel handle those
                    // itself. These event are still bubbling up through the documented changes in the users
                    // `ExceptionHandler` of their application or through the log channel integration to Jasnita
                    $integrations = array_filter($integrations, static function (SdkIntegration\IntegrationInterface $integration): bool {
                        if ($integration instanceof SdkIntegration\ErrorListenerIntegration) {
                            return false;
                        }

                        if ($integration instanceof SdkIntegration\ExceptionListenerIntegration) {
                            return false;
                        }

                        if ($integration instanceof SdkIntegration\FatalErrorListenerIntegration) {
                            return false;
                        }

                        // We also remove the default request integration so it can be readded
                        // after with a Laravel specific request fetcher. This way we can resolve
                        // the request from Laravel instead of constructing it from the global state
                        if ($integration instanceof SdkIntegration\RequestIntegration) {
                            return false;
                        }

                        return true;
                    });

                    $integrations[] = new SdkIntegration\RequestIntegration(
                        new LaravelRequestFetcher
                    );
                }

                $integrations = array_merge(
                    $integrations,
                    [
                        new Integration,
                        new Integration\LaravelContextIntegration,
                        new Integration\ExceptionContextIntegration,
                    ],
                    $userIntegrations
                );

                if (\is_callable($userIntegrationOption)) {
                    return $userIntegrationOption($integrations);
                }

                return $integrations;
            });

            // Some Laravel versions might run withExceptions(..) before the real Jasnita hub is instantiated.
            // By copying the scope here we can preserve data that was set on the Scope before this
            // function was executed.
            $hub = new Hub($clientBuilder->getClient(), $this->cloneCurrentHubScope());

            JasnitaSdk::setCurrentHub($hub);

            return $hub;
        });

        $this->app->alias(HubInterface::class, static::$abstract);

        $this->app->singleton(BacktraceHelper::class, function () {
            $jasnita = $this->app->make(HubInterface::class);

            $options = $jasnita->getClient()->getOptions();

            return new BacktraceHelper($options, new RepresentationSerializer($options));
        });
    }

    private function cloneCurrentHubScope(): ?Scope
    {
        $currentHub = JasnitaSdk::getCurrentHub();

        if ($currentHub->getClient() !== null) {
            return null;
        }

        $clonedScope = null;

        $currentHub->configureScope(static function (Scope $scope) use (&$clonedScope): void {
            $clonedScope = clone $scope;
        });

        return $clonedScope;
    }

    /**
     * Resolve the integrations from the user configuration with the container.
     */
    private function resolveIntegrationsFromUserConfig(array $userIntegrations, bool $enableDefaultTracingIntegrations): array
    {
        $integrationsToResolve = $userIntegrations;

        if ($enableDefaultTracingIntegrations) {
            $integrationsToResolve = array_merge($integrationsToResolve, TracingServiceProvider::DEFAULT_INTEGRATIONS);
        }

        $integrations = [];

        foreach ($integrationsToResolve as $userIntegration) {
            if ($userIntegration instanceof SdkIntegration\IntegrationInterface) {
                $integrations[] = $userIntegration;
            } elseif (\is_string($userIntegration)) {
                $resolvedIntegration = $this->app->make($userIntegration);

                if (!$resolvedIntegration instanceof SdkIntegration\IntegrationInterface) {
                    throw new RuntimeException(
                        sprintf(
                            'Jasnita integrations must be an instance of `%s` got `%s`.',
                            SdkIntegration\IntegrationInterface::class,
                            get_class($resolvedIntegration)
                        )
                    );
                }

                $integrations[] = $resolvedIntegration;
            } else {
                throw new RuntimeException(
                    sprintf(
                        'Jasnita integrations must either be a valid container reference or an instance of `%s`.',
                        SdkIntegration\IntegrationInterface::class
                    )
                );
            }
        }

        return $integrations;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [static::$abstract];
    }
}
