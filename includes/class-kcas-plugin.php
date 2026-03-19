<?php
/**
 * Main plugin class for Kayce Custom Archive Sections.
 *
 * @package Kayce_Custom_Archive_Sections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KCAS_Plugin
 */
class KCAS_Plugin {

	/** Custom post type slug. @var string */
	const POST_TYPE = 'kcas_section';

	/** Meta key: display location. @var string */
	const META_LOCATION = '_kcas_location';

	/** Meta key: display position (before/after). @var string */
	const META_POSITION = '_kcas_position';

	/** Meta key: active toggle (1 = active, 0 = inactive). @var string */
	const META_ACTIVE = '_kcas_active';

	/** Meta key: login-state visibility. @var string */
	const META_VISIBILITY = '_kcas_visibility';

	/** Meta key: specific category IDs (serialised array). @var string */
	const META_CATEGORIES = '_kcas_categories';

	/**
	 * Constructor — load files, initialise i18n, bootstrap components.
	 */
	public function __construct() {
		$this->load_textdomain();

		require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-cpt.php';
		require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-cache.php';
		require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-admin.php';
		require_once KCAS_PLUGIN_DIR . 'includes/class-kcas-frontend.php';

		new KCAS_CPT( self::POST_TYPE );

		new KCAS_Cache(
			self::POST_TYPE,
			self::META_LOCATION,
			self::META_POSITION
		);

		new KCAS_Admin(
			self::POST_TYPE,
			self::META_LOCATION,
			self::META_POSITION,
			self::META_ACTIVE,
			self::META_VISIBILITY,
			self::META_CATEGORIES
		);

		new KCAS_Frontend(
			self::POST_TYPE,
			self::META_LOCATION,
			self::META_POSITION,
			self::META_ACTIVE,
			self::META_VISIBILITY,
			self::META_CATEGORIES
		);
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'kayce-custom-archive-sections',
			false,
			dirname( plugin_basename( KCAS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
