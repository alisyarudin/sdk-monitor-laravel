<?php

namespace Jasnita\Monitor\Console;

use Illuminate\Console\Command;

/**
 * php artisan monitor:install --dsn=https://<key>@monitor.jasnita.com/<id>
 *
 * Mengisi .env dan memberi tahu bila aplikasi masih memasang pelapor
 * exception manual (yang akan membuat setiap error terkirim dua kali).
 */
class InstallCommand extends Command
{
    protected $signature = 'monitor:install
        {--dsn= : DSN dari dashboard Jasnita Monitor}
        {--traces=0.1 : Porsi request yang dicatat performanya (0.0-1.0)}
        {--publish : Salin config/jasnita-monitor.php ke aplikasi}';

    protected $description = 'Pasang Jasnita Monitor di aplikasi ini';

    public function handle()
    {
        $dsn = (string) ($this->option('dsn') ?: $this->ask('DSN (dari dashboard Jasnita Monitor)'));
        if (! preg_match('#^https?://[0-9a-f]{32}@[^/]+(/.*)?/\d+$#', $dsn)) {
            $this->error('Format DSN tidak dikenali. Salin persis dari halaman project di dashboard.');

            return 1;
        }

        $traces = (float) $this->option('traces');
        if ($traces < 0 || $traces > 1) {
            $this->error('--traces harus di antara 0.0 dan 1.0.');

            return 1;
        }

        foreach ([$this->laravel->environmentFilePath(), $this->laravel->basePath('.env.example')] as $i => $file) {
            if (! is_file($file)) {
                continue;
            }
            // .env.example hanya dapat nama variabelnya, bukan DSN asli.
            $this->setEnv($file, 'JASNITA_MONITOR_DSN', $i === 0 ? $dsn : '');
            $this->setEnv($file, 'JASNITA_MONITOR_TRACES_SAMPLE_RATE', (string) $traces);
            $this->line('Diperbarui: ' . basename($file));
        }

        if ($this->option('publish')) {
            $this->call('vendor:publish', ['--tag' => 'jasnita-monitor-config']);
        }

        $this->warnDuplicateReporting();

        $this->info('Selesai. Uji dengan: php artisan monitor:test');
        if (is_file($this->laravel->getCachedConfigPath())) {
            $this->warn('Config sedang di-cache — jalankan php artisan config:cache lagi.');
        }

        return 0;
    }

    private function setEnv($file, $key, $value)
    {
        $content = (string) file_get_contents($file);
        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content);
        } else {
            $content = rtrim($content, "\n") . "\n\n" . $line . "\n";
        }
        file_put_contents($file, $content);
    }

    /**
     * Paket ini sudah memasang pelapor exception sendiri. Integration::handles()
     * atau captureUnhandledException() yang tertinggal dari pemasangan agent
     * sebelumnya membuat setiap error terkirim dua kali.
     */
    private function warnDuplicateReporting()
    {
        $files = [
            $this->laravel->basePath('bootstrap/app.php'),
            $this->laravel->basePath('app/Exceptions/Handler.php'),
        ];
        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }
            $src = (string) file_get_contents($file);
            if (strpos($src, 'Integration::handles') !== false || strpos($src, 'captureUnhandledException') !== false) {
                $this->warn('Ditemukan pelapor exception manual di ' . str_replace($this->laravel->basePath() . '/', '', $file) . '.');
                $this->warn('Hapus baris itu, ATAU set JASNITA_MONITOR_AUTO_REPORT=false — jangan keduanya aktif.');
            }
        }
    }
}
