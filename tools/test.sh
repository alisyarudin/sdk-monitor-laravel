#!/usr/bin/env bash
# Jalur 1.x: jalankan test suite hulu terhadap kode hasil rename.
#
#   PHP=php7.4 tools/test.sh            # Laravel 5.8 (bawaan)
#   PHP=php7.4 LARAVEL=5.6 tools/test.sh
#
# Seperti hulu, dua suite dipasang terpisah: inti SDK dengan dependensi dev
# milik inti saja (tanpa Laravel — fungsi global value() milik Laravel
# mengubah hasil tes serializer), integrasi Laravel dengan dependensi Laravel
# saja (tanpa symfony/phpunit-bridge yang memantau error handler).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-php}"
LARAVEL="${LARAVEL:-5.8}"
# Kombinasi sama dengan CI hulu. `case`, bukan array asosiatif: bash bawaan
# macOS (3.2) tidak mendukung declare -A.
case "$LARAVEL" in
  5.5) LV=5.5.*; TB=3.5.*; PU=6.5.* ;;
  5.6) LV=5.6.*; TB=3.6.*; PU=7.5.* ;;
  5.7) LV=5.7.*; TB=3.7.*; PU=7.5.* ;;
  5.8) LV=5.8.*; TB=3.8.*; PU=7.5.* ;;
  6)   LV=^6.0;  TB=4.7.*; PU=8.4.* ;;
  7)   LV=^7.0;  TB=5.1.*; PU=8.4.* ;;
  8)   LV=^8.0;  TB=^6.0;  PU=9.3.* ;;
  9)   LV=^9.0;  TB=^7.0;  PU=9.5.* ;;
  *) echo "LARAVEL harus salah satu: 5.5 5.6 5.7 5.8 6 7 8 9" >&2; exit 1 ;;
esac

COMPOSER="$PHP $(command -v composer)"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$(composer config --global cache-dir 2>/dev/null)}"
export COMPOSER_HOME="$(mktemp -d)"   # tanpa config global (platform.php mesin dev)
WORK="$(mktemp -d)"
trap 'rm -rf "$COMPOSER_HOME" "$WORK"' EXIT
echo "PHP $($PHP -r 'echo PHP_VERSION;'), Laravel $LV"

copy() { rsync -a --exclude vendor --exclude composer.lock --exclude tools/.upstream "$ROOT/" "$1/"; }

echo "==> Inti SDK"
copy "$WORK/sdk" && cd "$WORK/sdk"
ROOT="$ROOT" $PHP -r '
  $c = json_decode(file_get_contents("composer.json"), true);
  $up = json_decode(file_get_contents(getenv("ROOT")."/tools/.upstream/sentry-php/composer.json"), true)["require-dev"];
  foreach (["friendsofphp/php-cs-fixer","phpstan/phpstan","vimeo/psalm","phpstan/extension-installer","phpstan/phpstan-phpunit"] as $x) unset($up[$x]);
  $c["require-dev"] = $up;
  file_put_contents("composer.json", json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'
$COMPOSER config allow-plugins.php-http/discovery true
$COMPOSER update --no-interaction --no-progress -q --no-audit
$PHP vendor/bin/phpunit -c phpunit-sdk.xml.dist --testsuite sdk | grep -E "^(OK|Tests:)" || true

echo "==> Integrasi Laravel $LV"
copy "$WORK/lar" && cd "$WORK/lar"
$COMPOSER config allow-plugins.php-http/discovery true
# Carbon lama (Laravel 5.x) membawa plugin ini; tidak dibutuhkan untuk tes.
$COMPOSER config allow-plugins.kylekatarnls/update-helper false
$COMPOSER remove symfony/phpunit-bridge --dev --no-update -q || true
$COMPOSER require --dev "laravel/framework:$LV" "illuminate/support:$LV" "phpunit/phpunit:$PU" "orchestra/testbench:$TB" --no-update -q
$COMPOSER update --no-interaction --no-progress -q --no-audit
echo "    laravel/framework $($COMPOSER show laravel/framework | awk '/^versions/{print $4}')"
$PHP vendor/bin/phpunit -c phpunit-laravel.xml.dist | grep -E "^(OK|Tests:)" || true

echo "Bandingkan dengan hulu: inti 945 tes / 2.424 assertion (7 gagal juga di hulu"
echo "bila illuminate/support terpasang); Laravel 5.8: OK (67 tes, 128 assertion)."
