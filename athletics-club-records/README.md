# Athletics Club Records

A WordPress plugin that maintains an athletics club's age-group records by
pulling first-claim member performances from [Power of 10](https://www.powerof10.uk)
via a human-in-the-loop **Claude in Chrome** scraping agent.

Originally built for [Brentwood Beagles Athletics Club](https://www.beagles.org.uk)
to replace a hand-maintained Ninja Tables page that fell out of date and didn't
keep pace with the 2026 EA age-group restructure (U14 / U16 / U18 / U20).

## Why an agent instead of a server-side scraper?

Power of 10's ranking endpoint is protected by hCaptcha. A traditional cron
job using `cURL` or Playwright would get blocked on the first request. Instead,
this plugin:

1. Builds a **queue** of Po10 URLs to refresh.
2. Exposes a **REST API** for an external agent to read jobs and post results.
3. Provides a **standard operating prompt** that the admin pastes into Claude
   in Chrome on their own machine — solving any hCaptcha challenges once,
   manually, in a real browser session.

The plugin itself stays "boring PHP" — no Chromium dependency on the WordPress
host, no token exchange beyond a single bearer token. Compatible with any WP
hosting environment.

## Architecture

```
   ┌────────────────────────┐                ┌───────────────────────┐
   │   WordPress + plugin   │                │   powerof10.uk        │
   │   (records DB, queue,  │                │   (CAPTCHA-gated)     │
   │   REST API, admin UI)  │                └──────────┬────────────┘
   └──────────┬─────────────┘                           ▲
              │ REST                                    │ page loads (with
              ▼                                         │ human-solved CAPTCHAs)
   ┌────────────────────────┐  reads queue, posts back  │
   │  Claude in Chrome      │ ──────────────────────────┘
   │  (your laptop/PC,      │
   │  signed in already)    │
   └────────────────────────┘
```

## Data model (Option C — records recomputed from raw performances)

Rather than storing records as the primary entity, the plugin stores
**every performance** the agent observes (with date and athlete DOB). Records
are derived: for each `(sex, age_group, event)` cell, find the best
performance among first-claim members where the athlete's age on the
performance date falls in the age group's range.

This means future age-group restructures are a SQL change, not a re-scrape.

Tables:
- `wp_acr_athletes` — name, sex, DOB, Po10 ID, first-claim flag.
- `wp_acr_performances` — every (athlete, event, perf, date) tuple.
- `wp_acr_records` — derived per cell; admin can `manual_override` any cell.
- `wp_acr_scrape_jobs` — agent queue.

## Installation

1. Download the latest release zip (see Releases) or zip the repo:
   `zip -r athletics-club-records.zip athletics-club-records`
2. WP admin → Plugins → Add New → Upload Plugin → choose the zip → Activate.
3. Go to **Athletics Records → Settings**, set your Power of 10 club UUID.
4. Go to **Athletics Records → Dashboard**, click "Import existing Ninja
   Tables records" to seed the database from the legacy table.
5. On a public page, replace the Ninja Tables shortcode with
   `[acr_records gender="women"]` or use the Gutenberg block.

## First refresh

1. **Athletics Records → Agent Queue** → choose "Bootstrap" strategy → click
   "Plan refresh".
2. Copy the SOP prompt (button on the same page).
3. Open Claude in Chrome on your machine, paste the prompt, hit send.
4. Solve the hCaptcha when prompted; the session cookie will carry through.
5. Watch the queue drain on the same admin page.
6. Click "Recompute records now" on the dashboard when it's done.

## Shortcodes / blocks

```text
[acr_records gender="women"]
[acr_records gender="men"]
[acr_records gender="women" filter="0"]   /* hide the search box */
```

Or use the "Athletics Club Records" Gutenberg block.

The rendered markup deliberately reuses Ninja Tables CSS class names
(`ninja_column_1` etc) so any styling currently applied to your records pages
keeps working.

## Settings

- **Club name / short name** — branding for admin and frontend.
- **Power of 10 club UUID** — found in the URL of your Po10 club page.
- **Ninja Tables IDs** — the legacy table IDs to seed from.
- **Record colour** — hex code for the performance text colour.
- **Agent token** — rotates on demand; re-paste SOP after rotating.

## REST API

All under `/wp-json/acr/v1/`:

| Method | Endpoint               | Description                                |
|--------|------------------------|--------------------------------------------|
| GET    | `/jobs`                | Next batch of pending jobs (auth)          |
| POST   | `/jobs/plan`           | Enqueue new jobs (auth)                    |
| POST   | `/jobs/{id}/result`    | Submit scrape result (auth)                |
| POST   | `/jobs/{id}/fail`      | Mark a job failed (auth)                   |
| GET    | `/records?sex=F\|M`    | Public read-only records                   |
| POST   | `/recompute`           | Trigger a recompute pass (auth)            |

Bearer-token auth: `Authorization: Bearer <agent_token>`.

## Development

This is a single-file-per-class plugin with no build step. Edit, save, reload.

```
athletics-club-records/
├── athletics-club-records.php   # main bootstrap
├── uninstall.php
├── includes/
│   ├── class-acr-activator.php
│   ├── class-acr-deactivator.php
│   ├── class-acr-perfvalue.php
│   ├── class-acr-athletes.php
│   ├── class-acr-performances.php
│   ├── class-acr-records.php
│   ├── class-acr-jobs.php
│   ├── class-acr-planner.php
│   ├── class-acr-seeder.php
│   ├── class-acr-po10-parser.php
│   ├── class-acr-recompute.php
│   ├── class-acr-rest.php
│   ├── class-acr-shortcode.php
│   ├── class-acr-block.php
│   └── class-acr-admin.php
├── admin/
│   ├── assets/admin.css
│   └── views/{dashboard,records,athletes,agent-queue,settings}.php
├── public/
│   └── assets/public.css
└── docs/
    └── claude-in-chrome-prompt.md
```

## Re-using for other clubs

The plugin is generic — only the defaults are BBAC-specific. To use for
another club, just set:

- Club name + short name
- Power of 10 club UUID
- (Optional) Ninja Tables IDs if you're migrating from a Ninja Tables setup
- Otherwise leave Ninja Tables IDs blank and the seed step becomes a no-op.

## Licence

GPL-2.0-or-later. See `LICENSE`.

## Status

v0.1.0 — first cut. The agent loop and recompute engine are functional; the
"realtime image" share-card renderer is planned for v0.2.
