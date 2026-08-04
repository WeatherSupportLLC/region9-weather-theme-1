#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
OUT="$ROOT/build/region9-live-studio-alpha4.zip"
mkdir -p "$ROOT/build"
(cd "$ROOT/plugins" && zip -qr "$OUT" region9-live-studio)
unzip -t "$OUT"
echo "$OUT"
