<?php

if (! defined('ABSPATH')) {
    exit;
}

class KCAS_CPT
{

    protected $post_type;

    public function __construct($post_type)
    {
        $this->post_type = $post_type;

        add_action('init', [$this, 'register_archive_section_cpt']);
    }

    /**
     * Register the "Archive Section" custom post type.
     *
     * Each post in this CPT will be a reusable section of content
     * (built with Gutenberg or Elementor) that we can inject
     * into archive pages later.
     */
    public function register_archive_section_cpt()
    {

        $labels = array(
            'name'                  => __('Archive Sections', 'kayce-custom-archive-sections'),
            'singular_name'         => __('Archive Section', 'kayce-custom-archive-sections'),
            'menu_name'             => __('Archive Sections', 'kayce-custom-archive-sections'),
            'name_admin_bar'        => __('Archive Section', 'kayce-custom-archive-sections'),
            'add_new'               => __('Add New', 'kayce-custom-archive-sections'),
            'add_new_item'          => __('Add New Archive Section', 'kayce-custom-archive-sections'),
            'edit_item'             => __('Edit Archive Section', 'kayce-custom-archive-sections'),
            'new_item'              => __('New Archive Section', 'kayce-custom-archive-sections'),
            'view_item'             => __('View Archive Section', 'kayce-custom-archive-sections'),
            'search_items'          => __('Search Archive Sections', 'kayce-custom-archive-sections'),
            'not_found'             => __('No archive sections found', 'kayce-custom-archive-sections'),
            'not_found_in_trash'    => __('No archive sections found in Trash', 'kayce-custom-archive-sections'),
            'all_items'             => __('All Archive Sections', 'kayce-custom-archive-sections'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-layout',
            'supports'           => array('title', 'editor'),
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => true,
            'publicly_queryable' => false,
        );

        register_post_type($this->post_type, $args);
    }
}
