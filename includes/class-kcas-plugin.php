<?php

/**
 * Main plugin class for Kayce Custom Archive Sections.
 */

if (! defined('ABSPATH')) {
    exit;
}

class KCAS_Plugin
{

    /**
     * Custom post type slug.
     *
     * @var string
     */
    const POST_TYPE = 'kcas_section';
    const META_LOCATION = '_kcas_location'; // Meta key for display location.
    const META_POSITION = '_kcas_position'; // Meta key for display position.
    /**
     * Constructor – hook into WordPress.
     */
    public function __construct()
    {
        // Load our other files.
        require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-cpt.php';
        require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-admin.php';
        require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-frontend.php';

        // Initialize components.
        new KCAS_CPT(self::POST_TYPE);
        new KCAS_Admin(self::POST_TYPE, self::META_LOCATION, self::META_POSITION);
        new KCAS_Frontend(self::POST_TYPE, self::META_LOCATION, self::META_POSITION);
    }
}
