# LifeOS Sync

Build a public Drupal 10/11 development demonstration for Jack Lai. This is a module repository, not a hosted production service. The user authorizes implementation, public push, PR creation and squash merge after CI passes, without intermediate approvals.

Use a node bundle (`lifeos_item`) with source ID, item kind, date/time, end, course, location and status fields. A feed client validates a complete bounded JSON snapshot before enqueueing. Cron checks the configured interval and enqueues records; a QueueWorker calls an idempotent importer. A Drush command invokes the same services. Settings use Form API, permission checks, schema and dependency injection. A Views page and block show published future coursework deadlines sorted by due date. Closed tasks and cancelled events are unpublished; omitted records are retained. IDs are namespaced by kind. An administrator changing feed sources must understand that IDs belong to one source.

Ship fictional public fixture data modelled on lifeos-open coursework and calendar shapes, with provenance links and explicit disclosure that LifeOS itself does not supply this public endpoint. Date-only deadlines use end of day in the feed timezone, then convert to UTC. Reject malformed snapshots without partial queue writes. Use bounded HTTP reads, no redirects, timeouts, and an administrator-only feed setting.

Docker Compose builds a PHP/Apache Drupal site with SQLite, installs the module, imports the fixture and exposes the Views page. CI runs kernel tests and Drupal PHPCS against both supported core majors. Include reproducible setup, actual screenshots, limitations, skills demonstrated, and cleanup instructions. No private LifeOS data.
