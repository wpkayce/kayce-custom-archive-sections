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
		}
	}

	/**
	 * Enqueue the minimal frontend stylesheet.
	 * Only loaded on archive/singular pages where sections can appear.
	 */
	public function enqueue_frontend_assets()
	{
		if (! is_home() && ! is_category() && ! is_tag() && ! is_author() && ! is_search() && ! is_date() && ! is_single()) {
			return;
		}

		wp_enqueue_style(
			'kcas-frontend',
			KCAS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			KCAS_PLUGIN_VERSION
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
	 * Wrap the core/query block with archive sections (block/FSE themes).
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public function inject_around_query_block($block_content, $block)
	{
		if ('core/query' !== $block['blockName']) {
			return $block_content;
		}

		// Only wrap Query blocks that inherit the main archive query.
		if (empty($block['attrs']['query']['inherit'])) {
			return $block_content;
		}

		// Prevent injecting sections more than once per page load — some FSE themes
		// place multiple core/query blocks with inherit:true on the same archive page.
		static $injected = false;
		if ($injected) {
			return $block_content;
		}

		$locations = $this->resolve_locations();
		if (empty($locations)) {
			return $block_content;
		}

		$before = '';
		$after  = '';

		foreach ($locations as $loc) {
			$before .= $this->get_sections_html($loc['location'], 'before', $loc['extra']);
			$after  .= $this->get_sections_html($loc['location'], 'after',  $loc['extra']);
		}

		if (! $before && ! $after) {
			return $block_content;
		}

		$injected = true;
		return $before . $block_content . $after;
	}

	// =========================================================================
	// Location resolution
	// =========================================================================

	/**
	 * Determine which location slugs apply to the current archive page.
	 *
	 * Returns an array of arrays, each with:
	 *  - 'location' string  The location slug to query.
	 *  - 'extra'    string  Extra context for the cache key (e.g. category ID).
	 *
	 * @return array
	 */
	private function resolve_locations()
	{
		if (is_home()) {
			return array(array('location' => 'blog_index', 'extra' => ''));
		}

		if (is_category()) {
			$cat_id = (int) get_queried_object_id();
			return array(
				// Sections assigned to ALL category archives.
				array('location' => 'category_archives',   'extra' => ''),
				// Sections assigned to SPECIFIC categories (filtered by cat ID).
				array('location' => 'specific_categories', 'extra' => (string) $cat_id),
			);
		}

		if (is_single()) {
			// Include the post ID in the cache key so each post gets its own
			// cached copy — required because dynamic blocks (e.g. core/post-title)
			// resolve to the specific post being viewed.
			return array(array('location' => 'single_post', 'extra' => (string) get_queried_object_id()));
		}

		if (is_tag()) {
			return array(array('location' => 'tag_archives', 'extra' => ''));
		}

		if (is_author()) {
			return array(array('location' => 'author_archives', 'extra' => ''));
		}

		if (is_search()) {
			return array(array('location' => 'search_results', 'extra' => ''));
		}

		if (is_date()) {
			return array(array('location' => 'date_archives', 'extra' => ''));
		}

		return array();
	}

	// =========================================================================
	// Core HTML builder
	// =========================================================================

	/**
	 * Build and return the HTML for all sections matching a location + position.
	 *
	 * Checks the transient cache first. On a miss, runs the WP_Query,
	 * applies visibility filters, builds the HTML, and stores it in cache.
	 *
	 * Developer hooks are applied at every key stage (feature 5).
	 *
	 * @param string $location One of the supported location slugs.
	 * @param string $position 'before' or 'after'.
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
		);
		$allowed_positions = array('before', 'after');

		if (
			! in_array($location, $allowed_locations, true) ||
			! in_array($position, $allowed_positions, true)
		) {
			return '';
		}

		// ── Cache check (feature 3a) ──────────────────────────────────────────
		$cached = KCAS_Cache::get($location, $position, $extra);
		if (false !== $cached) {
			return $cached;
		}

		// ── Developer hook: allow query arg modification (feature 5) ─────────
		$query_args = apply_filters(
			'kcas_query_args',
			$this->build_query_args($location, $position),
			$location,
			$position
		);

		$sections_query = new WP_Query($query_args);

		if (! $sections_query->have_posts()) {
			// Cache the empty result too, to avoid repeated DB hits.
			KCAS_Cache::set($location, $position, '', $extra);
			return '';
		}

		// ── Developer action: fires before any sections HTML (feature 5) ─────
		ob_start();
		do_action('kcas_before_sections', $location, $position);
		$before_action = ob_get_clean();

		$inner = '';

		while ($sections_query->have_posts()) {
			$sections_query->the_post();
			$post_id      = get_the_ID();
			$section_post = get_post(); // The kcas_section post — saved for restoration.

			// ── Visibility check (feature 1e) ─────────────────────────────────
			if (! $this->passes_visibility_check($post_id)) {
				continue;
			}

			// ── Build section content ─────────────────────────────────────────
			$content = get_the_content();

			// Dynamic blocks (e.g. core/post-title, core/post-excerpt) read from
			// the global $post. While iterating sections, $post is the kcas_section
			// post — so those blocks would show the section's own title/data.
			//
			// For singular pages, temporarily swap $post to the viewed post so
			// dynamic blocks resolve against the correct page context. For archive
			// pages there is no single queried post, so we leave $post as-is and
			// let archive-aware blocks (core/archive-title, core/term-description)
			// continue to use get_queried_object() on their own.
			$page_context_post = null;
			if (is_singular()) {
				$queried = get_queried_object();
				if ($queried instanceof WP_Post) {
					$page_context_post       = $queried;
					$GLOBALS['post']         = $page_context_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional; restored below
					setup_postdata($page_context_post);
				}
			}

			$content = apply_filters('the_content', $content); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally applying the core WP content filter
			$content = str_replace(']]>', ']]&gt;', $content);

			// Restore the section post context for the next loop iteration.
			if ($page_context_post) {
				$GLOBALS['post'] = $section_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring previous value
				setup_postdata($section_post);
			}

			$section_html = '<section class="kcas-archive-section" id="kcas-section-' . esc_attr($post_id) . '">';
			$section_html .= $content;
			$section_html .= '</section>';

			// ── Developer filter: per-section HTML (feature 5) ────────────────
			$section_html = apply_filters('kcas_section_html', $section_html, $post_id, $location, $position);

			$inner .= $section_html;
		}

		wp_reset_postdata();

		if ('' === $inner) {
			KCAS_Cache::set($location, $position, '', $extra);
			return '';
		}

		$wrapper = '<div class="kcas-archive-sections kcas-archive-sections--' . esc_attr($position) . '">';
		$wrapper .= $inner;
		$wrapper .= '</div>';

		// ── Developer action: fires after sections HTML (feature 5) ──────────
		ob_start();
		do_action('kcas_after_sections', $location, $position);
		$after_action = ob_get_clean();

		$html = $before_action . $wrapper . $after_action;

		// ── Developer filter: full output (feature 5) ─────────────────────────
		$html = apply_filters('kcas_sections_html', $html, $location, $position);

		// ── Store in cache (feature 3a) ───────────────────────────────────────
		KCAS_Cache::set($location, $position, $html, $extra);

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
	 * Check whether the current visitor matches the section's visibility setting
	 * and — for specific_categories — whether the current category is selected.
	 *
	 * @param int $post_id Section post ID (current post in the loop).
	 * @return bool True if the section should be displayed.
	 */
	private function passes_visibility_check($post_id)
	{
		// ── Login-state check (feature 1e) ────────────────────────────────────
		$visibility = get_post_meta($post_id, $this->meta_visibility, true);

		if ('logged_in' === $visibility && ! is_user_logged_in()) {
			return false;
		}

		if ('logged_out' === $visibility && is_user_logged_in()) {
			return false;
		}

		// ── Specific-category check ───────────────────────────────────────────
		$location = get_post_meta($post_id, $this->meta_location, true);

		if ('specific_categories' === $location && is_category()) {
			$saved_cats  = get_post_meta($post_id, $this->meta_categories, true);
			$current_cat = (int) get_queried_object_id();

			if (! is_array($saved_cats) || ! in_array($current_cat, array_map('intval', $saved_cats), true)) {
				return false;
			}
		}

		return true;
	}
}