=== Athletics Club Records ===
Contributors: brentwoodbeagles
Tags: athletics, records, power of 10, club, sports
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.3.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Maintains a UK athletics club's age-group records by pulling first-claim member performances from Power of 10 via a human-in-the-loop Claude in Chrome agent.

== Description ==

This plugin replaces hand-maintained Ninja Tables records pages with a database-backed system. Records are recomputed from raw performances under the current EA age-group structure (U14, U16, U18, U20, senior, masters), so changes to the structure don't require re-scraping.

Power of 10's data is gated by hCaptcha. Rather than try to bypass it, this plugin queues scrape jobs and lets you drive a Claude in Chrome agent on your own machine — solving any CAPTCHA challenges once, manually, in a real browser session.

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload.
2. Activate.
3. Go to "Athletics Records → Settings" and set your Power of 10 club UUID.
4. Click "Import existing Ninja Tables records" on the dashboard if migrating.
5. Replace your existing table shortcodes with [acr_records gender="women"] or [acr_records gender="men"].

== Frequently Asked Questions ==

= Does this work without Power of 10? =

You can still use it as a manual records database — admin can edit any cell. But the agent-driven refresh is the main reason it exists.

= Does it work for other clubs? =

Yes. Set your club's UUID in Settings.

== Changelog ==

= 0.3.7 =
* Recompute now applies UKA-standard inclusive age-band semantics: a junior performance counts for its own bucket AND every older junior bucket (U14 ⇒ U14/U16/U18/U20). A masters performance counts for its own bucket AND every younger masters bucket (V60 ⇒ V35/V40/V45/V50/V55/V60). SEN remains senior-only. So a 14-year-old's PB now legitimately holds the U16/U18/U20 records too if no older athlete has bettered it.
* Recompute now clears all non-override `source='recompute'` record cells before rebuilding, so cells whose qualifying performance moved buckets (or was deleted) no longer leave stale entries.

= 0.3.6 =
* New: GET /wp-json/acr/v1/athletes endpoint (auth required) returning id, name, sex, po10_id, first_claim for every athlete in the DB. Lets an agent look up Po10 UUIDs without scraping the rate-limited club rankings page. Supports ?sex= and ?first_claim= filters.

= 0.3.5 =
* Bugfix: ACR_Performances::insert_unique() now refreshes mutable fields on an existing match instead of returning early. A re-POST to /jobs/{id}/result can now correct an earlier wrong age_group_at_time (or venue, meeting, is_pb, position, etc.) without requiring a manual wp-admin edit. Only non-null/non-empty caller-supplied values overwrite — sparse re-POSTs never clobber populated fields with NULL.
* Dedupe key (athlete_id, event, perf_date / perf_year, performance_raw) is unchanged.

= 0.3.4 =
* UKA Competition Rules (TR3 S2 / TR3 S4) compliance — disallowed (event, age_group) combinations are no longer rendered.
* No more U14 Triple Jump, U14 Marathon, U14 race over 1 mile, U14 300m/400m, U16 race over 3000m, U16 Marathon/Half Marathon, U18 Marathon, U18 10000m, etc.
* New helper function acr_event_allowed($event, $age_group) for use anywhere that needs to filter on UKA eligibility.

= 0.3.3 =
* Shortcode default is now gender="all" — renders both sexes with a Sex column.
* New radio buttons at the top of the table: Women / Men / All. Live filter, no page reload.
* New "Hide empty rows" checkbox (default ON) — only show event/age cells that actually have a record.
* Empty cells render at 55% opacity so unfilled records aren't visually noisy when shown.
* Use [acr_records gender="women"] or [acr_records gender="men"] for single-sex pages (backward compatible).

= 0.3.2 =
* New: "Release stuck-claimed jobs" admin button on Agent Queue page.
* New: POST /wp-json/acr/v1/jobs/release-claimed REST endpoint (auth required).
* Resets jobs stuck in "claimed" status (i.e. fetched by an agent that never posted results) back to "pending" so they can be re-fetched.

= 0.3.1 =
* Bugfix: athletes.po10_id widened from VARCHAR(32) to VARCHAR(40). Po10 UUIDs are 36 chars and were being rejected on strict-mode MySQL hosts.
* Bugfix: dropped UNIQUE constraint on athletes.po10_id (multiple empty values were colliding when the seeder ran).
* Bugfix: jobs queue dedupe now considers (type, url, payload) instead of url alone — full rankings sweep can now actually enqueue all 270+ jobs.
* Migration runs on activation for sites upgrading from v0.3.0 in place.
* Uninstall now also deletes acr_last_seed.

= 0.3.0 =
* Pivoted to club-rankings sweep approach — one job per (year × sex × event), age=OVERALL returns all age groups inline-tagged.
* Drops athlete_search / athlete_profile complexity; ~270 jobs for full 2022-present seed, ~54 per incremental refresh.
* Parser captures wind values; w-prefixed (e.g. w2.4) flagged as wind-assisted automatically.
* Multiple stacked performance lines per athlete handled.
* SOP rewritten for the simpler flow.

= 0.2.0 =
* Po10 athlete URLs now use UUID format (/Home/Athlete/{uuid}).
* New athlete_search job type — discovers an athlete's Po10 UUID via the search form.
* Recompute now uses Po10's age_group_at_time on each performance instead of computing from DOB. DOB no longer required.
* New "Performances since" setting (default 2022-01-01) — records ignore older performances.
* SOP prompt rewritten to drive Best Known Performances table extraction (much simpler than year-tab walking).
* DB schema: performances table gains age_group_at_time, perf_year, is_pb, meeting columns. In-place upgrade on activation.

= 0.1.0 =
* Initial release.
