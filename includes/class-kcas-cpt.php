<?php
/**
 * Custom Post Type registration for Kayce Custom Archive Sections.
 *
 * @package Kayce_Custom_Archive_Sections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KCAS_CPT
 */
class KCAS_CPT {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * Constructor.
	 *
	 * @param string $post_type The post type slug.
	 */
	public function __construct( $post_type ) {
		$this->post_type = $post_type;
		add_action( 'init', array( $this, 'register_archive_section_cpt' ) );
	}

	/**
	 * Register the "Archive Section" custom post type.
	 *
	 * Each post in this CPT represents a reusable section of content
	 * (built with Gutenberg or Elementor) that can be injected into
	 * archive pages automatically.
	 */
	public function register_archive_section_cpt() {
		$labels = array(
			'name'               => _x( 'Archive Sections', 'post type general name', 'kayce-custom-archive-sections' ),
			'singular_name'      => _x( 'Archive Section', 'post type singular name', 'kayce-custom-archive-sections' ),
			'menu_name'          => _x( 'Archive Sections', 'admin menu', 'kayce-custom-archive-sections' ),
			'name_admin_bar'     => _x( 'Archive Section', 'add new on admin bar', 'kayce-custom-archive-sections' ),
			'add_new'            => __( 'Add New', 'kayce-custom-archive-sections' ),
			'add_new_item'       => __( 'Add New Archive Section', 'kayce-custom-archive-sections' ),
			'edit_item'          => __( 'Edit Archive Section', 'kayce-custom-archive-sections' ),
			'new_item'           => __( 'New Archive Section', 'kayce-custom-archive-sections' ),
			'view_item'          => __( 'View Archive Section', 'kayce-custom-archive-sections' ),
			'search_items'       => __( 'Search Archive Sections', 'kayce-custom-archive-sections' ),
			'not_found'          => __( 'No archive sections found.', 'kayce-custom-archive-sections' ),
			'not_found_in_trash' => __( 'No archive sections found in Trash.', 'kayce-custom-archive-sections' ),
			'all_items'          => __( 'All Archive Sections', 'kayce-custom-archive-sections' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Reusable content sections displayed on archive pages.', 'kayce-custom-archive-sections' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-layout',
			'capability_type'    => 'post',
			'supports'           => array( 'title', 'editor', 'page-attributes' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'show_in_rest'       => true,
		);

		register_post_type( $this->post_type, $args );
	}
}
