#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
root=$(pwd)
fixture_port=${LIFEOS_FIXTURE_PORT:-8093}
site_port=${LIFEOS_SITE_PORT:-8094}
fixture_pid=''
site_pid=''
cleanup() {
  result=$?
  if [[ $result -ne 0 ]]; then
    tail -30 .demo/fixture.log .demo/server.log 2>/dev/null || true
  fi
  for owned_pid in "$site_pid" "$fixture_pid"; do
    if [[ -n "$owned_pid" ]]; then
      kill -TERM "$owned_pid" 2>/dev/null || true
      wait "$owned_pid" 2>/dev/null || true
    fi
  done
  exit "$result"
}
trap cleanup EXIT
mkdir -p .demo
php -S "127.0.0.1:$fixture_port" -t fixtures > .demo/fixture.log 2>&1 &
fixture_pid=$!
export LIFEOS_FEED_URL="http://127.0.0.1:$fixture_port/lifeos-public.json"
scripts/install-demo.sh
vendor/bin/drush lifeos:sync --force --enqueue-only
vendor/bin/drush queue:run lifeos_sync
vendor/bin/drush state:set lifeos_sync.last_queued 0
vendor/bin/drush cron
vendor/bin/drush php:eval '
$count = \Drupal::entityQuery("node")->accessCheck(FALSE)->condition("type", "lifeos_item")->count()->execute();
if ((int) $count !== 5 || \Drupal::queue("lifeos_sync")->numberOfItems() !== 0) { throw new \RuntimeException("Expected five nodes and an empty queue."); }
'
(cd web && exec php -S "127.0.0.1:$site_port" -t "$root/web" "$root/web/.ht.router.php") > .demo/server.log 2>&1 &
site_pid=$!
for attempt in {1..30}; do
  if curl --fail --silent "http://127.0.0.1:$site_port/upcoming-deadlines" -o .demo/page.html; then
    break
  fi
  sleep 1
done
php -r '$html = file_get_contents(".demo/page.html"); foreach (["Thin-film lab report", "Sensor design proposal", "Surface science reading", "block-lifeos-demo-deadlines"] as $text) { if (!str_contains($html, $text)) { throw new RuntimeException("Missing demo content: " . $text); } }'
status=$(curl --silent -o /dev/null -w '%{http_code}' "http://127.0.0.1:$site_port/admin/config/services/lifeos-sync")
[[ "$status" == '403' ]]
echo 'Smoke passed: five nodes, cron/Drush/queue, Views page/block, protected settings.'
