#!/usr/bin/env bash
# Arma el zip instalable del plugin (se sube desde el admin de TypeDock → Plugins).
#   scripts/zip.sh            → dist/sportiva-plugin-v<versión>.zip
set -euo pipefail
cd "$(dirname "$0")/.."

bash scripts/css.sh > /dev/null
php tests/run.php > /dev/null || { echo "Las pruebas fallan: no se arma el zip." >&2; exit 1; }

v=$(php -r 'echo json_decode(file_get_contents("sportiva/plugin.json"), true)["version"];')
mkdir -p dist
rm -f "dist/sportiva-plugin-v${v}.zip"
zip -qr "dist/sportiva-plugin-v${v}.zip" sportiva -x '*.DS_Store' 'sportiva/assets-src/*'
echo "dist/sportiva-plugin-v${v}.zip"
