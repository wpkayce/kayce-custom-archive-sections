<?php

/**
 * Plugin Name:       Kayce Custom Archive Sections
 * Plugin URI:        https://wpkayce.com/plugins/kayce-custom-archive-sections
 * Description:       Create and manage reusable content sections that automatically display before or after posts on your blog index and category archive pages. Designed to work seamlessly with Gutenberg and Elementor.
 * Version:           1.0.0
 * Author:            WPKayce
 * Author URI:        https://wpkayce.com
 * Text Domain:       kayce-custom-archive-sections
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 * Requires at least: 6.8
 * Requires PHP:      7.4
 *
 * @package Kayce_Custom_Archive_Sections
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


define('KCAS_PLUGIN_VERSION', '1.0.0');
define('KCAS_PLUGIN_FILE', __FILE__);
define('KCAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KCAS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load the main plugin class.
require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-plugin.php';


function kcas_run_plugin()
{
    new KCAS_Plugin();
}
kcas_run_plugin();
