<?php

/**
 * Membangun ulang SDK Jasnita Monitor dari kode hulu (getsentry/sentry-php &
 * getsentry/sentry-laravel, lisensi MIT) dengan mengganti seluruh penamaan.
 *
 *   php tools/rebrand.php            # tarik versi di tools/upstream.json, tulis ulang src/Sdk, src/Laravel, ...
 *   php tools/rebrand.php --check    # hanya periksa: masih ada "sentry" tersisa?
 *
 * Direktori yang DIHASILKAN skrip ini (jangan diubah manual — akan tertimpa):
 *   src/Sdk/  src/Laravel/  config/jasnita.php  tests/Sdk/  tests/Laravel/  LICENSES/
 *
 * Kustomisasi Jasnita ditaruh di luar direktori itu (src/*.php, src/Console,
 * src/Facades, src/Support, config/jasnita-monitor.php).
 *
 * Syarat lisensi MIT: teks LICENSE hulu disalin utuh ke LICENSES/. Itu
 * satu-satunya tempat nama hulu boleh tersisa.
 */

$root = dirname(__DIR__);
$work = $root . '/tools/.upstream';
$checkOnly = in_array('--check', $argv, true);

$GENERATED = ['src/Sdk', 'src/Laravel', 'config/jasnita.php', 'tests/Sdk', 'tests/Laravel', 'LICENSES'];

// ── Aturan penggantian isi berkas (berurutan; urutan penting) ────────────────

/** @return string */
function rebrand(string $s, bool $isLaravel, bool $isTest = false): string
{
    // 1. Tautan DOKUMENTASI hulu → dibuang (bukan diganti jadi domain palsu).
    //    Hanya host dokumentasi: URL lain (mis. DSN contoh di tes
    //    "http://key@sentry.dev/1") harus tetap URL yang valid dan ikut
    //    rename biasa di langkah 6.
    $s = preg_replace('~https?://(?:docs\.sentry\.io|develop\.sentry\.dev|github\.com/getsentry|sentry\.io/(?:for|welcome|pricing|signup))[^\s\'"()<>`]*~i', '(dokumentasi hulu)', $s);

    // 2. Nama paket composer & identitas SDK.
    $s = strtr($s, [
        // Bentuk JSON (garis miring di-escape).
        'sentry\\/sentry-laravel'  => 'jasnita\\/monitor-laravel',
        'sentry\\/sentry'          => 'jasnita\\/monitor-laravel',
        'getsentry/sentry-laravel' => 'jasnita/monitor-laravel',
        'getsentry/sentry-php'     => 'jasnita/monitor-laravel',
        'sentry/sentry-laravel'    => 'jasnita/monitor-laravel',
        'sentry/sentry'            => 'jasnita/monitor-laravel',
        "'sentry.php.laravel'"     => "'jasnita.monitor.laravel'",
        "SDK_IDENTIFIER = 'sentry.php'" => "SDK_IDENTIFIER = 'jasnita.monitor.php'",
        'config/sentry.php'        => 'config/jasnita.php',
        'logs/sentry.log'          => 'logs/jasnita-monitor.log',
    ]);
    // Identitas SDK inti di mana pun ia ditulis (konstanta, harapan tes, JSON).
    $s = str_replace(["'sentry.php'", '"sentry.php"'], ["'jasnita.monitor.php'", '"jasnita.monitor.php"'], $s);

    // 3. Kode Laravel pindah dari src/Sentry/Laravel ke src/Laravel: path
    //    relatif ke akar paket jadi satu tingkat lebih pendek.
    if ($isLaravel && !$isTest) {
        $s = str_replace("__DIR__ . '/../../../", "__DIR__ . '/../../", $s);
    }
    // Tes pindah SATU tingkat lebih dalam (test/ → tests/Laravel/, tests/ →
    // tests/Sdk/): hanya path yang keluar sampai akar paket (vendor/) yang
    // perlu tambahan satu "../". Path di dalam folder tes sendiri tetap benar.
    if ($isTest) {
        $s = str_replace("__DIR__ . '/../vendor/", "__DIR__ . '/../../vendor/", $s);
        $s = preg_replace_callback("/dirname\\(__DIR__, (\\d+)\\) \\. '\\/vendor\\//", function ($m) {
            return 'dirname(__DIR__, ' . ((int) $m[1] + 1) . ") . '/vendor/";
        }, $s);
    }

    // 4. Variabel lingkungan.
    $s = str_replace(['SENTRY_LARAVEL_DSN', 'SENTRY_LARAVEL_'], ['JASNITA_MONITOR_DSN', 'JASNITA_MONITOR_'], $s);
    // Bukan nama header dari $_SERVER: header "sentry-trace" terbaca sebagai
    // HTTP_SENTRY_TRACE dan harus ikut nama headernya (jasnita-trace →
    // HTTP_JASNITA_TRACE, lewat langkah 6), bukan jadi HTTP_JASNITA_MONITOR_*.
    $s = preg_replace('/(?<![A-Za-z0-9_])SENTRY_/', 'JASNITA_MONITOR_', $s);

    // 5. Namespace. Jumlah backslash pemisah DIPERTAHANKAN: kode biasa pakai
    //    satu, string PHP dua, regex di dalam string empat. Mengganti hanya
    //    sebagian membuat regex/nama kelas rusak.
    $nb = '(?<![A-Za-z0-9_])';
    $s = preg_replace_callback('/' . $nb . 'Sentry(\\\\+)Laravel(?![A-Za-z0-9_])/', function ($m) {
        return 'Jasnita' . $m[1] . 'Monitor' . $m[1] . 'Laravel';
    }, $s);
    $s = preg_replace_callback('/' . $nb . 'Sentry(\\\\+)(?=[A-Za-z_{])/', function ($m) {
        return 'Jasnita' . $m[1] . 'Monitor' . $m[1] . 'Sdk' . $m[1];
    }, $s);
    // `namespace Sentry;` dan `namespace Sentry { ... }`
    $s = preg_replace('/\bnamespace Sentry(\s*[;{])/', 'namespace Jasnita\\Monitor\\Sdk$1', $s);

    // 5a. Namespace bertitik (dipakai Folio untuk nama berkas model dan
    //     Pennant untuk nama flag): Sentry.Laravel.X → Jasnita.Monitor.Laravel.X
    $s = dottedNamespace($s);

    // 5b. String hasil serialize() menyimpan panjang nama kelas:
    //     O:25:"Sentry\State\HubAdapter" → panjang harus dihitung ulang.
    $s = preg_replace_callback('/\b([OC]):\d+:"((?:Jasnita|Sentry)[^"]*)"/', function ($m) {
        return $m[1] . ':' . strlen(str_replace('\\\\', '\\', $m[2])) . ':"' . $m[2] . '"';
    }, $s);

    // 6. Sisanya: nama kelas/metode/konstanta, header HTTP, kunci config,
    //    teks komentar — huruf besar/kecil dipertahankan. "Jasnita", bukan
    //    "Monitor": hulu sudah memakai Monitor* (fitur cron) dan akan bentrok.
    $s = str_replace(['SENTRY', 'Sentry', 'sentry'], ['JASNITA', 'Jasnita', 'jasnita'], $s);

    return $s;
}

/**
 * Patch KHUSUS TES: angka/path yang dikodekan mati di tes hulu dan berubah
 * karena folder tes pindah atau teks data ikut berganti nama. Bukan
 * perubahan perilaku kode. Skrip gagal bila polanya tidak ditemukan — tanda
 * tes hulu berubah dan patch perlu ditinjau.
 *
 * @return array<string, array{0: string, 1: string, 2: string}> berkas => [cari, ganti, alasan]
 */
function testPatches(): array
{
    return [
        'tests/Sdk/CodeLocationResolverTest.php' => [
            "\\DIRECTORY_SEPARATOR . 'tests' . \\DIRECTORY_SEPARATOR . 'CodeLocationResolverTest.php'",
            "\\DIRECTORY_SEPARATOR . 'Sdk' . \\DIRECTORY_SEPARATOR . 'CodeLocationResolverTest.php'",
            'folder tes pindah dari tests/ ke tests/Sdk/',
        ],
        'tests/Laravel/Jasnita/Features/ConsoleSchedulingIntegrationTest.php' => [
            "'scheduled_scheduledqueuedjob-features-tests-laravel-jasnita'", "'scheduled_scheduledqueuedjob-features-tests-laravel-monitor-jasnita'",
            'slug dibentuk dari namespace kelas tes, yang kini Jasnita\\Monitor\\Laravel\\Tests',
        ],
        'tests/Sdk/Tracing/GuzzleTracingMiddlewareTest.php' => [
            "'not-jasnita'),\n            new Response(403, [], 'jasnita'),", "'not-sentri'),\n            new Response(403, [], 'sentri'),",
            'ukuran body (10/6 byte) dikodekan mati; teks data dikembalikan ke panjang semula',
        ],
    ];
}

function dottedNamespace(string $s): string
{
    $s = preg_replace('/(?<![A-Za-z0-9_])Sentry\.Laravel\./', 'Jasnita.Monitor.Laravel.', $s);

    return preg_replace('/(?<=\.|\[)Sentry\.(?=[A-Z])/', 'Jasnita.Monitor.Sdk.', $s);
}

function rebrandPath(string $p): string
{
    return str_replace(['Sentry', 'sentry'], ['Jasnita', 'jasnita'], dottedNamespace($p));
}

// ── Utilitas berkas ─────────────────────────────────────────────────────────

function rrmdir(string $d): void
{
    if (!file_exists($d)) {
        return;
    }
    if (is_file($d) || is_link($d)) {
        unlink($d);
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($d);
}

/** Salin satu pohon sambil mengganti nama & isi. */
function copyTree(string $from, string $to, bool $isLaravel, string $origin, bool $isTest = false): int
{
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($from) + 1);
        $dest = $to . '/' . rebrandPath($rel);
        @mkdir(dirname($dest), 0777, true);
        $content = file_get_contents($file->getPathname());
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        // Semua berkas teks diproses; hanya biner (berisi byte nol) yang
        // disalin apa adanya. Daftar ekstensi manual pasti ada yang bolong.
        if (strpos($content, "\0") === false) {
            $content = rebrand($content, $isLaravel, $isTest);
            // Penanda di baris yang SAMA dengan <?php: jumlah baris tidak
            // berubah, jadi nomor baris di stack trace tetap cocok dengan hulu.
            if ($ext === 'php' && strncmp($content, "<?php\n", 6) === 0) {
                $content = "<?php // Dihasilkan tools/rebrand.php dari {$origin} — jangan diubah manual.\n" . substr($content, 6);
            }
        }
        file_put_contents($dest, $content);
        $n++;
    }
    return $n;
}

/** @return list<string> baris "berkas:baris: isi" yang masih memuat nama hulu */
function leftovers(string $root, array $paths): array
{
    $hits = [];
    foreach ($paths as $p) {
        $abs = $root . '/' . $p;
        if (!file_exists($abs) || strpos($p, 'LICENSES') === 0) {
            continue;
        }
        $files = is_file($abs) ? [new SplFileInfo($abs)] : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $f) {
            if (stripos($f->getPathname(), 'sentry') !== false) {
                $hits[] = substr($f->getPathname(), strlen($root) + 1) . ': nama berkas';
            }
            foreach (file($f->getPathname()) ?: [] as $i => $line) {
                if (stripos($line, 'sentry') !== false) {
                    $hits[] = substr($f->getPathname(), strlen($root) + 1) . ':' . ($i + 1) . ': ' . trim($line);
                }
            }
        }
    }
    return $hits;
}

// ── Jalankan ────────────────────────────────────────────────────────────────

if (!$checkOnly) {
    $up = json_decode(file_get_contents(__DIR__ . '/upstream.json'), true);
    rrmdir($work);
    @mkdir($work, 0777, true);

    foreach (['sentry-php', 'sentry-laravel'] as $name) {
        $cmd = sprintf('git clone -q --depth 1 --branch %s %s %s 2>&1', escapeshellarg($up[$name]['tag']), escapeshellarg($up[$name]['repo']), escapeshellarg("$work/$name"));
        exec($cmd, $out, $code);
        if ($code !== 0) {
            fwrite(STDERR, "Gagal menarik $name {$up[$name]['tag']}:\n" . implode("\n", $out) . "\n");
            exit(1);
        }
        echo "✓ tarik $name {$up[$name]['tag']}\n";
    }

    foreach ($GENERATED as $g) {
        rrmdir("$root/$g");
    }

    $php = 'getsentry/sentry-php ' . $up['sentry-php']['tag'];
    $lar = 'getsentry/sentry-laravel ' . $up['sentry-laravel']['tag'];
    // Tulis asal-usul dengan nama hulu APA ADANYA hanya di LICENSES/.
    $counts = [
        'src/Sdk'       => copyTree("$work/sentry-php/src", "$root/src/Sdk", false, 'hulu sdk-php'),
        'src/Laravel'   => copyTree("$work/sentry-laravel/src/Sentry/Laravel", "$root/src/Laravel", true, 'hulu sdk-laravel'),
        'tests/Sdk'     => copyTree("$work/sentry-php/tests", "$root/tests/Sdk", false, 'hulu sdk-php', true),
        'tests/Laravel' => copyTree("$work/sentry-laravel/test", "$root/tests/Laravel", true, 'hulu sdk-laravel', true),
    ];
    foreach (testPatches() as $file => [$find, $replace, $why]) {
        $path = "$root/$file";
        $content = file_get_contents($path);
        if (strpos($content, $find) === false) {
            fwrite(STDERR, "✗ Patch tes tidak cocok lagi: $file ($why). Tinjau tes hulu.\n");
            exit(1);
        }
        file_put_contents($path, str_replace($find, $replace, $content));
        echo "✓ patch tes: $file — $why\n";
    }

    @mkdir("$root/config", 0777, true);
    file_put_contents("$root/config/jasnita.php", rebrand(file_get_contents("$work/sentry-laravel/config/sentry.php"), true));

    @mkdir("$root/LICENSES", 0777, true);
    copy("$work/sentry-php/LICENSE", "$root/LICENSES/sdk-php.LICENSE");
    copy("$work/sentry-laravel/LICENSE", "$root/LICENSES/sdk-laravel.LICENSE");
    file_put_contents("$root/LICENSES/README.md",
        "Kode di src/Sdk dan src/Laravel diturunkan dari proyek sumber terbuka berlisensi MIT:\n\n" .
        "- $php\n- $lar\n\n" .
        "Lisensi MIT mewajibkan teks lisensi asli disertakan; lihat berkas *.LICENSE di folder ini.\n");

    foreach ($counts as $dir => $n) {
        echo "✓ $dir: $n berkas\n";
    }
}

$hits = leftovers($root, $GENERATED);
if ($hits) {
    fwrite(STDERR, "\n✗ Masih ada " . count($hits) . " kemunculan nama hulu:\n  " . implode("\n  ", array_slice($hits, 0, 40)) . "\n");
    exit(1);
}
echo "✓ Tidak ada nama hulu tersisa di luar LICENSES/.\n";
