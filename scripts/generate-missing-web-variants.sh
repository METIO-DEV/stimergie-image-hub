#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

php artisan images:generate-variants \
  --source-prefix="${SOURCE_PREFIX:-photos}" \
  --target-prefix="${TARGET_PREFIX:-images}" \
  --missing-web-only \
  "$@"
