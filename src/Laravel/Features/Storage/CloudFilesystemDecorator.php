<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Features\Storage;

trait CloudFilesystemDecorator
{
    use FilesystemDecorator;

    public function url($path)
    {
        return $this->withJasnita(__FUNCTION__, func_get_args(), $path, compact('path'));
    }
}
