<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features\Storage;

use Illuminate\Filesystem\AwsS3V3Adapter;

class JasnitaS3V3Adapter extends AwsS3V3Adapter
{
    use FilesystemAdapterDecorator;

    public function __construct(AwsS3V3Adapter $filesystem, array $defaultData, bool $recordSpans, bool $recordBreadcrumbs)
    {
        parent::__construct($filesystem->getDriver(), $filesystem->getAdapter(), $filesystem->getConfig(), $filesystem->getClient());

        $this->filesystem = $filesystem;
        $this->defaultData = $defaultData;
        $this->recordSpans = $recordSpans;
        $this->recordBreadcrumbs = $recordBreadcrumbs;
    }
}
