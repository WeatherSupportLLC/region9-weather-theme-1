#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
VERSION="17.1.0"
THEME_SLUG="${R9_THEME_SLUG:-$(basename "$ROOT") }"; THEME_SLUG="${THEME_SLUG% }"
OUT="$ROOT/build/region9-weather-theme-$VERSION.zip"
MANIFEST="$ROOT/build/region9-weather-theme-$VERSION-manifest.json"
TMP="$ROOT/build/theme-package-tmp"; PKG="$TMP/$THEME_SLUG"
rm -rf "$TMP" "$OUT" "$MANIFEST"; mkdir -p "$PKG" "$ROOT/build"
find "$ROOT" -maxdepth 1 -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find "$ROOT/inc" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
cp "$ROOT/style.css" "$ROOT/functions.php" "$PKG/"
for file in index.php header.php footer.php front-page.php page.php sidebar.php template-parts-studio-home.php README.txt; do test ! -f "$ROOT/$file" || cp "$ROOT/$file" "$PKG/"; done
cp -R "$ROOT/inc" "$ROOT/assets" "$PKG/"
grep -q '^Theme Name: Region 9 Weather Studio - GeneratePress Child$' "$PKG/style.css"
grep -q '^Template: generatepress$' "$PKG/style.css"
grep -q '^Version: 17.1.0$' "$PKG/style.css"
(cd "$TMP" && zip -qr "$OUT" "$THEME_SLUG")
unzip -t "$OUT" >/dev/null
test "$(zipinfo -1 "$OUT" | cut -d/ -f1 | sort -u)" = "$THEME_SLUG"
for required in "$THEME_SLUG/style.css" "$THEME_SLUG/functions.php" "$THEME_SLUG/front-page.php" "$THEME_SLUG/sidebar.php" "$THEME_SLUG/inc/live-studio-integration.php"; do unzip -Z1 "$OUT" | grep -Fxq "$required"; done
if unzip -Z1 "$OUT" | grep -Eq '(^|/)(plugins|scripts|tests|docs|\.git|\.github|build|cache)(/|$)|\.zip$|wp-config\.php'; then echo 'Development-only file included in theme ZIP' >&2; exit 1; fi
SIZE=$(wc -c < "$OUT" | tr -d ' '); SHA=$(sha256sum "$OUT" | cut -d' ' -f1); COMMIT=$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown); BUILT_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
cat > "$MANIFEST" <<JSON
{
  "component": "Region 9 Weather Studio GeneratePress child theme",
  "version": "$VERSION",
  "artifact_filename": "$(basename "$OUT")",
  "top_level_folder": "$THEME_SLUG",
  "build_commit": "$COMMIT",
  "build_timestamp": "$BUILT_AT",
  "byte_size": $SIZE,
  "sha256": "$SHA",
  "php_requirements": ">=7.4",
  "wordpress_requirements": ">=6.0 with GeneratePress parent theme",
  "known_limitations": ["Requires the existing GeneratePress parent theme.", "GP Premium and responsive visual acceptance require staging verification."]
}
JSON
(cd "$ROOT/build" && find . -maxdepth 1 -type f \( -name 'region9-live-studio-*.zip' -o -name 'region9-weather-theme-*.zip' \) -printf '%f\0' | sort -z | xargs -0 sha256sum > SHA256SUMS.txt && sha256sum -c SHA256SUMS.txt)
rm -rf "$TMP"
printf '%s\n%s\n' "$OUT" "$MANIFEST"
