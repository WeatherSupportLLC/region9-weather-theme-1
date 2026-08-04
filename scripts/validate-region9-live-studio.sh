#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/plugins/region9-live-studio"

find "$PLUGIN_DIR" -name '*.php' -print0 | xargs -0 -n1 php -l
"$ROOT_DIR/scripts/build-region9-live-studio.sh" >/dev/null
unzip -l "$ROOT_DIR/dist/region9-live-studio.zip" | grep -q 'region9-live-studio/region9-live-studio.php'
echo "Region 9 Live Studio validation passed."
