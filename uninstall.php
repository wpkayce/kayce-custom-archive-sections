<?php
/**
 * Uninstall routine for Kayce Custom Archive Sections.
 *
 * Removes all CPT posts and their post meta when the plugin is deleted.
 * This file is executed automatically by WordPress on plugin deletion.
 *
 * @package Kayce_Custom_Archive_Sections
 */

// Safety check: only run when WordPress calls this file directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Fetch all kcas_section post IDs via the WP API (no direct DB query needed).
$kcas_post_ids = get_posts(
	array(
		'post_type'      => 'kcas_section',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $kcas_post_ids as $kcas_post_id ) {
	wp_delete_post( (int) $kcas_post_id, true ); // true = force delete, skip trash.
}

// Remove any leftover cache version options.
$kcas_locations = array(
	'blog_index',
	'category_archives',
	'specific_categories',
	'tag_archives',
	'author_archives',
	'search_results',
	'date_archives',
);

foreach ( $kcas_locations as $kcas_location ) {
	delete_option( 'kcas_cache_v_' . $kcas_location );
}
