<?php
/**
 * Settings page for Kayce Custom Archive Sections.
 *
 * Adds a "Settings" sub-menu under the Archive Sections CPT menu and
 * exposes site-level options (e.g. cache expiry) that admins can adjust
 * without touching code.
 *
 * @package Kayce_Custom_Archive_Sections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KCAS_Settings
 */
class KCAS_Settings {

	/** Option key for cache expiry (hours). */
	const OPTION_CACHE_EXPIRY = 'kcas_cache_expiry_hours';

	/** Default cache expiry in hours. */
	const DEFAULT_CACHE_EXPIRY = 12;

	/** @var string CPT slug — used to attach the sub-menu. */
	protected $post_type;

	/**
	 * Constructor.
	 *
	 * @param string $post_type
	 */
	public function __construct( $post_type ) {
		$this->post_type = $post_type;

		add_action( 'admin_menu',    array( $this, 'add_settings_page' ) );
		add_action( 'admin_init',    array( $this, 'register_settings' ) );
	}

	// =========================================================================
	// Menu
	// =========================================================================

	/**
	 * Register the Settings sub-menu under the Archive Sections CPT menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . $this->post_type,
			__( 'Archive Sections Settings', 'kayce-custom-archive-sections' ),
			__( 'Settings', 'kayce-custom-archive-sections' ),
			'manage_options',
			'kcas-settings',
			array( $this, 'render_settings_page' )
		);
	}

	// =========================================================================
	// Settings API
	// =========================================================================

	/**
	 * Register settings, sections, and fields via the WordPress Settings API.
	 */
	public function register_settings() {
		register_setting(
			'kcas_settings_group',
			self::OPTION_CACHE_EXPIRY,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_cache_expiry' ),
				'default'           => self::DEFAULT_CACHE_EXPIRY,
			)
		);

		add_settings_section(
			'kcas_section_cache',
			__( 'Cache Settings', 'kayce-custom-archive-sections' ),
			'__return_false',
			'kcas-settings'
		);

		add_settings_field(
			self::OPTION_CACHE_EXPIRY,
			__( 'Cache Expiry', 'kayce-custom-archive-sections' ),
			array( $this, 'render_cache_expiry_field' ),
			'kcas-settings',
			'kcas_section_cache'
		);
	}

	/**
	 * Sanitise the cache expiry value — must be a non-negative integer.
	 *
	 * @param mixed $value
	 * @return int
	 */
	public function sanitize_cache_expiry( $value ) {
		$int = absint( $value );
		// Cap at 168 hours (1 week) to prevent runaway cache lifetimes.
		return min( $int, 168 );
	}

	// =========================================================================
	// Render
	// =========================================================================

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Archive Sections Settings', 'kayce-custom-archive-sections' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'kcas_settings_group' );
				do_settings_sections( 'kcas-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Cache Expiry field.
	 */
	public function render_cache_expiry_field() {
		$value = (int) get_option( self::OPTION_CACHE_EXPIRY, self::DEFAULT_CACHE_EXPIRY );
		?>
		<input
			type="number"
			id="<?php echo esc_attr( self::OPTION_CACHE_EXPIRY ); ?>"
			name="<?php echo esc_attr( self::OPTION_CACHE_EXPIRY ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			min="0"
			max="168"
			step="1"
			class="small-text"
		/>
		<span><?php esc_html_e( 'hours', 'kayce-custom-archive-sections' ); ?></span>
		<p class="description">
			<?php esc_html_e( 'How long to cache sections on the frontend. Default: 12 hours. Set to 0 to disable caching.', 'kayce-custom-archive-sections' ); ?>
		</p>
		<?php
	}

	// =========================================================================
	// Helper
	// =========================================================================

	/**
	 * Get the configured cache expiry in seconds (ready for set_transient).
	 *
	 * @return int Seconds. 0 means no caching.
	 */
	public static function get_cache_expiry_seconds() {
		$hours = (int) get_option( self::OPTION_CACHE_EXPIRY, self::DEFAULT_CACHE_EXPIRY );
		return $hours * HOUR_IN_SECONDS;
	}
}
