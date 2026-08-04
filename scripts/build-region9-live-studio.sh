#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/plugins/region9-live-studio"
DIST_DIR="$ROOT_DIR/dist"
ZIP_PATH="$DIST_DIR/region9-live-studio.zip"

rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

(cd "$ROOT_DIR/plugins" && zip -qr "$ZIP_PATH" region9-live-studio -x '*/.DS_Store' '*/node_modules/*' '*/vendor/*')
unzip -tq "$ZIP_PATH"
echo "$ZIP_PATH"
