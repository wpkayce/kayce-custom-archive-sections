<?php

/**
 * Plugin Name:       Kayce Custom Archive Sections
 * Plugin URI:        https://github.com/wpkayce/kayce-custom-archive-sections
 * Description:       Create and manage reusable content sections that automatically display before or after posts on your blog index and category archive pages. Designed to work seamlessly with Gutenberg and Elementor.
 * Version:           2.0.0
 * Author:            rohitkc32,wpkacey
 * Author URI:        https://wpkayce.io
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

define('KCAS_PLUGIN_VERSION', '2.0.0');
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

	// Flag to show the welcome admin notice on first activation.
	set_transient('kcas_activation_notice', true, 30);
}
register_activation_hook(__FILE__, 'kcas_activate');

/**
 * Show a one-time welcome notice after plugin activation.
 */
function kcas_activation_notice()
{
	if (! get_transient('kcas_activation_notice')) {
		return;
	}
	delete_transient('kcas_activation_notice');

	$add_new_url = admin_url('post-new.php?post_type=kcas_section');
	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<strong><?php esc_html_e('Kayce Custom Archive Sections is active!', 'kayce-custom-archive-sections'); ?></strong>
			<?php esc_html_e('Ready to add content to your archive pages.', 'kayce-custom-archive-sections'); ?>
			&nbsp;<a href="<?php echo esc_url($add_new_url); ?>" class="button button-primary button-small">
				<?php esc_html_e('Create your first section →', 'kayce-custom-archive-sections'); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action('admin_notices', 'kcas_activation_notice');

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
