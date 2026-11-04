# LifeOS Sync Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship and merge a working public Drupal 10/11 module demonstration.
**Architecture:** Validated public snapshot → cron/Drush producer → Drupal Queue → idempotent node importer. Installable fields and Views config provide the UI.
**Tech Stack:** PHP, Drupal 10/11, Drush 13, PHPUnit kernel tests, Drupal PHPCS, Docker Compose.
**Spec:** docs/superpowers/specs/2026-11-03-lifeos-sync-design.md

## Global Constraints
- Module machine name `lifeos_sync`; preserve repository visibility PUBLIC.
- Work on existing isolated branch; squash merge only after CI passes.
- Fictional fixture only; bounded logs and explicit resource cleanup.

## Review Focus
- Repeated imports update existing nodes without duplication.
- Invalid input and HTTP failure preserve existing data and interval state.
- Date-only deadlines respect source timezone and reject impossible dates.
- Cancelled/completed records are hidden; omitted records are retained.
- Cron/Drush share one queue and do not build an unbounded backlog.

### Task 1: Import pipeline
Files: `lifeos_sync/src/{FeedClient,FeedNormalizer,Importer,SyncManager}.php`, kernel tests, services, fields config.
- [ ] Write kernel coverage for fixture import, replay/update, hidden closed records, invalid dates and snapshots, interval/backlog/failure.
- [ ] Observe failure before implementation.
- [ ] Implement bounded fetch, validation, locked producer and idempotent importer.
- [ ] Run kernel tests; commit.

### Task 2: Drupal entry points and presentation
Files: settings form, permissions/routes/menu, QueueWorker, Drush command, Views config.
- [ ] Wire dependency injection and shared service calls.
- [ ] Verify module install, command, cron, Views page/block and settings on a real Drupal site.
- [ ] Commit verified implementation.

### Task 3: Reproducible demonstration and release
Files: Dockerfile, compose, demo setup, CI, README, screenshots.
- [ ] Add clean setup and a Drupal 10/11 CI matrix with kernel tests, PHPCS and smoke checks.
- [ ] Capture real Drupal screenshots and document setup, mapping and skills.
- [ ] Run verification, request one whole-branch review, address material findings.
- [ ] Stop owned fixtures/pages, record cleanup, push, create/link PR; merge after passing CI.
