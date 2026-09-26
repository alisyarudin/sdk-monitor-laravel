<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features\Storage;

use Illuminate\Filesystem\FilesystemAdapter;

class JasnitaFilesystemAdapter extends FilesystemAdapter
{
    use FilesystemAdapterDecorator;

    public function __construct(FilesystemAdapter $filesystem, array $defaultData, bool $recordSpans, bool $recordBreadcrumbs)
    {
        parent::__construct($filesystem->getDriver(), $filesystem->getAdapter(), $filesystem->getConfig());

        $this->filesystem = $filesystem;
        $this->defaultData = $defaultData;
        $this->recordSpans = $recordSpans;
        $this->recordBreadcrumbs = $recordBreadcrumbs;
    }
}
