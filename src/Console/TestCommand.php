<?php

namespace Jasnita\Monitor\Console;

use Exception;
use Illuminate\Console\Command;
use Jasnita\Monitor\Monitor;

/**
 * php artisan monitor:test
 *
 * Memeriksa DSN, keterjangkauan server, lalu mengirim satu event uji.
 * Server yang tidak terjangkau diperiksa DULU secara terpisah: SDK sendiri
 * diam saja saat gagal mengirim, dan "event terkirim" tanpa pemeriksaan ini
 * menyesatkan.
 */
class TestCommand extends Command
{
    protected $signature = 'monitor:test';

    protected $description = 'Kirim event uji ke Jasnita Monitor';

    public function handle(Monitor $monitor)
    {
        $dsn = (string) config('jasnita-monitor.dsn');
        if ($dsn === '') {
            $this->error('JASNITA_MONITOR_DSN belum diisi. Jalankan php artisan monitor:install.');

            return 1;
        }

        $parts = parse_url($dsn);
        if (! $parts || empty($parts['host'])) {
            $this->error('DSN tidak valid.');

            return 1;
        }

        $base = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $this->line('Server  : ' . $base);
        $this->line('Env     : ' . config('jasnita-monitor.environment'));
        $this->line('Release : ' . (config('jasnita-monitor.release') ?: '(tidak terdeteksi)'));

        $status = $this->ping($base . '/healthz');
        if ($status !== 200) {
            $this->error('Server tidak terjangkau (' . ($status ?: 'tanpa respons') . '). Periksa jaringan/firewall ke ' . $base . '.');

            return 1;
        }
        $this->info('Server terjangkau.');

        $id = $monitor->captureException(new Exception('Event uji dari php artisan monitor:test — boleh ditandai selesai.'));
        $monitor->flush(5);

        if ($id === null) {
            $this->error('SDK tidak membuat event (sample_rate 0 atau exception diabaikan?).');

            return 1;
        }

        $this->info('Event uji terkirim: ' . $id);
        $this->line('Lihat di dashboard Jasnita Monitor → project ini → issue "Event uji dari php artisan monitor:test".');

        return 0;
    }

    /** @return int|null kode HTTP */
    private function ping($url)
    {
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || empty($http_response_header[0])) {
            return null;
        }
        if (preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
