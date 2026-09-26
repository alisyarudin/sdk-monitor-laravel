#!/usr/bin/env bash
# Jalankan test suite hulu terhadap kode hasil rename.
#
#   tools/test.sh           # kedua suite
#
# Inti SDK hanya jalan di PHPUnit ≤ 9, integrasi Laravel terbaru butuh
# PHPUnit ≥ 10 — sama seperti hulu (dua repo terpisah). Jadi dependensi dev
# dipasang dua kali, masing-masing untuk suite-nya.
set -euo pipefail
cd "$(dirname "$0")/.."

export COMPOSER_ROOT_VERSION=dev-main
# Config global composer bisa mematok platform.php (mesin dev Jasnita: 7.4),
# yang membuat versi Laravel yang dipilih tidak cocok dengan PHP sungguhan.
# COMPOSER_HOME sementara = tanpa config global; cache unduhan tetap dipakai.
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$(composer config --global cache-dir 2>/dev/null)}"
export COMPOSER_HOME="$(mktemp -d)"
trap 'rm -rf "$COMPOSER_HOME"' EXIT
PLATFORM=""
echo "    PHP $(php -r 'echo PHP_VERSION;')"

echo "==> Integrasi Laravel (Laravel & PHPUnit terbaru)"
rm -f composer.lock
composer update --with-all-dependencies --no-interaction --no-progress -q $PLATFORM
echo "    laravel/framework $(composer show laravel/framework | awk '/^versions/{print $4}')"
vendor/bin/phpunit -c phpunit-laravel.xml.dist

echo "==> Inti SDK (PHPUnit 9)"
composer update --with-all-dependencies --no-interaction --no-progress -q $PLATFORM \
  phpunit/phpunit:^9.6 orchestra/testbench:^8.0
vendor/bin/phpunit -c phpunit-sdk.xml.dist

rm -f composer.lock
echo "✓ Semua suite lolos."
