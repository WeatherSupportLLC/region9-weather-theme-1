#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
VERSION="17.1.0"
TOP="region9-live-studio"
OUT="$ROOT/build/region9-live-studio-$VERSION.zip"
MANIFEST="$ROOT/build/region9-live-studio-$VERSION-manifest.json"
TMP="$ROOT/build/plugin-package-tmp"
PKG="$TMP/$TOP"
rm -rf "$TMP" "$OUT" "$MANIFEST"
mkdir -p "$PKG/includes" "$PKG/data" "$ROOT/build"
cp "$ROOT/plugins/$TOP/region9-live-studio.php" "$ROOT/plugins/$TOP/README.md" "$PKG/"
cp "$ROOT/plugins/$TOP/includes/"*.php "$PKG/includes/"
cp "$ROOT/plugins/$TOP/data/region9-counties.geojson" "$PKG/data/"
find "$PKG" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
grep -q 'Version: 17.1.0' "$PKG/region9-live-studio.php"
grep -q "define('R9LS_VERSION', '17.1.0')" "$PKG/region9-live-studio.php"
(cd "$TMP" && zip -qr "$OUT" "$TOP")
unzip -t "$OUT" >/dev/null
test "$(zipinfo -1 "$OUT" | cut -d/ -f1 | sort -u)" = "$TOP"
for required in "$TOP/region9-live-studio.php" "$TOP/includes/class-product-generator.php" "$TOP/data/region9-counties.geojson"; do unzip -Z1 "$OUT" | grep -Fxq "$required"; done
if unzip -Z1 "$OUT" | grep -Eq '(^|/)(tests|\.git|\.github|build|scripts|docs|cache)(/|$)|\.zip$|wp-config\.php'; then echo 'Development-only file included in plugin ZIP' >&2; exit 1; fi
SIZE=$(wc -c < "$OUT" | tr -d ' '); SHA=$(sha256sum "$OUT" | cut -d' ' -f1); COMMIT=$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown); BUILT_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
cat > "$MANIFEST" <<JSON
{
  "component": "Region 9 Live Studio plugin",
  "version": "$VERSION",
  "artifact_filename": "$(basename "$OUT")",
  "top_level_folder": "$TOP",
  "build_commit": "$COMMIT",
  "build_timestamp": "$BUILT_AT",
  "byte_size": $SIZE,
  "sha256": "$SHA",
  "php_requirements": ">=7.4",
  "wordpress_requirements": ">=6.0",
  "known_limitations": ["Production source availability requires upstream services.", "A 24-48 hour staging soak and browser review require staging infrastructure."]
}
JSON
(cd "$ROOT/build" && find . -maxdepth 1 -type f \( -name 'region9-live-studio-*.zip' -o -name 'region9-weather-theme-*.zip' \) -printf '%f\0' | sort -z | xargs -0 sha256sum > SHA256SUMS.txt && sha256sum -c SHA256SUMS.txt)
rm -rf "$TMP"
printf '%s\n%s\n' "$OUT" "$MANIFEST"
