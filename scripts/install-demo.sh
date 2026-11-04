#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p .demo
if [[ ! -f .demo/demo.sqlite ]]; then
  vendor/bin/drush site:install standard \
    --db-url="sqlite://localhost/$(pwd)/.demo/demo.sqlite" \
    --site-name='LifeOS Sync · Drupal demo' \
    --account-name=admin --account-pass="${DEMO_ADMIN_PASSWORD:-demo-local-only}" -y
fi
vendor/bin/drush en lifeos_sync -y
vendor/bin/drush php:script scripts/configure-demo.php
vendor/bin/drush lifeos:sync --force
vendor/bin/drush cr
