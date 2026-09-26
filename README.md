# jasnita/monitor-laravel

Agent **Jasnita Monitor** untuk Laravel: exception, performa request/queue, dan breadcrumb (query, log, HTTP client) dikirim ke server Jasnita Monitor.

- Laravel 6–12, PHP 7.2+
- Pasang tanpa mengubah berkas aplikasi (Laravel 8+)
- Password, token, cookie disaring di server walaupun klien lupa mengatur apa pun

## Pasang

```bash
composer require jasnita/monitor-laravel
php artisan monitor:install --dsn=https://<key>@monitor.jasnita.com/<dsn_id>
php artisan monitor:test
```

DSN ada di dashboard Jasnita Monitor, halaman project (klik untuk menyalin).

<details>
<summary>Belum terdaftar di Packagist? Pasang langsung dari GitHub</summary>

Daftarkan repo ini sebagai sumber paket di aplikasi Anda, lalu pasang seperti biasa:

```bash
composer config repositories.jasnita vcs https://github.com/alisyarudin/sdk-monitor-laravel
composer require jasnita/monitor-laravel:dev-main
```

`dev-main` = versi terbaru di branch `main`. Bila repo sudah punya tag rilis (mis. `v1.0.0`), pakai versinya supaya tidak ikut berubah tiap ada commit baru:

```bash
composer require jasnita/monitor-laravel:^1.0
```

Setelah paket terdaftar di Packagist, baris `repositories` itu boleh dihapus dari `composer.json`.
</details>

Sudah. Pada Laravel 8 ke atas pelapor exception terpasang otomatis — tidak ada yang perlu diubah di `bootstrap/app.php` maupun `Handler.php`.

<details>
<summary>Laravel 6 / 7</summary>

Belum punya `reportable()`, jadi tambahkan di `app/Exceptions/Handler.php`:

```php
public function report(Throwable $e)
{
    \Jasnita\Monitor\Laravel\Integration::captureUnhandledException($e);
    parent::report($e);
}
```

dan set `JASNITA_MONITOR_AUTO_REPORT=false`.
</details>

## Konfigurasi (.env)

| Variabel | Bawaan | Keterangan |
|---|---|---|
| `JASNITA_MONITOR_DSN` | — | Kosong = tidak mengirim apa pun (cocok untuk local) |
| `JASNITA_MONITOR_ENVIRONMENT` | `APP_ENV` | |
| `JASNITA_MONITOR_RELEASE` | commit git | Isi saat deploy bila server tidak membawa folder `.git` |
| `JASNITA_MONITOR_TRACES_SAMPLE_RATE` | `0.1` | Porsi request yang dicatat performanya. `1.0` hanya untuk dev |
| `JASNITA_MONITOR_SAMPLE_RATE` | `1.0` | Porsi error yang dikirim |
| `JASNITA_MONITOR_SEND_DEFAULT_PII` | `false` | Kirim IP & user yang login |
| `JASNITA_MONITOR_AUTO_REPORT` | `true` | Matikan bila memasang pelapor manual |

Pengaturan lain (tag global, exception yang diabaikan, opsi lanjutan): `php artisan vendor:publish --tag=jasnita-monitor-config` → `config/jasnita-monitor.php`.

## Dari kode aplikasi

```php
use Jasnita\Monitor\Facades\Monitor;

Monitor::captureException($e);                       // laporkan exception yang Anda tangkap sendiri
Monitor::captureMessage('Saldo gateway menipis', 'warning');
Monitor::setUser(['id' => $user->id, 'email' => $user->email]);
Monitor::setTag('tenant', $tenantId);                // bisa difilter di dashboard
```

## Naik dari v1

v2 membawa SDK sendiri — tidak lagi menarik paket pihak ketiga untuk
pengiriman. Konfigurasi, perintah, dan facade tidak berubah:

```bash
composer update jasnita/monitor-laravel
php artisan config:clear
php artisan monitor:test
```

Server Jasnita Monitor harus sudah versi yang mengenali header
`X-Jasnita-Auth` (ingest per 2026-09) sebelum aplikasi dinaikkan ke v2.

## Menyelidiki query lambat & N+1

Dengan `JASNITA_MONITOR_TRACES_SAMPLE_RATE` > 0, setiap query di request yang
ter-sampling dicatat. Halaman **Database** di dashboard merangkum query paling
berat, paling lambat, dan pola N+1 (query yang sama berulang di satu request)
beserta route-nya.

## Untuk pengembang paket

Kode di `src/Sdk/` dan `src/Laravel/` **dihasilkan** oleh `tools/rebrand.php`
dari proyek sumber terbuka berlisensi MIT (asal-usul dan teks lisensi di
`LICENSES/`). Jangan diubah manual — perubahan tertimpa saat pembaruan.
Kustomisasi Jasnita ada di `src/*.php`, `src/Console`, `src/Facades`,
`src/Support`, dan `config/jasnita-monitor.php`.

```bash
# naikkan versi hulu di tools/upstream.json, lalu:
php tools/rebrand.php     # tarik ulang + ganti nama; gagal bila ada nama hulu tersisa
tools/test.sh             # test suite hulu (1.700+ tes) terhadap hasilnya
```
