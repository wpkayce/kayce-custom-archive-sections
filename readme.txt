=== Kayce Custom Archive Sections ===
Contributors: wpkayce
Donate link: https://wpkayce.com
Tags: archive, content sections, blog layout, gutenberg, elementor
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add reusable content sections before or after posts on your blog index and category archives. Fully compatible with Gutenberg and Elementor.

== Description ==

Kayce Custom Archive Sections lets you add custom content areas to your WordPress archive pages — such as your blog index or category archives — without editing any theme files.

Create reusable "archive sections" that appear automatically before or after the main list of posts. Each section is a standard WordPress post, so you can build it with the block editor (Gutenberg) or design it with Elementor for full creative control.

**Key Features**

* Add custom content before or after posts on the blog index page and category archive pages.
* Fully compatible with Gutenberg (block editor) and Elementor page builder.
* Reusable content sections stored as a custom post type — create once, display everywhere.
* Clean and optimised query handling with `no_found_rows` and term cache disabled.
* Control display order via the built-in "Order" field (Page Attributes support).
* Fully compatible with caching plugins and standard themes.
* Simple, intuitive interface — no coding required.

**Perfect For**

* Adding a hero banner or intro section above your blog feed.
* Including promotional content or newsletter sign-ups below posts.
* Custom messaging for specific archive types (blog index vs. category pages).

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

1. After activation, go to **Archive Sections → Add New**.
2. Build your section using the block editor or Elementor.
3. In the **Archive Section Settings** sidebar panel, choose:
   * **Where** it should appear (blog index or category archives).
   * **Position** (before or after the posts list).
4. Publish the section.
5. Visit your blog index or a category page — your section will appear automatically.

**Tip:** Use the **Order** field (Page Attributes) to control the display order when you have multiple sections on the same page.

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes. The plugin hooks into WordPress's native `loop_start` and `loop_end` actions, which are fired by virtually all standard themes. If your theme uses a completely custom loop, you may need to add a template hook manually.

= Can I use Elementor to design my sections? =

Yes. The archive section CPT has `show_in_rest` enabled, which makes it compatible with the block editor. Elementor also supports editing custom post types, so you can build sections visually with Elementor.

= Can I show a section only on certain category pages? =

The current version shows sections on all category archives or the blog index. Per-category targeting is planned for a future release.

= How do I control the order of multiple sections? =

Open any archive section in the editor and set the **Order** value in the Page Attributes panel (usually in the right sidebar). Lower numbers appear first.

= Will my sections appear on the front end immediately? =

Yes — as soon as you publish an archive section with a location and position set, it will appear on the matching archive page.

= Does the plugin add any database tables? =

No. The plugin uses the standard WordPress `wp_posts` and `wp_postmeta` tables only.

== Screenshots ==

1. The Archive Sections list screen in the WordPress admin.
2. The "Archive Section Settings" meta box showing location and position options.
3. A custom section displayed before the post loop on a blog index page.

== Changelog ==

= 1.0.0 =
* Initial release.
* Custom post type (`kcas_section`) for creating reusable archive sections.
* Display sections before or after posts on the blog index and category archive pages.
* Meta box with location (blog index / category archives) and position (before / after) controls.
* Fully compatible with Gutenberg and Elementor.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
