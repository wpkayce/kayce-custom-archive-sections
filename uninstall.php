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

global $wpdb;

// Delete all kcas_section posts and their associated meta.
$post_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		'kcas_section'
	)
);

if ( ! empty( $post_ids ) ) {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true ); // true = force delete, skip trash.
	}
}
