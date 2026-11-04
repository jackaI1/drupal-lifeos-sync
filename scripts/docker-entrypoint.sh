#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html
scripts/install-demo.sh
chown -R www-data:www-data .demo web/sites/default/files
exec apache2-foreground
