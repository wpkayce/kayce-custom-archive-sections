# Kayce Custom Archive Sections

**Contributors:** wpkayce
**Requires at least:** WordPress 6.0
**Tested up to:** WordPress 6.9
**Requires PHP:** 7.4
**Stable tag:** 1.0.0
**License:** GPL-2.0+
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Add reusable content sections before or after posts on any archive page — blog index, categories, tags, authors, search results, and more.

---

## Description

**Kayce Custom Archive Sections** lets you create and manage reusable content areas that appear automatically before or after the post loop on your WordPress archive pages — no theme file editing required.

Each section is a regular WordPress post, so you can design it with the **block editor (Gutenberg)** or **Elementor**, publish it once, and it shows up exactly where you set it.

The plugin works with both **classic PHP themes** and modern **Full Site Editing (FSE) / block themes** like Twenty Twenty-Five — out of the box, with no extra configuration.

### Archive locations supported

- Blog index (Posts page)
- All category archives
- Specific category archives — target individual categories with a built-in category picker
- Tag archives
- Author archives
- Search results pages
- Date archives

### Display controls

- **Before or after** the post loop — per section
- **Active / Inactive toggle** — publish a section without displaying it yet
- **Visibility by login state** — show to everyone, logged-in users only, or logged-out visitors only

### Admin experience

- At-a-glance list table with Active, Location, Position, and Visibility columns
- One-click **Duplicate** action to clone any section as a draft
- **Preview link** in the meta box that jumps straight to the relevant archive page
- Display order controlled via the standard **Order** field (Page Attributes)

### Performance & security

- **Transient caching** — sections are cached per location, position, and login state; cache is busted automatically when a section is saved or the plugin is deactivated
- Admin CSS and JavaScript load **only on the Archive Sections edit screen** — zero frontend asset overhead
- All inputs validated against whitelists; category IDs cast with `absint()`; nonces verified with `sanitize_key()`
- Works with all standard caching plugins

### For developers

| Hook | Type | Description |
|------|------|-------------|
| `kcas_query_args` | Filter | Modify WP_Query args before sections are fetched |
| `kcas_section_html` | Filter | Modify the HTML of an individual section |
| `kcas_sections_html` | Filter | Modify the complete output wrapper HTML |
| `kcas_before_sections` | Action | Fires before the sections wrapper is output |
| `kcas_after_sections` | Action | Fires after the sections wrapper is output |

Sections are wrapped in `<section class="kcas-archive-section" id="kcas-section-{ID}">` and `<div class="kcas-archive-sections kcas-archive-sections--{before|after}">` for easy CSS targeting.

---

## Installation

### From the WordPress Plugin Directory

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **Kayce Custom Archive Sections**.
3. Click **Install Now**, then **Activate**.

### Manual Installation

1. Download the plugin ZIP file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**, then **Activate**.

### Getting Started

1. After activation, go to **Archive Sections → Add New** in the admin menu.
2. Write or design your section content using the block editor or Elementor.
3. In the **Archive Section Settings** meta box, configure:
   - **Location** — choose which archive page(s) to target. Selecting *Specific Categories* reveals a category picker.
   - **Position** — before or after the post loop.
   - **Active** — tick to enable the section immediately.
   - **Visibility** — show to everyone, logged-in users only, or logged-out visitors only.
4. Click **Publish**.
5. Visit the matching archive page — your section will appear automatically.

> **Tip:** Use the **Order** field (Page Attributes panel) to control display order when multiple sections share the same location and position. Lower numbers appear first.

---

## Frequently Asked Questions

**Does this work with any theme?**
Yes — including Full Site Editing (block) themes like Twenty Twenty-Five. The plugin automatically detects whether your active theme is a classic or block theme and uses the correct rendering pipeline for each.

**Can I use Elementor to design my sections?**
Yes. The Archive Section CPT has `show_in_rest` enabled, making it fully compatible with the block editor. Elementor also supports editing custom post types out of the box.

**Can I target a specific category page?**
Yes. Set the location to **Specific Categories**, then tick the categories you want in the category picker that appears. The section will only display on those category archives.

**Can I show different content to logged-in and logged-out users?**
Yes. Each section has a **Visibility** setting: *Everyone*, *Logged-in users only*, or *Logged-out visitors only*.

**How do I temporarily disable a section without deleting it?**
Uncheck the **Active** checkbox in the meta box and update the post.

**Does the plugin add any database tables?**
No. It uses WordPress's standard `wp_posts`, `wp_postmeta`, and `wp_options` tables only.

**What happens when I delete the plugin?**
The included `uninstall.php` permanently deletes all Archive Section posts and their meta data when the plugin is removed.

---

## Changelog

### 1.0.0
- Initial release
- Custom post type (`kcas_section`) for creating reusable archive sections
- Display sections before or after posts on the **blog index** and **category archive** pages
- Support for **specific category targeting** with a scrollable category picker
- Additional archive locations: **tag archives**, **author archives**, **search results**, **date archives**
- Per-section **Active / Inactive toggle**
- Per-section **login-state visibility** control (everyone / logged-in / logged-out)
- Admin list table columns: Active, Location, Position, Visibility
- **Duplicate / Clone** row action with nonce and capability checks
- **Preview link** in the meta box resolving the real archive URL per location type
- **Transient caching** with version-stamped keys and targeted per-location cache busting on save
- Admin CSS and JS enqueued only on the Archive Section edit screen
- Full compatibility with **classic themes** and **FSE / block themes** (Twenty Twenty-Five, etc.)
- Fully compatible with **Gutenberg** and **Elementor**
- Developer hooks: `kcas_query_args`, `kcas_section_html`, `kcas_sections_html`, `kcas_before_sections`, `kcas_after_sections`
- `uninstall.php` for clean removal

---

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).
