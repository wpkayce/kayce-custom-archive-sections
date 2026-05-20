<?php

/**
 * Transient caching for Kayce Custom Archive Sections.
 *
 * Each location has its own version counter stored in wp_options.
 * Busting a location's cache increments its counter, immediately making
 * all transients for that location stale — without touching caches for
 * other locations (rate-aware / targeted busting, feature 4b).
 *
 * Login state is baked into the cache key so logged-in and logged-out
 * visitors always get the correct sections (feature 1e dependency).
 *
 * @package Kayce_Custom_Archive_Sections
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class KCAS_Cache
 */
class KCAS_Cache
{

	/** Transient lifetime in seconds (12 hours). */
	const EXPIRY = 43200;

	/** All known location slugs — used when busting everything. */
	const LOCATIONS = array(
		'blog_index',
		'category_archives',
		'specific_categories',
		'single_post',
		'tag_archives',
		'author_archives',
		'search_results',
		'date_archives',
	);

	/** @var string */
	protected $post_type;

	/** @var string */
	protected $meta_location;

	/** @var string */
	protected $meta_position;

	/**
	 * Constructor — register cache-busting hooks.
	 *
	 * @param string $post_type
	 * @param string $meta_location
	 * @param string $meta_position
	 */
	public function __construct($post_type, $meta_location, $meta_position)
	{
		$this->post_type     = $post_type;
		$this->meta_location = $meta_location;
		$this->meta_position = $meta_position;

		// Bust on any lifecycle event that could change what's displayed.
		add_action('save_post_' . $this->post_type, array($this, 'bust_for_section'), 20);
		add_action('delete_post',   array($this, 'bust_for_section'));
		add_action('trashed_post',  array($this, 'bust_for_section'));
		add_action('untrashed_post', array($this, 'bust_for_section'));
	}

	// -------------------------------------------------------------------------
	// Read / Write
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a cached HTML string, or false on a cache miss.
	 *
	 * @param string $location Cache location slug.
	 * @param string $position 'before' or 'after'.
	 * @param string $extra    Optional extra context (e.g. category ID).
	 * @return string|false
	 */
	public static function get($location, $position, $extra = '')
	{
		$cached = get_transient(self::build_key($location, $position, $extra));
		return (false !== $cached) ? $cached : false;
	}

	/**
	 * Store an HTML string in the cache.
	 *
	 * @param string $location
	 * @param string $position
	 * @param string $html
	 * @param string $extra
	 */
	public static function set($location, $position, $html, $extra = '')
	{
		set_transient(self::build_key($location, $position, $extra), $html, self::EXPIRY);
	}

	// -------------------------------------------------------------------------
	// Key Building
	// -------------------------------------------------------------------------

	/**
	 * Build a unique, version-stamped transient key.
	 *
	 * The key encodes:
	 *  - The location-specific version (incremented on bust).
	 *  - The location, position, and optional extra context.
	 *  - Whether the current visitor is logged in (for feature 1e).
	 *
	 * The raw string is hashed so the key always fits WordPress's 172-char limit.
	 *
	 * @param string $location
	 * @param string $position
	 * @param string $extra
	 * @return string
	 */
	public static function build_key($location, $position, $extra = '')
	{
		$version   = self::get_version($location);
		$logged_in = is_user_logged_in() ? '1' : '0';
		$raw       = implode('_', array_filter(array($version, $location, $position, $extra, $logged_in)));
		return 'kcas_' . md5($raw);
	}

	// -------------------------------------------------------------------------
	// Cache Busting
	// -------------------------------------------------------------------------

	/**
	 * Bust the cache for the location of the given section post.
	 *
	 * Only the location that changed is invalidated — other locations remain
	 * cached and undisturbed (targeted / rate-aware busting — feature 4b).
	 *
	 * @param int $post_id
	 */
	public function bust_for_section($post_id)
	{
		if (get_post_type($post_id) !== $this->post_type) {
			return;
		}

		$location = get_post_meta($post_id, $this->meta_location, true);

		if (empty($location)) {
			// Location unknown — bust everything to be safe.
			self::bust_all();
			return;
		}

		self::increment_version($location);
	}

	/**
	 * Bust every location's cache. Used on plugin deactivation.
	 */
	public static function bust_all()
	{
		foreach (self::LOCATIONS as $location) {
			self::increment_version($location);
		}
	}

	// -------------------------------------------------------------------------
	// Version Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the current version counter for a location.
	 *
	 * @param string $location
	 * @return int
	 */
	private static function get_version($location)
	{
		return (int) get_option('kcas_cache_v_' . sanitize_key($location), 0);
	}

	/**
	 * Increment the version counter for a location, immediately invalidating
	 * all existing transients for that location.
	 *
	 * @param string $location
	 */
	private static function increment_version($location)
	{
		$current = self::get_version($location);
		update_option('kcas_cache_v_' . sanitize_key($location), $current + 1, false);
	}
}
