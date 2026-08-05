#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
VERSION="17.0.0-rc.1"
OUT="$ROOT/build/region9-live-studio-$VERSION.zip"
MANIFEST="$ROOT/build/region9-live-studio-$VERSION-manifest.json"
mkdir -p "$ROOT/build"
(cd "$ROOT/plugins" && zip -qr "$OUT" region9-live-studio)
unzip -t "$OUT"
(cd "$ROOT/build" && sha256sum "$(basename "$OUT")" > SHA256SUMS.txt)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
SHA=$(cut -d ' ' -f1 "$ROOT/build/SHA256SUMS.txt")
COMMIT=$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)
BUILT_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
cat > "$MANIFEST" <<JSON
{
  "version": "$VERSION",
  "build_commit": "$COMMIT",
  "build_time_utc": "$BUILT_AT",
  "artifacts": [
    {
      "file": "region9-live-studio-$VERSION.zip",
      "size_bytes": $SIZE,
      "sha256": "$SHA"
    }
  ],
  "checksums_file": "SHA256SUMS.txt",
  "known_limitations": "Real 24-48 hour staging soak, browser accessibility review, and production WordPress matrix sign-off require the staging checklist evidence."
}
JSON
(cd "$ROOT/build" && sha256sum -c SHA256SUMS.txt)
echo "$OUT"
echo "$MANIFEST"
