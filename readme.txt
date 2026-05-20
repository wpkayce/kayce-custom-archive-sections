=== Kayce Custom Archive Sections ===
Contributors: rohitkc32, wpkayce
Tags: archive, content sections, blog, category, gutenberg
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add reusable content sections before or after posts on any archive page — blog index, categories, tags, authors, search results, and more.

== Description ==

**Kayce Custom Archive Sections** lets you create and manage reusable content areas that appear automatically before or after the post loop on your WordPress archive pages — no theme file editing required.

Each section is a regular WordPress post, so you can design it with the **block editor (Gutenberg)** or **Elementor**, publish it once, and it shows up exactly where you set it.

The plugin works with both **classic PHP themes** and modern **Full Site Editing (FSE) / block themes** like Twenty Twenty-Five — out of the box, with no extra configuration.

**Archive locations supported:**

* Blog index (Posts page)
* All category archives
* Specific category archives — target individual categories with a built-in category picker
* Tag archives
* Author archives
* Search results pages
* Date archives

**Display controls:**

* **Before or after** the post loop — per section
* **Active / Inactive toggle** — publish a section without displaying it yet
* **Visibility by login state** — show to everyone, logged-in users only, or logged-out visitors only

**Admin experience:**

* At-a-glance list table with Active, Location, Position, and Visibility columns
* One-click **Duplicate** action to clone any section as a draft
* **Preview link** in the meta box that jumps straight to the relevant archive page
* Display order controlled via the standard **Order** field (Page Attributes)

**Performance & security:**

* **Transient caching** — sections are cached per location, position, and login state; cache is busted automatically when a section is saved or the plugin is deactivated
* Admin CSS and JavaScript load **only on the Archive Sections edit screen** — zero frontend asset overhead
* All inputs validated against whitelists; category IDs cast with `absint()`; nonces verified with `sanitize_key()`
* Works with all standard caching plugins

**For developers:**

* `kcas_query_args` — filter the WP_Query args before sections are fetched
* `kcas_section_html` — filter the HTML of an individual section
* `kcas_sections_html` — filter the complete output wrapper HTML
* `kcas_before_sections` — action fires before the sections wrapper is output
* `kcas_after_sections` — action fires after the sections wrapper is output
* Sections are wrapped in `<section class="kcas-archive-section" id="kcas-section-{ID}">` and `<div class="kcas-archive-sections kcas-archive-sections--{before|after}">` for easy CSS targeting

**Perfect for:**

* Hero banners or intro text above your blog feed
* Promotional blocks, CTA banners, or newsletter sign-ups below posts
* Category-specific messaging (e.g. a disclaimer on a legal advice category)
* Showing different content to logged-in members vs. public visitors
* Search results page enhancements

== Installation ==

**From the WordPress Plugin Directory**

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **Kayce Custom Archive Sections**.
3. Click **Install Now**, then **Activate**.

**Manual Installation**

1. Download the plugin ZIP file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**, then **Activate**.

**Getting Started**

1. After activation, go to **Archive Sections → Add New** in the admin menu.
2. Write or design your section content using the block editor or Elementor.
3. In the **Archive Section Settings** meta box on the right, configure:
   * **Location** — choose which archive page(s) to target. Selecting *Specific Categories* reveals a category picker.
   * **Position** — before or after the post loop.
   * **Active** — tick to enable the section immediately.
   * **Visibility** — show to everyone, logged-in users only, or logged-out visitors only.
4. Click **Publish**.
5. Visit the matching archive page — your section will appear automatically.

**Tip:** Use the **Order** field (Page Attributes panel) to control display order when multiple sections share the same location and position. Lower numbers appear first.

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes — including Full Site Editing (block) themes like Twenty Twenty-Five. The plugin automatically detects whether your active theme is a classic or block theme and uses the correct rendering pipeline for each.

= Can I use Elementor to design my sections? =

Yes. The Archive Section custom post type has `show_in_rest` enabled, making it fully compatible with the block editor. Elementor also supports editing custom post types, so you can build sections visually with Elementor as well.

= Can I target a specific category page? =

Yes. Set the location to **Specific Categories**, then tick the categories you want in the category picker that appears below. The section will only display on those category archives.

= Can I show different content to logged-in and logged-out users? =

Yes. Each section has a **Visibility** setting: *Everyone*, *Logged-in users only*, or *Logged-out visitors only*.

= How do I temporarily disable a section without deleting it? =

Uncheck the **Active** checkbox in the meta box and update the post. The section will be saved but will no longer display on the front end.

= How do I control the order of multiple sections? =

Set the **Order** value in the Page Attributes panel (lower numbers appear first). Sections with the same order value are sorted alphabetically by title.

= Will the sections appear on the front end straight away? =

Yes — as soon as you publish an active section with a location and position set, it will appear on the matching archive page. The transient cache is cleared automatically on save.

= Does the plugin add any database tables? =

No. The plugin uses WordPress's standard `wp_posts`, `wp_postmeta`, and `wp_options` tables only.

= Will it slow down my site? =

No. Sections are cached in transients after the first load, so subsequent page views skip the database query entirely. The cache is split by location, position, and login state to ensure visitors always see the correct content. Admin assets (CSS/JS) are only loaded on the Archive Sections edit screen.

= What happens when I delete the plugin? =

The included `uninstall.php` permanently deletes all Archive Section posts and their meta data when the plugin is removed from **Plugins → Installed Plugins → Delete**.

== Screenshots ==

1. The Archive Sections list table with Active, Location, Position, and Visibility columns.
2. The Archive Section Settings meta box showing all controls: location, specific-category picker, position, active toggle, visibility, and preview link.
3. A custom section displayed before the post loop on a category archive page.
4. A custom section displayed after the post loop on the blog index page.

== Changelog ==

= 1.1.0 =
**New features**

* **Quick Edit** — update Active, Position, and Visibility directly from the list table without opening the edit screen.
* **Bulk actions** — activate or deactivate multiple sections at once from the list table.
* **Sortable columns** — click Active, Location, Position, or Visibility column headers to sort the list table.
* **Settings page** (Archive Sections → Settings) — configure transient cache expiry (0–168 hours); set to 0 to disable caching entirely.
* **Activation welcome notice** — a one-time admin notice with a direct link to create the first section appears after plugin activation.
* **Single post support** — sections can now be targeted to individual post pages (`is_single()`), not just archive pages.

**UI improvements**

* Redesigned **Archive Section Settings** meta box with a CSS toggle switch for Active, a two-column Dashicon card grid for Location, segmented button groups for Position and Visibility, and a styled full-width Preview button.
* Category picker now includes a **live search / filter** input with a leading search icon.
* Category list items show a post-count badge; checked items are highlighted.
* **Friendly empty state** card shown in the list table when no sections exist yet, with a direct "Create your first section" call-to-action.

**Bug fixes**

* FSE / block themes: fixed duplicate section output on pages using the Query Loop block — sections are now injected once per page load.
* Cache: `single_post` location was missing from the cache locations list, causing cache-bust gaps on deactivation and uninstall.
* Frontend assets (`frontend.css`) are now enqueued only on archive and singular pages, not globally.

**Developer / i18n**

* All user-facing strings are now wrapped in the `kayce-custom-archive-sections` text domain.
* Added `languages/kayce-custom-archive-sections.pot` template for translators.
* Full CPT label set added (`archives`, `attributes`, `items_list`, `items_list_navigation`, etc.).

= 1.0.0 =
* Initial release.
* Custom post type (`kcas_section`) for creating reusable archive sections.
* Display sections before or after posts on the **blog index** and **category archive** pages.
* Support for **specific category targeting** with a scrollable category picker.
* Additional archive locations: **tag archives**, **author archives**, **search results**, **date archives**.
* Per-section **Active / Inactive toggle**.
* Per-section **login-state visibility** control (everyone / logged-in / logged-out).
* Admin list table columns: Active, Location, Position, Visibility.
* **Duplicate / Clone** row action with nonce and capability checks.
* **Preview link** in the meta box resolving the real archive URL per location type.
* **Transient caching** with version-stamped keys; login state included in cache key; targeted per-location cache busting on save.
* Admin CSS and JS enqueued only on the Archive Section edit screen (zero front-end overhead).
* Full compatibility with **classic themes** (loop_start / loop_end pipeline) and **FSE / block themes** (render_block filter pipeline).
* Fully compatible with **Gutenberg** and **Elementor**.
* Developer hooks: `kcas_query_args`, `kcas_section_html`, `kcas_sections_html`, `kcas_before_sections`, `kcas_after_sections`.
* `uninstall.php` for clean removal.

== Upgrade Notice ==

= 1.1.0 =
Feature update — adds Quick Edit, bulk actions, sortable columns, a Settings page for cache control, single-post support, and a redesigned meta box UI. No data migration required; simply update and activate.

= 1.0.0 =
Initial release — no upgrade steps required.
