#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
VERSION="17.0.0-rc.1"
THEME_SLUG="${R9_THEME_SLUG:-$(basename "$ROOT") }"
THEME_SLUG="${THEME_SLUG% }"
OUT="$ROOT/build/region9-weather-theme-$VERSION.zip"
TMP="$ROOT/build/theme-package-tmp"
PKG="$TMP/$THEME_SLUG"
rm -rf "$TMP" "$OUT"
mkdir -p "$PKG" "$ROOT/build"
find "$ROOT" -maxdepth 1 -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find "$ROOT/inc" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
cp "$ROOT/style.css" "$ROOT/functions.php" "$PKG/"
for file in index.php header.php footer.php front-page.php page.php template-parts-studio-home.php README.txt; do
  if [ -f "$ROOT/$file" ]; then cp "$ROOT/$file" "$PKG/"; fi
done
cp -R "$ROOT/inc" "$ROOT/assets" "$PKG/"
(cd "$TMP" && zip -qr "$OUT" "$THEME_SLUG")
unzip -t "$OUT"
unzip -l "$OUT" "$THEME_SLUG/style.css" | grep -q "$THEME_SLUG/style.css"
unzip -l "$OUT" "$THEME_SLUG/functions.php" | grep -q "$THEME_SLUG/functions.php"
unzip -l "$OUT" "$THEME_SLUG/inc/admin-studio.php" | grep -q "$THEME_SLUG/inc/admin-studio.php"
unzip -l "$OUT" "$THEME_SLUG/inc/live-studio-integration.php" | grep -q "$THEME_SLUG/inc/live-studio-integration.php"
(cd "$ROOT/build" && sha256sum "$(basename "$OUT")" >> SHA256SUMS.txt)
rm -rf "$TMP"
echo "$OUT"
echo "theme_folder=$THEME_SLUG"
