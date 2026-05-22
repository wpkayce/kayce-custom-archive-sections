<?php

/**
 * Frontend output for Kayce Custom Archive Sections.
 *
 * Supports two rendering pipelines:
 *  1. Classic themes  — hooks into loop_start / loop_end after get_header fires.
 *  2. Block/FSE themes — filters the rendered core/query block that inherits
 *     the main archive query (e.g. Twenty Twenty-Five).
 *
 * Additional features:
 *  - All archive types: blog index, all categories, specific categories,
 *    tag archives, author archives, search results, date archives.
 *  - Active toggle: only active sections are shown.
 *  - Login-state visibility: per-section control over who sees it.
 *  - Transient caching: cache hits skip the WP_Query entirely.
 *  - Developer hooks: filters and actions at every key output point.
 *
 * @package Kayce_Custom_Archive_Sections
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class KCAS_Frontend
 */
class KCAS_Frontend
{

	/** @var string */
	protected $post_type;
	/** @var string */
	protected $meta_location;
	/** @var string */
	protected $meta_position;
	/** @var string */
	protected $meta_active;
	/** @var string */
	protected $meta_visibility;
	/** @var string */
	protected $meta_categories;

	/**
	 * Constructor.
	 *
	 * @param string $post_type
	 * @param string $meta_location
	 * @param string $meta_position
	 * @param string $meta_active
	 * @param string $meta_visibility
	 * @param string $meta_categories
	 */
	public function __construct($post_type, $meta_location, $meta_position, $meta_active, $meta_visibility, $meta_categories)
	{
		$this->post_type       = $post_type;
		$this->meta_location   = $meta_location;
		$this->meta_position   = $meta_position;
		$this->meta_active     = $meta_active;
		$this->meta_visibility = $meta_visibility;
		$this->meta_categories = $meta_categories;

		if (is_admin()) {
			return;
		}

		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

		if (wp_is_block_theme()) {
			add_filter('render_block', array($this, 'inject_around_query_block'), 10, 2);
		} else {
			add_action('get_header', array($this, 'register_loop_hooks'));
			add_action('get_header', array($this, 'register_before_content_hooks'));
		}
	}

	/**
	 * Enqueue the minimal frontend stylesheet.
	 * Only loaded on archive/singular pages where sections can appear.
	 */
	public function enqueue_frontend_assets()
	{
		if (
			! is_home() &&
			! is_front_page() &&
			! is_category() &&
			! is_tag() &&
			! is_author() &&
			! is_search() &&
			! is_date() &&
			! is_post_type_archive() &&
			! is_singular()
		) {
			return;
		}

		wp_enqueue_style(
			'kcas-frontend',
			KCAS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			KCAS_PLUGIN_VERSION
		);

		// Repositioning script for 'before_content' sections on themes that
		// don't provide an 'after header' action hook. Runs before first paint
		// so the section moves before the user sees the wrong position.
		wp_enqueue_script(
			'kcas-frontend',
			KCAS_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			KCAS_PLUGIN_VERSION,
			array( 'strategy' => 'defer' )
		);
	}

	// =========================================================================
	// Classic theme pipeline
	// =========================================================================

	/**
	 * Register loop hooks after get_header so sections can never appear
	 * before the page header (classic themes only).
	 */
	public function register_loop_hooks()
	{
		add_action('loop_start', array($this, 'maybe_output_sections_before'));
		add_action('loop_end',   array($this, 'maybe_output_sections_after'));
	}

	/**
	 * Register the "after header / before content area" injection point for
	 * classic themes (the `before_content` position value).
	 *
	 * There is no universal WordPress hook that fires between </header> and the
	 * opening content wrapper. Major themes each expose their own named action
	 * for this spot. We try a cascade of known hooks so the section renders in
	 * the structurally correct place on popular themes.
	 *
	 * If none of the theme-specific hooks fire (unknown theme), we fall back to
	 * `loop_start` at priority 1 — placing the section at the very top of the
	 * post loop, which is the closest universally available equivalent.
	 *
	 * Known hook map:
	 *   astra_header_after          — Astra
	 *   genesis_after_header        — Genesis Framework
	 *   ocean_after_header          — OceanWP
	 *   kadence_after_header        — Kadence
	 *   neve_after_header_wrapper   — Neve
	 *   storefront_after_header     — WooCommerce Storefront
	 *   hestia_after_header         — Hestia
	 *   bimber_site_after_header    — Bimber / Newspaper-family
	 */
	public function register_before_content_hooks()
	{
		// One-shot flag: prevents double output if two theme hooks both fire.
		$fired = false;
		$self  = $this;

		$output = function () use (&$fired, $self) {
			if ($fired || is_admin()) {
				return;
			}
			$fired = true;
			foreach ($self->resolve_locations() as $loc) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $self->get_sections_html($loc['location'], 'before_content', $loc['extra']);
			}
		};

		// ── Theme-specific "after header" hooks ───────────────────────────────
		$theme_hooks = array(
			'astra_header_after',              // Astra
			'genesis_after_header',            // Genesis Framework
			'ocean_after_header',              // OceanWP
			'kadence_after_header',            // Kadence
			'neve_after_header_wrapper',       // Neve
			'storefront_after_header',         // WooCommerce Storefront
			'hestia_after_header',             // Hestia
			'bimber_site_after_header',        // Bimber
			'colormag_before_primary_content',  // ColorMag (ThemeGrill) — inside #cm-primary; JS lifts to full-width
			'tg_before_primary',                // ThemeGrill family fallback
			'blocksy:header:after',            // Blocksy
			'hello_elementor_after_header',    // Hello Elementor
			'divi_after_header',               // Divi
			'enfold_after_header',             // Enfold
		);

		foreach ($theme_hooks as $hook) {
			add_action($hook, $output, 10);
		}

		// ── Universal fallback: top of main loop ──────────────────────────────
		// Fires only if none of the above theme hooks triggered.
		add_action(
			'loop_start',
			function ($query) use (&$fired, $output) {
				if (! $fired && $query->is_main_query() && ! is_admin()) {
					$output();
				}
			},
			1   // Priority 1 — runs before our regular 'before' output at default priority.
		);
	}

	/**
	 * Output sections before the post loop (classic themes).
	 *
	 * @param WP_Query $query
	 */
	public function maybe_output_sections_before($query)
	{
		if (! $query->is_main_query() || is_admin()) {
			return;
		}

		foreach ($this->resolve_locations() as $loc) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_sections_html($loc['location'], 'before', $loc['extra']);
		}
	}

	/**
	 * Output sections after the post loop (classic themes).
	 *
	 * @param WP_Query $query
	 */
	public function maybe_output_sections_after($query)
	{
		if (! $query->is_main_query() || is_admin()) {
			return;
		}

		foreach ($this->resolve_locations() as $loc) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_sections_html($loc['location'], 'after', $loc['extra']);
		}
	}

	// =========================================================================
	// Block / FSE theme pipeline
	// =========================================================================

	/**
	 * Wrap FSE blocks with archive sections (block/FSE themes).
	 *
	 * Three injection points are handled:
	 *
	 *  1. core/query (inherit:true) — archive pages (blog, category, tag, etc.)
	 *     Injects before_content + before BEFORE the block, after AFTER the block.
	 *     Works because the Query block is the first content element after the
	 *     header template part on archive templates.
	 *
	 *  2. core/template-part (slug/area = "header") on singular pages
	 *     Injects before_content AFTER the header template part — this is the
	 *     true "after header, before page content" position. Runs before any
	 *     post-specific blocks (title, featured image, etc.) are rendered.
	 *
	 *  3. core/post-content on singular pages
	 *     Injects before BEFORE the block, after AFTER the block — so sections
	 *     appear immediately before/after the post body text, not the featured
	 *     image or title blocks that precede it in the template.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public function inject_around_query_block($block_content, $block)
	{
		$block_name = isset($block['blockName']) ? $block['blockName'] : '';

		// ── Path 1: archive pages via core/query (inherit:true) ──────────────────
		if ('core/query' === $block_name) {
			// Only wrap Query blocks that inherit the main archive query.
			if (empty($block['attrs']['query']['inherit'])) {
				return $block_content;
			}

			// Prevent injecting sections more than once per page load — some FSE themes
			// place multiple core/query blocks with inherit:true on the same archive page.
			static $archive_injected = false;
			if ($archive_injected) {
				return $block_content;
			}

			$locations = $this->resolve_locations();
			if (empty($locations)) {
				return $block_content;
			}

			$before = '';
			$after  = '';

			foreach ($locations as $loc) {
				// 'before_content' on FSE archive templates: the Query block is the
				// first content element after the header template part, so injecting
				// before it is structurally equivalent to "after header".
				$before .= $this->get_sections_html($loc['location'], 'before_content', $loc['extra']);
				$before .= $this->get_sections_html($loc['location'], 'before', $loc['extra']);
				$after  .= $this->get_sections_html($loc['location'], 'after',  $loc['extra']);
			}

			if (! $before && ! $after) {
				return $block_content;
			}

			$archive_injected = true;
			return $before . $block_content . $after;
		}

		// ── Path 2: "after header" + "before posts" on singular FSE pages ──────────
		// On FSE single-post templates the featured image and post title/meta blocks
		// sit between the header template part and core/post-content. Injecting
		// before core/post-content therefore lands between the featured image and
		// the post body — not what "before posts" means to a user.
		//
		// Instead we inject BOTH before_content and before sections right after
		// the header template part (before the title, featured image, everything).
		// Only the 'after' position stays anchored to core/post-content.
		if ('core/template-part' === $block_name && is_singular()) {
			$slug = isset($block['attrs']['slug']) ? $block['attrs']['slug'] : '';
			$area = isset($block['attrs']['area']) ? $block['attrs']['area'] : '';

			if ('header' !== $slug && 'header' !== $area) {
				return $block_content;
			}

			static $header_injected = false;
			if ($header_injected) {
				return $block_content;
			}

			$locations = $this->resolve_locations();
			if (empty($locations)) {
				return $block_content;
			}

			$after_header = '';
			foreach ($locations as $loc) {
				// Both "After header" and "Before posts" inject here on singular pages —
				// right after the site header, before the post title and featured image.
				$after_header .= $this->get_sections_html($loc['location'], 'before_content', $loc['extra']);
				$after_header .= $this->get_sections_html($loc['location'], 'before', $loc['extra']);
			}

			if (! $after_header) {
				return $block_content;
			}

			$header_injected = true;
			return $block_content . $after_header;
		}

		// ── Path 3: "after posts" on singular FSE pages ───────────────────────────
		// Only the 'after' position hooks onto core/post-content — it appears right
		// below the post body text, above the comments/navigation blocks.
		if ('core/post-content' === $block_name && is_singular()) {
			static $singular_injected = false;
			if ($singular_injected) {
				return $block_content;
			}

			$locations = $this->resolve_locations();
			if (empty($locations)) {
				return $block_content;
			}

			$after = '';
			foreach ($locations as $loc) {
				$after .= $this->get_sections_html($loc['location'], 'after', $loc['extra']);
			}

			if (! $after) {
				return $block_content;
			}

			$singular_injected = true;
			return $block_content . $after;
		}

		return $block_content;
	}

	// =========================================================================
	// Location resolution
	// =========================================================================

	/**
	 * Determine which location slugs apply to the current page.
	 *
	 * Returns an array of arrays, each with:
	 *  - 'location' string  The location slug to query.
	 *  - 'extra'    string  Extra context for the cache key (e.g. category ID).
	 *
	 * Checks are ordered from most-specific to least-specific so that a page
	 * that satisfies multiple conditions (e.g. is_front_page() + is_home()) is
	 * matched by the right slug first.
	 *
	 * @return array
	 */
	private function resolve_locations()
	{
		// ── Static front page ─────────────────────────────────────────────────
		// Must come before is_page() — the front page satisfies both conditions.
		if (is_front_page() && ! is_home()) {
			$page_id = (int) get_queried_object_id();
			return array(
				array('location' => 'front_page',      'extra' => ''),
				array('location' => 'all_pages',        'extra' => ''),
				array('location' => 'specific_pages',   'extra' => (string) $page_id),
			);
		}

		// ── Blog index ────────────────────────────────────────────────────────
		if (is_home()) {
			return array(array('location' => 'blog_index', 'extra' => ''));
		}

		// ── Category archives ─────────────────────────────────────────────────
		if (is_category()) {
			$cat_id = (int) get_queried_object_id();
			return array(
				array('location' => 'category_archives',   'extra' => ''),
				array('location' => 'specific_categories', 'extra' => (string) $cat_id),
			);
		}

		// ── Tag archives ──────────────────────────────────────────────────────
		if (is_tag()) {
			return array(array('location' => 'tag_archives', 'extra' => ''));
		}

		// ── Author archives ───────────────────────────────────────────────────
		if (is_author()) {
			return array(array('location' => 'author_archives', 'extra' => ''));
		}

		// ── Search results ────────────────────────────────────────────────────
		if (is_search()) {
			return array(array('location' => 'search_results', 'extra' => ''));
		}

		// ── Date archives ─────────────────────────────────────────────────────
		if (is_date()) {
			return array(array('location' => 'date_archives', 'extra' => ''));
		}

		// ── CPT archives ──────────────────────────────────────────────────────
		// is_post_type_archive() must come before is_singular() since CPT
		// archive pages are not singular.
		if (is_post_type_archive()) {
			$cpt = get_query_var('post_type');
			// get_query_var may return an array for multi-type queries.
			if (is_array($cpt)) {
				$cpt = reset($cpt);
			}
			$cpt = sanitize_key($cpt);
			return array(
				array('location' => 'cpt_archives', 'extra' => $cpt),
			);
		}

		// ── Static pages ──────────────────────────────────────────────────────
		// Must come before is_singular() to match page-specific slugs.
		if (is_page()) {
			$page_id = (int) get_queried_object_id();
			return array(
				array('location' => 'all_pages',      'extra' => ''),
				array('location' => 'specific_pages', 'extra' => (string) $page_id),
			);
		}

		// ── Standard single posts ─────────────────────────────────────────────
		if (is_single()) {
			// Include the post ID in the cache key so each post gets its own
			// cached copy — required because dynamic blocks (e.g. core/post-title)
			// resolve to the specific post being viewed.
			return array(array('location' => 'single_post', 'extra' => (string) get_queried_object_id()));
		}

		// ── CPT single posts ──────────────────────────────────────────────────
		// Any singular that is not a standard post, page, or attachment.
		if (is_singular()) {
			$queried = get_queried_object();
			if ($queried instanceof WP_Post) {
				$cpt = sanitize_key($queried->post_type);
				if (
					$cpt &&
					! in_array($cpt, array('post', 'page', 'attachment', $this->post_type), true)
				) {
					return array(
						array('location' => 'cpt_singles', 'extra' => $cpt),
					);
				}
			}
		}

		return array();
	}

	// =========================================================================
	// Core HTML builder
	// =========================================================================

	/**
	 * Build and return the HTML for all sections matching a location + position.
	 *
	 * Caching strategy — two-phase:
	 *
	 *   Phase 1 (cached): WP_Query results — section post IDs and raw block
	 *   markup. This is stored in a transient to eliminate repeated DB queries.
	 *
	 *   Phase 2 (always fresh): block rendering — apply_filters('the_content')
	 *   is called on every request, never cached. This is intentional: WordPress
	 *   generates dynamic layout class names (e.g. wp-container-*-is-layout-N)
	 *   whose counter increments across blocks on the page. The corresponding CSS
	 *   is output to wp_footer in the same request. If rendered HTML is cached and
	 *   served on a later request, the class names no longer match the CSS — the
	 *   layout breaks. Rendering fresh each time keeps class names and CSS in sync.
	 *
	 * Developer hooks are applied at every key stage.
	 *
	 * @param string $location One of the supported location slugs.
	 * @param string $position 'before_content', 'before', or 'after'.
	 * @param string $extra    Extra cache-key context (e.g. category ID).
	 * @return string HTML string, or empty string if nothing to show.
	 */
	public function get_sections_html($location, $position, $extra = '')
	{
		$allowed_locations = array(
			'blog_index',
			'category_archives',
			'specific_categories',
			'single_post',
			'tag_archives',
			'author_archives',
			'search_results',
			'date_archives',
			// v1.2.0
			'front_page',
			'all_pages',
			'specific_pages',
			'cpt_archives',
			'cpt_singles',
		);
		$allowed_positions = array('before_content', 'before', 'after');

		if (
			! in_array($location, $allowed_locations, true) ||
			! in_array($position, $allowed_positions, true)
		) {
			return '';
		}

		// ── Phase 1: query cache ──────────────────────────────────────────────
		// Returns an array of ['id' => int, 'content' => string], or false on miss.
		$sections_data = KCAS_Cache::get($location, $position, $extra);

		if (false === $sections_data) {
			// ── Developer hook: allow query arg modification (feature 5) ──────
			$query_args = apply_filters(
				'kcas_query_args',
				$this->build_query_args($location, $position),
				$location,
				$position
			);

			$sections_query = new WP_Query($query_args);
			$sections_data  = array();

			if ($sections_query->have_posts()) {
				while ($sections_query->have_posts()) {
					$sections_query->the_post();
					$sections_data[] = array(
						'id'      => get_the_ID(),
						'content' => get_the_content(),
					);
				}
				wp_reset_postdata();
			}

			// Cache the data array (empty array for no results is a valid cache hit).
			KCAS_Cache::set($location, $position, $sections_data, $extra);
		}

		if (empty($sections_data)) {
			return '';
		}

		// ── Phase 2: render fresh ─────────────────────────────────────────────
		// Blocks are rendered on every request so WordPress's dynamic layout
		// class names (and their matching inline CSS) are always in sync.

		// ── Developer action: fires before any sections HTML (feature 5) ─────
		ob_start();
		do_action('kcas_before_sections', $location, $position);
		$before_action = ob_get_clean();

		$inner = '';

		foreach ($sections_data as $section_data) {
			$post_id      = (int) $section_data['id'];
			$section_post = get_post($post_id);

			if (! $section_post || 'publish' !== $section_post->post_status) {
				continue;
			}

			// ── Visibility check (feature 1e) ─────────────────────────────────
			if (! $this->passes_visibility_check($post_id)) {
				continue;
			}

			$content = $section_data['content'];

			// ── Dynamic block context ──────────────────────────────────────────
			// Dynamic blocks read post data from either:
			//   (a) the global $post             — classic / fallback path
			//   (b) $block->context['postId']    — block context path (Gutenberg)
			//
			// While iterating our sections data, $post is undefined / stale.
			//
			// Singular pages (single posts, pages):
			//   Swap $post to the viewed post AND inject its ID into block context.
			//
			// Archive pages (category, tag, author, search, date, blog index):
			//   Clear postId/postType from block context so post-specific blocks
			//   return '' rather than showing unexpected data.
			$page_context_post    = null;
			$block_context_filter = null;

			if (is_singular()) {
				$queried = get_queried_object();
				if ($queried instanceof WP_Post) {
					$page_context_post = $queried;
					$GLOBALS['post']   = $page_context_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional; restored below
					setup_postdata($page_context_post);

					$ctx_id   = $page_context_post->ID;
					$ctx_type = $page_context_post->post_type;

					$block_context_filter = static function ($context) use ($ctx_id, $ctx_type) {
						$context['postId']   = $ctx_id;
						$context['postType'] = $ctx_type;
						return $context;
					};
				}
			} else {
				$block_context_filter = static function ($context) {
					unset($context['postId'], $context['postType']);
					return $context;
				};
			}

			if ($block_context_filter) {
				add_filter('render_block_context', $block_context_filter, 5); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}

			$content = apply_filters('the_content', $content); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$content = str_replace(']]>', ']]&gt;', $content);

			if ($block_context_filter) {
				remove_filter('render_block_context', $block_context_filter, 5); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}

			if ($page_context_post) {
				$GLOBALS['post'] = $section_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring context
				setup_postdata($section_post);
			}

			$section_html  = '<section class="kcas-archive-section" id="kcas-section-' . esc_attr($post_id) . '">';
			$section_html .= $content;
			$section_html .= '</section>';

			// ── Developer filter: per-section HTML (feature 5) ────────────────
			$section_html = apply_filters('kcas_section_html', $section_html, $post_id, $location, $position);

			$inner .= $section_html;
		}

		// Restore the global $post to the main query's current post.
		// setup_postdata() was called inside the loop; without this reset the
		// theme would read the wrong $post and the actual page content disappears.
		wp_reset_postdata();

		if ('' === $inner) {
			return '';
		}

		$wrapper  = '<div class="kcas-archive-sections kcas-archive-sections--' . esc_attr($position) . '">';
		$wrapper .= $inner;
		$wrapper .= '</div>';

		// ── Developer action: fires after sections HTML (feature 5) ──────────
		ob_start();
		do_action('kcas_after_sections', $location, $position);
		$after_action = ob_get_clean();

		$html = $before_action . $wrapper . $after_action;

		// ── Developer filter: full output (feature 5) ─────────────────────────
		$html = apply_filters('kcas_sections_html', $html, $location, $position);

		// Rendered HTML is intentionally NOT cached — see docblock above.
		return $html;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build the WP_Query args for fetching sections.
	 *
	 * For 'specific_categories', the query fetches ALL specific-category
	 * sections and relies on PHP filtering (passes_visibility_check + category
	 * matching) to narrow the result — avoids unreliable LIKE queries on
	 * serialised meta.
	 *
	 * @param string $location
	 * @param string $position
	 * @return array
	 */
	private function build_query_args($location, $position)
	{
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => $this->meta_location,
				'value'   => $location,
				'compare' => '=',
			),
			array(
				'key'     => $this->meta_position,
				'value'   => $position,
				'compare' => '=',
			),
			array(
				'key'     => $this->meta_active,
				'value'   => '1',
				'compare' => '=',
			),
		);

		return array(
			'post_type'              => $this->post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
	}

	/**
	 * Check whether the current visitor and page context match the section's
	 * settings. Returns false (hide) if any check fails, true (show) otherwise.
	 *
	 * Checks performed:
	 *  1. Login-state visibility (everyone / logged-in / logged-out).
	 *  2. Specific-item matching for picker-based locations:
	 *       specific_categories — current category in the saved cat IDs list.
	 *       specific_pages      — current page in the saved page IDs list.
	 *       cpt_archives        — current CPT matches the saved CPT slug.
	 *       cpt_singles         — current post type matches the saved CPT slug.
	 *
	 * @param int $post_id Section post ID.
	 * @return bool
	 */
	private function passes_visibility_check($post_id)
	{
		// ── 1. Login-state check ──────────────────────────────────────────────
		$visibility = get_post_meta($post_id, $this->meta_visibility, true);

		if ('logged_in' === $visibility && ! is_user_logged_in()) {
			return false;
		}

		if ('logged_out' === $visibility && is_user_logged_in()) {
			return false;
		}

		// ── 2. Specific-item checks ───────────────────────────────────────────
		$location = get_post_meta($post_id, $this->meta_location, true);

		// Specific categories — current category ID must be in the saved list.
		if ('specific_categories' === $location && is_category()) {
			$saved  = get_post_meta($post_id, $this->meta_categories, true);
			$cur_id = (int) get_queried_object_id();
			if (! is_array($saved) || ! in_array($cur_id, array_map('intval', $saved), true)) {
				return false;
			}
		}

		// Specific pages — current page ID must be in the saved list.
		if ('specific_pages' === $location && is_page()) {
			$saved  = get_post_meta($post_id, '_kcas_pages', true);
			$cur_id = (int) get_queried_object_id();
			if (! is_array($saved) || ! in_array($cur_id, array_map('intval', $saved), true)) {
				return false;
			}
		}

		// CPT archives — saved CPT slug must match the current archive's post type.
		if ('cpt_archives' === $location && is_post_type_archive()) {
			$saved_cpt   = sanitize_key((string) get_post_meta($post_id, '_kcas_cpt', true));
			$current_cpt = get_query_var('post_type');
			if (is_array($current_cpt)) {
				$current_cpt = reset($current_cpt);
			}
			$current_cpt = sanitize_key($current_cpt);
			if (! $saved_cpt || $saved_cpt !== $current_cpt) {
				return false;
			}
		}

		// CPT singles — saved CPT slug must match the current singular's post type.
		if ('cpt_singles' === $location && is_singular()) {
			$saved_cpt = sanitize_key((string) get_post_meta($post_id, '_kcas_cpt', true));
			$queried   = get_queried_object();
			if (! $queried instanceof WP_Post) {
				return false;
			}
			if (! $saved_cpt || $saved_cpt !== $queried->post_type) {
				return false;
			}
		}

		return true;
	}
}