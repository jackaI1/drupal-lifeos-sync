# LifeOS Sync

Import a public LifeOS-shaped schedule and coursework feed into Drupal 10/11.

[![Drupal module CI](https://github.com/jackaI1/drupal-lifeos-sync/actions/workflows/ci.yml/badge.svg)](https://github.com/jackaI1/drupal-lifeos-sync/actions/workflows/ci.yml)

## Overview

LifeOS keeps schedule items and coursework deadlines together. I wanted to bring that information into Drupal, where it could become content that Views can filter and display, without sharing my private workspace.

I made `lifeos_sync` as a personal integration project. It imports a public JSON feed into a **LifeOS item** content type, updates existing records when the feed changes, and shows upcoming coursework on a page and block. Cron runs the import through Drupal's queue; a Drush command can run it immediately.

The included feed is fictional, modelled on the public [LifeOS source code](https://github.com/jackaI1/lifeos-open). It is a fixture for this project, not a live LifeOS API. The dates use 2099 so the example deadlines stay visible. This repository is public; the Drupal site runs locally.

![Upcoming deadlines page and block in Drupal](docs/screenshots/upcoming-deadlines.png)

## How to run

Install Docker with Compose v2, then:

```bash
git clone https://github.com/jackaI1/drupal-lifeos-sync.git
cd drupal-lifeos-sync
docker compose up --build
```

The first build downloads Drupal and installs the site. When it finishes, open **http://localhost:8080/upcoming-deadlines**. You will see three coursework deadlines; two schedule items are also imported and can be found under Content.

Log in at `/user/login` with `admin` / `demo-local-only`. To choose another local password, set `DEMO_ADMIN_PASSWORD` before the first install. The demo binds to loopback and is intended for local use.

Open **Configuration → Web services → LifeOS Sync** (`/admin/config/services/lifeos-sync`) to change the feed URL or minimum sync interval. An empty URL disables new fetches. The fixture is served by the second Compose container; you can edit `fixtures/lifeos-public.json` and run another import:

```bash
docker compose exec drupal vendor/bin/drush lifeos:sync --force
```

Repeated imports update the same nodes. Completed or cancelled items are unpublished; records missing from a later feed are retained. The deadlines page shows only published, future coursework.

![Feed settings in Drupal](docs/screenshots/settings.png)

To exercise cron or stop the site:

```bash
docker compose exec drupal vendor/bin/drush cron
docker compose down
```

Stopping preserves the database. `docker compose down --volumes` resets this project's demo data. The interval setting does not start a scheduler: for ongoing use, schedule `drush cron` on the host.

## Use it on another Drupal site

Copy `lifeos_sync/` into `web/modules/custom/`, enable it with `drush en lifeos_sync -y`, and set a public HTTP(S) feed URL in its settings. Drupal 10.3+ or 11 with Node, Datetime and Views is supported. The import command uses Drush 13.

Visit `/upcoming-deadlines` or place the **Upcoming deadlines** block in your theme. Only the included demo automatically places the block. Grant **Administer LifeOS Sync** only to trusted administrators: the configured URL is fetched by the server.

## More detail

- [Feed format, source mapping and importer behavior](docs/feed-and-importer.md)
- [Native setup, tests and CI](docs/development.md)
- [Verification and temporary-resource cleanup](docs/verification.md)

Licensed under [GPL-2.0-or-later](LICENSE.txt).
