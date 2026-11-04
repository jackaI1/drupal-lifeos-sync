# Feed and importer reference

## Public feed contract and LifeOS provenance

[`fixtures/lifeos-public.json`](../fixtures/lifeos-public.json) is an authored public fixture, **not an existing LifeOS API response**. Its `tasks` and `timeZone` mirror the reduced coursework export in [lifeos-open's Microsoft 365 integration](https://github.com/jackaI1/lifeos-open/blob/main/integrations/m365/src/lifeos-source.ts). The added `schedule` list mirrors calendar-event data's `start.dateTime`, `end.dateTime`, `location` and `status` in [local-calendar.mjs](https://github.com/jackaI1/lifeos-open/blob/main/src/cloud/local-calendar.mjs). This module defines the combined envelope. It does not add a public endpoint to LifeOS.

The `feed` container serves the fixture at `http://feed/lifeos-public.json` inside the Compose network. A public raw copy is available [from this repository](https://raw.githubusercontent.com/jackaI1/drupal-lifeos-sync/main/fixtures/lifeos-public.json) after merge; that URL can be entered in the settings form on another Drupal site.

```json
{
  "timeZone": "America/Toronto",
  "schedule": [{
    "id": "workshop-1", "title": "Demo workshop", "status": "confirmed",
    "start": {"dateTime": "2099-11-06T10:45:00-05:00"},
    "end": {"dateTime": "2099-11-06T11:30:00-05:00"},
    "location": "Demo lab"
  }],
  "tasks": [{
    "id": "report-1", "title": "Demo report", "status": "todo",
    "courseCode": "DEMO 201", "dueAt": null, "dueDate": "2099-11-08"
  }]
}
```

Both lists and a valid `timeZone` are required. IDs are unique within each kind, up to 200 characters; titles and imported text fields are at most 255 characters. Text is displayed through Drupal field formatters with normal escaping.

| Source | Drupal field / behavior |
| --- | --- |
| `id` | `field_lifeos_id`, prefixed `task:` or `schedule:`; stable lookup identity |
| collection | `field_lifeos_kind` (`task` or `schedule`) |
| `title` | Node title |
| task `dueAt` | `field_lifeos_when`, converted to UTC |
| task `dueDate` when no `dueAt` | End of day, 23:59:59 in `timeZone`, converted to UTC |
| schedule `start.dateTime`, `end.dateTime` | `field_lifeos_when`, `field_lifeos_end`, converted to UTC |
| `courseCode`, `location`, `status` | `field_lifeos_course`, `field_lifeos_location`, `field_lifeos_status` |
| done/completed/cancelled/archived status | Unpublished; reopening republishes the same node |
| Undated task | Imported without a date; excluded from upcoming deadlines |

Task statuses: `todo`, `in_progress`, `blocked`, `done`, `completed`, `cancelled`, `archived`. Schedule statuses: `confirmed`, `cancelled`. Timestamps require seconds and an explicit `Z` or numeric offset. Impossible dates, duplicate IDs and invalid records reject the whole snapshot. Extra source fields such as `estimatedMinutes` and `courseTitle` are allowed but not stored.

## How it works

I use Drupal content records so the imported deadlines can appear in ordinary
Views pages and blocks. I keep cron and the immediate import command on the
same queue and importer so both entry points apply the same validation and
update rules. This is a local demonstration, rather than a hosted service.

```text
Public JSON → FeedClient → FeedNormalizer → SyncManager → Drupal database queue
                                                         ↓
                            Upcoming Views ← lifeos_item nodes ← QueueWorker → Importer
```

- `hook_cron()` calls the injected producer service; Drupal cron then spends up to 30 seconds processing the worker.
- `lifeos:sync` uses the same producer and worker. `--force` bypasses the interval; `--enqueue-only` leaves jobs for cron or `queue:run`.
- Feed validation happens before queue insertion. The producer uses a lock and a database transaction. Pending jobs block another snapshot, preventing repeated backlog growth. The supported backend is Drupal's default **DatabaseQueue**; replacing it with a remote queue needs a corresponding transaction strategy.
- The importer locks each source ID and updates the same node. Replays do not create duplicates or save unchanged entities. Failures leave queue records available for retry.
- HTTP fetching permits only HTTP(S), disables redirects, sets connection/read timeouts, and limits responses to **1 MiB / 500 records**. Only trusted administrators can choose the address; their choice authorizes a server-side request, including local addresses needed by the demo. Logs omit feed URLs and response bodies.
- Views filters published coursework with dates at or after now, sorts by deadline, paginates the page at ten, and limits the block to five. Views caching is disabled so time-based results remain current.

Omitted records are **retained**, because absence alone does not prove deletion. Use explicit terminal statuses to hide them. Keep IDs stable for one feed source; switching to an unrelated source can update colliding IDs. The importer owns imported fields and publication state, so edit the source instead of hand-editing those node fields. A failed record blocks newer snapshots until the underlying problem is repaired and the queue retried. No private-feed authentication, attachments, two-way sync or automatic deletion is implemented.

