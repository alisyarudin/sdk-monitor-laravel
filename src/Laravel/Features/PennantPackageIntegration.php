<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features;

use Jasnita\Monitor\Sdk\State\Scope;
use Illuminate\Contracts\Events\Dispatcher;
use Jasnita\Monitor\Sdk\JasnitaSdk;
use Laravel\Pennant\Feature as Pennant;
use Laravel\Pennant\Events\FeatureResolved;
use Laravel\Pennant\Events\FeatureRetrieved;

class PennantPackageIntegration extends Feature
{
    private const FEATURE_KEY = 'pennant';

    public function isApplicable(): bool
    {
        return class_exists(Pennant::class);
    }

    public function onBoot(Dispatcher $events): void
    {
        $events->listen(FeatureRetrieved::class, [$this, 'handleFeatureRetrieved']);
    }

    public function handleFeatureRetrieved($feature): void
    {
        JasnitaSdk::getCurrentHub()->configureScope(function (Scope $scope) use ($feature) {
            // backslashes are not allowed as feature flag names so we replace it with dots to allow class
            // based feature flags
            $featureName = str_replace('\\', '.', ltrim($feature->feature, '\\'));

            // The value of the feature is not always a bool (Rich Feature Values) but only bools are supported.
            // The feature is considered "active" if its value is not explicitly false following Pennant's logic.
            $scope->addFeatureFlag($featureName, $feature->value !== false);
        });
    }
}
