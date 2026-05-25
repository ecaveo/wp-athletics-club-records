=== Athletics Club Records ===
Contributors: brentwoodbeagles
Tags: athletics, records, power of 10, club, sports
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
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

= 0.1.0 =
* Initial release.
