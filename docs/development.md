# Development and testing

## Test and develop without Docker

Requires PHP 8.3+, Composer 2, SQLite, GD, XML, mbstring, curl, zip and intl. On macOS, `brew install php composer` supplies the local toolchain. No PHP service needs to be started through Homebrew.

```bash
composer install
mkdir -p web/modules/custom
ln -s "$PWD/lifeos_sync" web/modules/custom/lifeos_sync
vendor/bin/phpcs --standard=phpcs.xml.dist
vendor/bin/phpunit -c phpunit.xml.dist
scripts/smoke.sh
```

The smoke script installs a disposable local site under `.demo/`, fetches the fixture, tests Drush/queue/cron, requests the real Views page/block, and checks anonymous settings access is forbidden. It stops both owned HTTP processes on exit, including failures. Ports default to 8093/8094 and can be changed with `LIFEOS_FIXTURE_PORT` and `LIFEOS_SITE_PORT`. Repeated runs preserve the disposable site. Remove `.demo/` and the generated `web/sites/default/settings.php` to start over with a fresh database.

To inspect that native demo interactively, run a fixture server from the repository root and keep its terminal open, then run Drupal's server from **inside `web/`** in a second terminal:

```bash
php -S 127.0.0.1:8093 -t fixtures
# Second terminal, repository root:
cd web
php -S 127.0.0.1:8094 -t "$PWD" "$PWD/.ht.router.php"
```

Open http://127.0.0.1:8094. Stop both servers with Ctrl-C when finished. If using different fixture ports, update the feed URL in settings.

[GitHub Actions](../.github/workflows/ci.yml) resolves and tests Drupal 10 and 11 separately on PHP 8.3, runs **Drupal + DrupalPractice PHPCS**, PHPUnit kernel tests and a full install/HTTP smoke. A separate job builds and tests the locked Docker demo. Kernel coverage includes entity replay/update, terminal status visibility, date validation/timezones, duplicate IDs, interval/backlog/fetch failure behavior, and actual Views queries.

