# LifeOS Sync

I built a way to show LifeOS deadlines on a website because I wanted to publish a selected schedule without sharing my private workspace.

[![Drupal module CI](https://github.com/jackaI1/drupal-lifeos-sync/actions/workflows/ci.yml/badge.svg)](https://github.com/jackaI1/drupal-lifeos-sync/actions/workflows/ci.yml)

## Overview

LifeOS is my personal workspace for schedules and coursework. I wanted a
separate website to show selected deadlines while keeping the rest of that
workspace private. I use Drupal, a website content-management system, to turn
those selected records into pages I can display and filter.

This personal project adds `lifeos_sync`, a Drupal module, or add-on. It reads a
public feed, creates website content for each record and updates the same items
when that feed changes. I can see upcoming coursework on a page and in a block,
a small list placed alongside other page content. The local demo below lets me
try this without connecting a private workspace.

The included feed is fictional, modelled on the public [LifeOS source code](https://github.com/jackaI1/lifeos-open). It is a fixture for this project, not a live connection to LifeOS. The dates use 2099 so the example deadlines stay visible. This repository is public; the Drupal site runs locally.

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

Open **Configuration → Web services → LifeOS Sync** (`/admin/config/services/lifeos-sync`) to change the feed URL or minimum sync interval. An empty URL disables new fetches. The sample feed is served by the second Compose container, an isolated running
service; you can edit `fixtures/lifeos-public.json` and run another import:

```bash
docker compose exec drupal vendor/bin/drush lifeos:sync --force
```

Repeated imports update the same content items, called nodes in Drupal. Completed or cancelled items are unpublished; records missing from a later feed are retained. The deadlines page shows only published, future coursework.

![Feed settings in Drupal](docs/screenshots/settings.png)

Drupal calls its scheduled maintenance runner cron. Drush is its command-line
tool. To run maintenance once or stop the site:

```bash
docker compose exec drupal vendor/bin/drush cron
docker compose down
```

Stopping preserves the database. `docker compose down --volumes` resets this project's demo data. The interval setting does not start a scheduler: for ongoing use, schedule `drush cron` on the host.

## Use it on another Drupal site

Copy `lifeos_sync/` into `web/modules/custom/`, enable it with `drush en lifeos_sync -y`, and set a public HTTP(S) feed URL in its settings. Drupal 10.3+ or 11 with Node (content records), Datetime (date fields) and Views
(filterable lists) is supported. The import command uses Drush 13.

Visit `/upcoming-deadlines` or place the **Upcoming deadlines** block in your theme. Only the included demo automatically places the block. Grant **Administer LifeOS Sync** only to trusted administrators: the configured URL is fetched by the server.

## How it works

The feed uses JSON, a structured text format. I validate the whole response
before placing records into Drupal's queue, a list of work to process. Stable
source IDs let later imports find and update existing items. The importer hides
completed or cancelled items but keeps items missing from a later feed.

The cron runner and the immediate `lifeos:sync` command share that importer.
The minimum interval prevents overly frequent fetches; it does not schedule
them. The deadline page uses Views to show published future coursework.
Administrator permissions control who can change the feed URL.

## More detail

- [Feed format, source mapping and importer behavior](docs/feed-and-importer.md)
- [Native setup, tests and CI](docs/development.md)
- [Verification and temporary-resource cleanup](docs/verification.md)

Licensed under [GPL-2.0-or-later](LICENSE.txt).
