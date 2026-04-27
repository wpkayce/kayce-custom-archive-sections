<?php

/**
 * Plugin Name:       Kayce Custom Archive Sections
 * Plugin URI:        https://github.com/wpkayce/kayce-custom-archive-sections
 * Description:       Create and manage reusable content sections that automatically display before or after posts on your blog index and category archive pages. Designed to work seamlessly with Gutenberg and Elementor.
 * Version:           1.0.0
 * Author:            rohitkc32,wpkacey
 * Author URI:        https://profiles.wordpress.org/rohitkc32
 * Text Domain:       kayce-custom-archive-sections
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package Kayce_Custom_Archive_Sections
 */

if (! defined('ABSPATH')) {
	exit;
}

define('KCAS_PLUGIN_VERSION', '1.0.0');
define('KCAS_PLUGIN_FILE', __FILE__);
define('KCAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KCAS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Flush rewrite rules on activation so the CPT is immediately available.
 */
function kcas_activate()
{
	require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-cpt.php';
	$cpt = new KCAS_CPT('kcas_section');
	$cpt->register_archive_section_cpt();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'kcas_activate');

/**
 * Flush rewrite rules on deactivation for a clean slate.
 */
function kcas_deactivate()
{
	if (class_exists('KCAS_Cache')) {
		KCAS_Cache::bust_all();
	}
	flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'kcas_deactivate');

/**
 * Bootstrap the plugin on plugins_loaded so all dependencies are available.
 */
function kcas_run_plugin()
{
	require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-plugin.php';
	new KCAS_Plugin();
}
add_action('plugins_loaded', 'kcas_run_plugin');