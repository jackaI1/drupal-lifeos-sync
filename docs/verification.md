# Verification and resource cleanup

The implementation was exercised on a real Drupal 11.4.11 standard-profile site with PHP 8.5.12 on macOS. The repository's locked project targets PHP 8.3, and CI separately resolves supported Drupal 10 and 11 releases on PHP 8.3.

- PHPUnit kernel suite: 8 tests covering replay/update, date/time validation, queue interval/backlog/failure, bounded/invalid HTTP snapshots and actual Views page/block queries.
- Drupal and DrupalPractice PHPCS: clean.
- Composer strict validation: clean.
- Full install/HTTP smoke: five imported nodes, idempotent Drush import, separate queue execution, cron, Views page and placed block, and anonymous settings denial.
- Browser: actual Olivero page/block and administrator settings screenshots captured from Drupal; saving the interval from 3600 to 900 was verified through Drush, then restored to 3600.
- Independent read-only review found an unsuitable Docker SQLite base and falsey malformed date inputs. The base now uses Debian Trixie with a build-time SQLite >=3.45 assertion. Date and offset regressions were observed failing before the fix and passing afterward.
- Docker runtime and Drupal 10 compatibility are verified by the required GitHub Actions jobs before merge, rather than inferred from the local Drupal 11 run. See the linked workflow history in the README.

## Temporary resource ownership and cleanup

Owner: Codex in the `skills/drupal-module` worktree. Final screenshot servers:

| Resource | PID | Start (local, 2026-11-03) | Port | Cleanup |
| --- | --- | --- | --- | --- |
| Fictional JSON fixture PHP server | 78295 | 22:52:14 | 8092 | `kill -TERM 78295` |
| Drupal screenshot PHP server | 85409 | 22:54:28 | 8091 | `kill -TERM 85409` |

Earlier iterations of the owned Drupal server (PIDs 78336 and 80205) were stopped before their replacements started. The native smoke script uses PID-scoped EXIT cleanup for its two servers, including failure paths.

The screenshot test tab was explicitly closed and the preview tool reported no remaining tab. `lsof` verified no listeners on ports 8091–8094 after cleanup. The two final servers had a combined 11,488 KiB RSS immediately before stopping. System-wide free memory changed from 50% before the campaign to 42% at cleanup; concurrent owner applications and other sessions remained active, so this is not an attributable memory improvement or evidence of a permanent memory fix. No owner browser processes, LifeOS workers or applications were stopped. No preview was retained.
