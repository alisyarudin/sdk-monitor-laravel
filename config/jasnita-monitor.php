<?php

use Jasnita\Monitor\Support\Release;

/*
|--------------------------------------------------------------------------
| Jasnita Monitor
|--------------------------------------------------------------------------
|
| Cukup isi JASNITA_MONITOR_DSN di .env. Nilai lain punya bawaan yang aman
| untuk produksi. Kosongkan DSN untuk mematikan pengiriman sepenuhnya
| (mis. di local), tanpa perlu mencopot paket.
|
*/

return [

    'dsn' => env('JASNITA_MONITOR_DSN'),

    // Bawaan: APP_ENV. Dipakai untuk memisahkan error production dari staging.
    'environment' => env('JASNITA_MONITOR_ENVIRONMENT', env('APP_ENV', 'production')),

    // Versi yang sedang berjalan: dari env bila diisi saat deploy, bila tidak
    // dari commit git (dibaca dari berkas .git, tanpa menjalankan perintah).
    // Menandai release membuat issue yang "muncul lagi setelah deploy" terlihat.
    'release' => env('JASNITA_MONITOR_RELEASE', Release::detect(base_path())),

    // Porsi request yang dicatat performanya (0.0–1.0). Satu request Laravel
    // bisa menghasilkan ratusan span; 1.0 hanya untuk development.
    'traces_sample_rate' => (float) env('JASNITA_MONITOR_TRACES_SAMPLE_RATE', 0.1),

    // Porsi error yang dikirim. Hampir selalu 1.0.
    'sample_rate' => (float) env('JASNITA_MONITOR_SAMPLE_RATE', 1.0),

    // Kirim IP, cookie, dan data user yang login. Mati secara bawaan; server
    // Jasnita Monitor tetap menyaring password/token walaupun ini dinyalakan.
    'send_default_pii' => (bool) env('JASNITA_MONITOR_SEND_DEFAULT_PII', false),

    // Pasang pelapor exception otomatis ke handler Laravel 8+. Matikan hanya
    // bila Anda memanggil Integration::handles() sendiri — jangan keduanya,
    // atau setiap error terkirim dua kali.
    'auto_report_exceptions' => (bool) env('JASNITA_MONITOR_AUTO_REPORT', true),

    // Tag yang ditempel di SETIAP event, mis. nama aplikasi atau kantor cabang.
    'tags' => [
        // 'app' => 'clicktocall',
    ],

    // Exception yang tidak perlu dikirim (nama kelas). Validasi & 404 sudah
    // disaring Laravel sendiri lewat $dontReport.
    'ignore_exceptions' => [
        // Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    // Jalur keluar ke opsi SDK di bawahnya, untuk kebutuhan yang belum punya
    // pengaturan di atas. Menimpa semua nilai lain.
    'advanced' => [
        // 'breadcrumbs' => ['sql_bindings' => false],
    ],

];
