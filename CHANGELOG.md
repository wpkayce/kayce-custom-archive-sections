# Changelog

All notable changes to Kayce Custom Archive Sections are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.4.0] — 2026-05-22

### Added

**Shortcode — `[kcas_section id="X"]`**
- Render any published, active section anywhere in WordPress content: posts, pages, widgets, Classic Editor, Gutenberg custom HTML blocks, or any plugin that executes shortcodes.
- Accepts a single required attribute: `id` — the post ID of the section to render.
- All display controls are respected: login-state visibility, date scheduling, user role targeting, and device CSS classes are applied exactly as in automatic archive injection.
- Block context is set correctly: on singular pages, dynamic blocks (`core/post-title`, `core/post-featured-image`, etc.) resolve against the viewed post; on archive/other pages, post-specific blocks return empty rather than leaking section metadata.
- Output is never cached — the shortcode already runs inside the page's own cached HTML; caching section output separately would cause stale double-caching.
- The `kcas_section_html` filter fires on shortcode output (position = `'shortcode'`), so developer customisations apply consistently everywhere.

**Drag-drop reordering**
- A drag-handle column (☰) appears as the first column in the Archive Sections list table.
- Handles are revealed only once jQuery UI Sortable is initialised — invisible on screens where sorting is not active (e.g. when a column header sort is applied).
- Drag-drop is automatically disabled when the list is sorted by a column header (`?orderby=…`); reorder by dragging is only meaningful in the default `menu_order` view.
- Dropping a row saves the new order instantly via AJAX (`wp_ajax_kcas_reorder_sections`) — no page reload required. Each section's `menu_order` is updated to its 0-based visual position.
- Default list table sort order changed to `menu_order ASC, title ASC` (was WordPress default). This makes the drag-drop order immediately visible without any URL parameter.
- AJAX handler validates a dedicated nonce (`kcas_reorder`) and `edit_posts` capability; non-KCAS post IDs in the payload are silently skipped.

---

## [1.3.0] — 2026-05-22

### Added

**Display scheduling**
- New **Schedule** fields in the meta box: optional **From** and **To** date pickers (YYYY-MM-DD, HTML5 `<input type="date">`).
- Sections outside their date range are silently skipped on the frontend — no code changes needed in themes.
- Dates are compared against the site's configured WordPress timezone (`current_time('Y-m-d')`), so the admin's notion of "today" always matches the site.
- Both fields are optional; set only one for open-ended scheduling (e.g. "starts on date X, never expires").
- Stored as `_kcas_date_from` / `_kcas_date_to` post meta. Validated server-side with `date_create_from_format('Y-m-d')` before saving.

**Device visibility**
- New **Devices** section in the meta box: three toggle-style checkboxes — **Desktop** (≥ 1025 px), **Tablet** (768–1024 px), **Mobile** (≤ 767 px). All three checked by default (no restriction).
- Implemented entirely in CSS — deselected devices add `kcas-hide-desktop`, `kcas-hide-tablet`, or `kcas-hide-mobile` classes to the `<section>` element; `frontend.css` hides them via `@media` queries.
- No server-side user-agent detection — works with page caching, CDNs, and Vary-free setups.
- Stored as `_kcas_devices` (array of active device slugs).

**User role targeting**
- New **User roles** section in the meta box: scrollable checklist of all registered WordPress roles, sorted alphabetically.
- Leave all unchecked for no restriction (existing behaviour preserved). Tick one or more roles to restrict visibility to those roles only.
- Combines with the existing Visibility setting — a section set to "Logged in" + "Administrator" only shows to logged-in administrators.
- The roles row is automatically hidden in the meta box when Visibility is set to "Logged-out visitors only" (role checks are irrelevant for logged-out users).
- Stored as `_kcas_roles` (array of role slugs). Validated against `wp_roles()->roles` before saving.

**Admin UX (v1.3.0)**
- Scheduling, Devices, and User roles sections all appear below the Visibility setting, separated by dividers.
- Hint text under each new field explains the default/no-restriction behaviour.
- Duplicate action copies all four new meta keys to the cloned post.

---

## [1.2.0] — 2026-05-22

### Added

**New locations**
- **Front page** — targets the static front page (`is_front_page() && !is_home()`). Distinct from the blog index so each can have independent sections.
- **All pages** — targets every static WordPress page (`is_page()`).
- **Specific pages** — targets individual pages selected from a searchable page picker in the meta box. Saved as `_kcas_pages` (array of page IDs).
- **CPT archives** — targets the archive listing page of a specific custom post type. A post-type dropdown appears in the meta box when this location is chosen. Saved as `_kcas_cpt` (CPT slug string).
- **CPT singles** — targets individual posts of a specific custom post type. Uses the same post-type dropdown as CPT archives.

**Admin UI**
- Location grid reorganised into four labelled groups: **General** (Disabled, Blog index, Front page), **Archives** (All categories, Specific categories, Tags, Authors, Search, Date), **Singular** (Single posts, All pages, Specific pages), **Custom Post Types** (CPT archives, CPT singles — only shown when public non-built-in CPTs are registered).
- **Specific Pages picker** — scrollable, searchable checklist (`kcas_pages[]`); shown/hidden via JS when "Specific pages" is selected; mirrors the existing category picker UI.
- **Post type dropdown** (`kcas_cpt`) — shown/hidden via JS when "CPT archives" or "CPT singles" is selected; lists all public non-built-in CPTs with their singular label and slug.
- List table Location column now shows labels and hints for all five new locations (specific page titles, CPT singular name).
- Preview button resolves a relevant URL for every new location type.
- Duplicate action copies `_kcas_pages` and `_kcas_cpt` meta to the cloned post.

**Cache / uninstall**
- Five new location slugs added to `KCAS_Cache::LOCATIONS`, version-option busting, and `uninstall.php` cleanup.

---

## [1.1.1] — 2026-05-22

### Fixed

- **FSE / block themes — single post sections not appearing**: sections with the `single_post` location were silently skipped on FSE themes because the `render_block` filter only targeted `core/query` (archive templates). Extended to also target `core/post-content` so sections inject correctly on single post and page templates.
- **FSE / block themes — "After Header" on singular pages**: position was injecting before `core/post-content`, which landed the section between the featured image and the post body. Fixed by targeting the header `core/template-part` block instead — section now appears between the site header and the post title/featured image.
- **FSE / block themes — "Before Posts" on singular pages**: now also injects at the header template part level (same as After Header), so it appears before the title and featured image rather than mid-post.
- **All themes — section design breaking on every other page load**: WordPress generates dynamic layout class names (e.g. `wp-container-*-is-layout-N`) whose counter increments globally across blocks on the page. Caching the rendered HTML meant the class names in cache never matched the CSS output to `wp_footer` on the next request. Fixed by caching only the raw section data (post IDs + block markup) and always rendering blocks fresh per request, so class names and CSS are always in sync.
- **All themes — post content disappearing after sections rendered**: missing `wp_reset_postdata()` after the section rendering loop left `$GLOBALS['post']` pointing at the last `kcas_section` post; the theme then read the wrong post for its own output. Reset call restored.
- **Theme switch — stale section output**: switching the active theme left transient-cached section HTML rendered under the old theme's block styles. Cache is now busted automatically on `switch_theme`.
- **Classic themes with sidebar (e.g. ColorMag) — "After Header" inside column**: the JS repositioner ran a single pass, moving the section from inside the posts loop to `#cm-primary` but stopping there — still inside the column layout. Repositioner now loops until the section stops moving, correctly lifting it outside the columns row to full-width.

---

## [1.1.0] — 2026-05-22

### Added

**Positions**
- New **After Header** (`before_content`) position — injects section between the site header and page content area, outside the posts grid
- Theme-hook cascade for classic themes: Astra (`astra_header_after`), Genesis (`genesis_after_header`), OceanWP (`ocean_after_header`), Kadence (`kadence_after_header`), Neve (`neve_after_header_wrapper`), Storefront (`storefront_after_header`), Hestia (`hestia_after_header`), ColorMag (`colormag_before_primary_content`), Blocksy (`blocksy:header:after`), Hello Elementor (`hello_elementor_after_header`), Divi (`divi_after_header`), Enfold (`enfold_after_header`)
- `assets/js/frontend.js` — JavaScript safety net that auto-repositions `before_content` sections on unknown classic themes by detecting `article` siblings and moving the section before the posts container

**Admin features**
- Quick Edit — update Active, Position, and Visibility directly from the list table
- Bulk actions — Activate / Deactivate multiple sections at once
- Sortable columns — Active, Location, Position, Visibility column headers are all sortable
- Settings page (Archive Sections → Settings) — configure cache expiry 0–168 hours; 0 disables caching
- Activation welcome notice with a direct link to create the first section
- Friendly empty-state card in the list table when no sections exist yet

**Locations**
- Single post support — sections can target individual post pages (`is_single()`)

**i18n / developer**
- `languages/kayce-custom-archive-sections.pot` — POT translation template
- Full CPT label set: `archives`, `attributes`, `items_list`, `items_list_navigation`, `filter_items_list`, etc.
- `includes/class-kcas-settings.php` — new Settings class with `KCAS_Settings::get_cache_expiry_seconds()` helper
- `assets/css/frontend.css` — minimal, non-opinionated frontend spacing styles

### Changed

**Admin UI (meta box redesign)**
- Active field: flat checkbox → CSS toggle switch with on/off label and hint text
- Location field: radio list → two-column Dashicon card grid (9 options including Disabled)
- Position field: two inline radios → vertical segmented button group; now includes After Header as third option
- Visibility field: inline radios → vertical segmented button group (one option per row)
- Category picker: added live search input with leading search icon; list items now show post-count badges; checked items highlighted
- Preview button: full-width styled button with external-link Dashicon
- Position section hidden entirely when Location is set to Disabled

**Caching**
- `KCAS_Cache::set()` now reads expiry from `KCAS_Settings::get_cache_expiry_seconds()` if available, falling back to the hardcoded 12-hour constant
- Cache key for `single_post` location now includes the queried post ID — each post gets its own cached copy

**List table**
- Position column now shows "After header" label for `before_content` value
- Quick Edit position `<select>` includes the After Header option

### Fixed

- **Dynamic block context on singular pages**: `core/post-title`, `core/post-excerpt`, and other post-specific dynamic blocks inside sections now resolve against the viewed post/page, not the `kcas_section` post itself. Implemented via `$post` global swap + scoped `render_block_context` filter
- **Dynamic block context on archive pages**: post-specific blocks return empty string rather than showing section data; archive blocks (`core/archive-title`, `core/term-description`) are unaffected
- **FSE/block themes**: fixed duplicate section injection on archive pages that render multiple `core/query` blocks with `inherit: true` — sections now injected at most once per page load
- **Cache bust coverage**: `single_post` location was missing from `KCAS_Cache::LOCATIONS` and `uninstall.php`; busting the cache on deactivation or uninstall now correctly invalidates single-post transients
- **Frontend assets**: `frontend.css` (and now `frontend.js`) enqueued only on archive and singular pages — zero overhead on all other pages

---

## [1.0.0] — 2025-11-01

### Added

- Custom post type `kcas_section` for creating reusable archive sections
- Display sections before or after posts on: blog index, all category archives, specific category archives (with category picker), tag archives, author archives, search results, date archives
- Per-section Active / Inactive toggle
- Per-section login-state visibility: everyone / logged-in only / logged-out only
- Admin list table columns: Active, Location, Position, Visibility
- Duplicate / Clone row action with nonce and capability checks
- Preview link in the meta box — resolves the real archive URL per location type
- Transient caching with version-stamped, login-state-aware keys; targeted per-location cache busting on save
- Admin CSS and JS enqueued only on the Archive Section edit screen
- Full compatibility with classic PHP themes (`loop_start` / `loop_end` pipeline) and FSE / block themes (`render_block` filter pipeline)
- Gutenberg and Elementor compatible
- Developer filters and actions: `kcas_query_args`, `kcas_section_html`, `kcas_sections_html`, `kcas_before_sections`, `kcas_after_sections`
- `uninstall.php` — clean removal of all plugin data

---

[1.1.1]: https://github.com/wpkayce/kayce-custom-archive-sections/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/wpkayce/kayce-custom-archive-sections/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/wpkayce/kayce-custom-archive-sections/releases/tag/v1.0.0
