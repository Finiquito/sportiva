#!/usr/bin/env bash
# Junta el CSS del diseño y el del front en sportiva/assets/sportiva.css (sin dependencias).
#   scripts/css.sh
set -euo pipefail
cd "$(dirname "$0")/../sportiva"
out=assets/sportiva.css
{
  echo "/* Sportiva · generado por scripts/css.sh — no editar a mano (fuente: assets-src/) */"
  cat assets-src/css/tokens.css assets-src/css/mobile.css assets-src/css/shared.css assets-src/css/components.css
  for f in assets-src/css/x_*.css; do cat "$f"; done
  cat assets-src/app.css
} | sed -e 's#url("{{GRANO}}")#url("grano.png")#g' | python3 -c "
import re,sys
t=sys.stdin.read()
t=re.sub(r'/\*(?!\s*Sportiva).*?\*/','',t,flags=re.S)
t=re.sub(r'\n\s*\n+','\n',t)
sys.stdout.write(t)
" > "$out"
echo "$out ($(wc -c < "$out") bytes)"
