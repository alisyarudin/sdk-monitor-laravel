<?php

namespace Jasnita\Monitor\Support;

/**
 * Menebak release dari repositori git aplikasi, tanpa menjalankan `git`
 * (exec sering dimatikan di server produksi, dan memanggil proses di setiap
 * boot terlalu mahal). Yang dibaca hanya .git/HEAD dan ref yang ditunjuknya.
 *
 * Deploy yang tidak membawa folder .git (mis. rsync/artifact) sebaiknya
 * mengisi JASNITA_MONITOR_RELEASE sendiri.
 */
class Release
{
    public static function detect($basePath)
    {
        $git = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . '.git';
        $head = @file_get_contents($git . DIRECTORY_SEPARATOR . 'HEAD');
        if ($head === false) {
            return null;
        }
        $head = trim($head);

        // Detached HEAD: isinya langsung hash commit.
        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return substr($head, 0, 12);
        }

        if (strpos($head, 'ref: ') !== 0) {
            return null;
        }
        $ref = trim(substr($head, 5));

        $hash = @file_get_contents($git . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref));
        if ($hash === false) {
            // Ref yang sudah di-pack (git gc) tidak punya berkas sendiri.
            $packed = @file_get_contents($git . DIRECTORY_SEPARATOR . 'packed-refs');
            if ($packed !== false && preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m', $packed, $m)) {
                $hash = $m[1];
            }
        }

        return $hash ? substr(trim($hash), 0, 12) : null;
    }
}
