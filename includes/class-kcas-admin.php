<?php

/**
 * Admin UI for Kayce Custom Archive Sections.
 *
 * Handles:
 *  - Meta box (location, position, active toggle, visibility, specific categories).
 *  - Admin list-table columns (location, position, active, visibility).
 *  - Duplicate / Clone row action.
 *  - Preview link in the meta box.
 *
 * @package Kayce_Custom_Archive_Sections
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class KCAS_Admin
 */
class KCAS_Admin
{

	/** @var string */
	protected $post_type;
	/** @var string */
	protected $meta_location;
	/** @var string */
	protected $meta_position;
	/** @var string */
	protected $meta_active;
	/** @var string */
	protected $meta_visibility;
	/** @var string */
	protected $meta_categories;

	/**
	 * Valid location slugs — used for sanitisation.
	 * @var string[]
	 */
	private static $allowed_locations = array(
		'',
		'blog_index',
		'category_archives',
		'specific_categories',
		'single_post',
		'tag_archives',
		'author_archives',
		'search_results',
		'date_archives',
		// v1.2.0
		'front_page',
		'all_pages',
		'specific_pages',
		'cpt_archives',
		'cpt_singles',
	);

	/** @var string[] */
	private static $allowed_positions = array('before_content', 'before', 'after');

	/** @var string[] */
	private static $allowed_visibilities = array('all', 'logged_in', 'logged_out');

	/**
	 * Constructor.
	 *
	 * @param string $post_type
	 * @param string $meta_location
	 * @param string $meta_position
	 * @param string $meta_active
	 * @param string $meta_visibility
	 * @param string $meta_categories
	 */
	public function __construct($post_type, $meta_location, $meta_position, $meta_active, $meta_visibility, $meta_categories)
	{
		$this->post_type       = $post_type;
		$this->meta_location   = $meta_location;
		$this->meta_position   = $meta_position;
		$this->meta_active     = $meta_active;
		$this->meta_visibility = $meta_visibility;
		$this->meta_categories = $meta_categories;

		// Meta box.
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post_' . $this->post_type, array($this, 'save_meta'), 10, 2);

		// Assets.
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

		// List-table columns.
		add_filter('manage_' . $this->post_type . '_posts_columns',      array($this, 'add_list_columns'));
		add_action('manage_' . $this->post_type . '_posts_custom_column', array($this, 'render_list_column'), 10, 2);

		// Row actions — Duplicate.
		add_filter('post_row_actions', array($this, 'add_row_actions'), 10, 2);
		add_action('admin_post_kcas_duplicate_section', array($this, 'handle_duplicate'));

		// Drag-drop reorder (v1.4.0).
		add_action('wp_ajax_kcas_reorder_sections', array($this, 'handle_reorder'));

		// Empty state.
		add_action('admin_footer-edit.php', array($this, 'render_empty_state'));

		// Quick Edit.
		add_action('quick_edit_custom_box', array($this, 'render_quick_edit_fields'), 10, 2);

		// Bulk actions.
		add_filter('bulk_actions-edit-' . $this->post_type,        array($this, 'add_bulk_actions'));
		add_filter('handle_bulk_actions-edit-' . $this->post_type, array($this, 'handle_bulk_actions'), 10, 3);
		add_action('admin_notices', array($this, 'bulk_action_notice'));

		// Sortable columns.
		add_filter('manage_edit-' . $this->post_type . '_sortable_columns', array($this, 'register_sortable_columns'));
		add_action('pre_get_posts', array($this, 'handle_column_orderby'));
	}

	// =========================================================================
	// Assets
	// =========================================================================

	/**
	 * Enqueue CSS and JS only on our CPT edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets($hook)
	{
		$screen = get_current_screen();

		if (! $screen || $this->post_type !== $screen->post_type) {
			return;
		}

		wp_enqueue_style(
			'kcas-admin',
			KCAS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			KCAS_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'kcas-admin',
			KCAS_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery-ui-sortable'),
			KCAS_PLUGIN_VERSION,
			true
		);

		// Drag-drop is only meaningful when the list is in its natural menu_order
		// sort. If the user has clicked a column header (?orderby=...), disable it.
		$is_list_screen  = $screen && 'edit' === $screen->base;
		$has_custom_sort = $is_list_screen && ! empty($_GET['orderby']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'kcas-admin',
			'kcasAdmin',
			array(
				'ajaxUrl'      => admin_url('admin-ajax.php'),
				'reorderNonce' => wp_create_nonce('kcas_reorder'),
				'isDraggable'  => $is_list_screen && ! $has_custom_sort,
				/* translators: shown in browser console on AJAX error */
				'reorderError' => __('Could not save the new order. Please reload the page and try again.', 'kayce-custom-archive-sections'),
			)
		);
	}

	// =========================================================================
	// Meta Box
	// =========================================================================

	/**
	 * Register the display-settings meta box.
	 */
	public function register_meta_boxes()
	{
		add_meta_box(
			'kcas_display_settings',
			__('Archive Section Settings', 'kayce-custom-archive-sections'),
			array($this, 'render_meta_box'),
			$this->post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param WP_Post $post
	 */
	public function render_meta_box($post)
	{
		wp_nonce_field('kcas_save_meta', 'kcas_meta_nonce');

		$location   = get_post_meta($post->ID, $this->meta_location,   true);
		$position   = get_post_meta($post->ID, $this->meta_position,   true);
		$active     = get_post_meta($post->ID, $this->meta_active,     true);
		$visibility = get_post_meta($post->ID, $this->meta_visibility, true);
		$categories = get_post_meta($post->ID, $this->meta_categories, true);

		// Defaults for new posts.
		if ('' === $position) {
			$position   = 'before';
		}
		if ('' === $active) {
			$active     = '1';
		}
		if ('' === $visibility) {
			$visibility = 'all';
		}
		if (! is_array($categories)) {
			$categories = array();
		}

		$all_cats  = get_categories(array('hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
		$all_pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC', 'post_status' => 'publish'));
		$saved_pages = get_post_meta($post->ID, '_kcas_pages', true);
		$saved_cpt   = (string) get_post_meta($post->ID, '_kcas_cpt',   true);
		if (! is_array($saved_pages)) {
			$saved_pages = array();
		}

		// Public, non-built-in CPTs — exclude our own post type.
		$public_cpts = get_post_types(
			array('public' => true, '_builtin' => false),
			'objects'
		);
		unset($public_cpts[$this->post_type]);

		// v1.3.0 — Display Control fields.
		$date_from    = get_post_meta($post->ID, '_kcas_date_from', true);
		$date_to      = get_post_meta($post->ID, '_kcas_date_to',   true);
		$saved_devices = get_post_meta($post->ID, '_kcas_devices',  true);
		$saved_roles   = get_post_meta($post->ID, '_kcas_roles',    true);
		// Default: all devices selected (no restriction).
		if (! is_array($saved_devices)) {
			$saved_devices = array('desktop', 'tablet', 'mobile');
		}
		if (! is_array($saved_roles)) {
			$saved_roles = array();
		}
		// All WP roles, sorted by display name.
		$all_roles = wp_roles()->roles;
		uasort($all_roles, static function ($a, $b) {
			return strcmp($a['name'], $b['name']);
		});
?>

		<?php /* ── Active toggle ── */ ?>
		<div class="kcas-section kcas-section--active">
			<label class="kcas-toggle">
				<input type="checkbox" name="kcas_active" value="1" <?php checked($active, '1'); ?> />
				<span class="kcas-toggle-track">
					<span class="kcas-toggle-thumb"></span>
				</span>
				<span class="kcas-toggle-label">
					<span class="kcas-toggle-on"><?php esc_html_e('Active', 'kayce-custom-archive-sections'); ?></span>
					<span class="kcas-toggle-off"><?php esc_html_e('Inactive', 'kayce-custom-archive-sections'); ?></span>
					<span class="kcas-toggle-hint"><?php esc_html_e('— showing on frontend', 'kayce-custom-archive-sections'); ?></span>
					<span class="kcas-toggle-hint kcas-toggle-hint--off"><?php esc_html_e('— hidden from frontend', 'kayce-custom-archive-sections'); ?></span>
				</span>
			</label>
		</div>

		<div class="kcas-divider"></div>

		<?php /* ── Location ── */ ?>
		<div class="kcas-section">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-location"></span>
				<?php esc_html_e('Location', 'kayce-custom-archive-sections'); ?>
			</p>

			<div class="kcas-location-grid" id="kcas-location-options">

				<?php /* Group: General */ ?>
				<div class="kcas-location-group-label"><?php esc_html_e('General', 'kayce-custom-archive-sections'); ?></div>

				<?php
				$general_options = array(
					''           => array( 'label' => __( 'Disabled',   'kayce-custom-archive-sections' ), 'icon' => 'dashicons-minus'       ),
					'blog_index' => array( 'label' => __( 'Blog index', 'kayce-custom-archive-sections' ), 'icon' => 'dashicons-admin-home'  ),
					'front_page' => array( 'label' => __( 'Front page', 'kayce-custom-archive-sections' ), 'icon' => 'dashicons-welcome-view-site' ),
				);
				foreach ($general_options as $value => $opt) : ?>
				<label class="kcas-location-card <?php echo esc_attr(($location === $value) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_location" value="<?php echo esc_attr($value); ?>" <?php checked($location, $value); ?> />
					<span class="kcas-card-body">
						<span class="dashicons <?php echo esc_attr($opt['icon']); ?>"></span>
						<span class="kcas-card-label"><?php echo esc_html($opt['label']); ?></span>
					</span>
				</label>
				<?php endforeach; ?>

				<?php /* Group: Archives */ ?>
				<div class="kcas-location-group-label"><?php esc_html_e('Archives', 'kayce-custom-archive-sections'); ?></div>

				<?php
				$archive_options = array(
					'category_archives'   => array( 'label' => __( 'All categories',      'kayce-custom-archive-sections' ), 'icon' => 'dashicons-category'     ),
					'specific_categories' => array( 'label' => __( 'Specific categories', 'kayce-custom-archive-sections' ), 'icon' => 'dashicons-admin-links'  ),
					'tag_archives'        => array( 'label' => __( 'Tag archives',        'kayce-custom-archive-sections' ), 'icon' => 'dashicons-tag'          ),
					'author_archives'     => array( 'label' => __( 'Author archives',     'kayce-custom-archive-sections' ), 'icon' => 'dashicons-admin-users'  ),
					'search_results'      => array( 'label' => __( 'Search results',      'kayce-custom-archive-sections' ), 'icon' => 'dashicons-search'       ),
					'date_archives'       => array( 'label' => __( 'Date archives',       'kayce-custom-archive-sections' ), 'icon' => 'dashicons-calendar-alt' ),
				);
				foreach ($archive_options as $value => $opt) : ?>
				<label class="kcas-location-card <?php echo esc_attr(($location === $value) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_location" value="<?php echo esc_attr($value); ?>" <?php checked($location, $value); ?> />
					<span class="kcas-card-body">
						<span class="dashicons <?php echo esc_attr($opt['icon']); ?>"></span>
						<span class="kcas-card-label"><?php echo esc_html($opt['label']); ?></span>
					</span>
				</label>
				<?php endforeach; ?>

				<?php /* Group: Singular */ ?>
				<div class="kcas-location-group-label"><?php esc_html_e('Singular', 'kayce-custom-archive-sections'); ?></div>

				<?php
				$singular_options = array(
					'single_post'   => array( 'label' => __( 'Single posts', 'kayce-custom-archive-sections' ), 'icon' => 'dashicons-admin-post'    ),
					'all_pages'     => array( 'label' => __( 'All pages',    'kayce-custom-archive-sections' ), 'icon' => 'dashicons-admin-page'    ),
					'specific_pages'=> array( 'label' => __( 'Specific pages','kayce-custom-archive-sections'), 'icon' => 'dashicons-excerpt-view'  ),
				);
				foreach ($singular_options as $value => $opt) : ?>
				<label class="kcas-location-card <?php echo esc_attr(($location === $value) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_location" value="<?php echo esc_attr($value); ?>" <?php checked($location, $value); ?> />
					<span class="kcas-card-body">
						<span class="dashicons <?php echo esc_attr($opt['icon']); ?>"></span>
						<span class="kcas-card-label"><?php echo esc_html($opt['label']); ?></span>
					</span>
				</label>
				<?php endforeach; ?>

				<?php /* Group: Custom Post Types (only shown when CPTs exist) */ ?>
				<?php if (! empty($public_cpts)) : ?>
				<div class="kcas-location-group-label"><?php esc_html_e('Custom Post Types', 'kayce-custom-archive-sections'); ?></div>

				<?php
				$cpt_options = array(
					'cpt_archives' => array( 'label' => __( 'CPT archives', 'kayce-custom-archive-sections' ), 'icon' => 'dashicons-archive'       ),
					'cpt_singles'  => array( 'label' => __( 'CPT singles',  'kayce-custom-archive-sections' ), 'icon' => 'dashicons-media-document' ),
				);
				foreach ($cpt_options as $value => $opt) : ?>
				<label class="kcas-location-card <?php echo esc_attr(($location === $value) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_location" value="<?php echo esc_attr($value); ?>" <?php checked($location, $value); ?> />
					<span class="kcas-card-body">
						<span class="dashicons <?php echo esc_attr($opt['icon']); ?>"></span>
						<span class="kcas-card-label"><?php echo esc_html($opt['label']); ?></span>
					</span>
				</label>
				<?php endforeach; ?>
				<?php endif; ?>

			</div><!-- .kcas-location-grid -->
		</div>

		<?php /* ── Specific Categories (shown / hidden via JS) ── */ ?>
		<div class="kcas-section" id="kcas-categories-row"
			style="<?php echo esc_attr(('specific_categories' !== $location) ? 'display:none;' : ''); ?>">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e('Categories', 'kayce-custom-archive-sections'); ?>
			</p>
			<?php if (! empty($all_cats)) : ?>
				<div class="kcas-cat-search-wrap">
					<span class="dashicons dashicons-search kcas-cat-search-icon"></span>
					<input
						type="text"
						id="kcas-cat-search"
						class="kcas-cat-search"
						placeholder="<?php esc_attr_e('Filter categories…', 'kayce-custom-archive-sections'); ?>"
						autocomplete="off"
					/>
				</div>
				<div class="kcas-category-list">
					<?php foreach ($all_cats as $cat) :
						$is_checked = in_array((int) $cat->term_id, array_map('intval', $categories), true);
					?>
					<label class="kcas-cat-item <?php echo esc_attr($is_checked ? 'is-checked' : ''); ?>">
						<input type="checkbox" name="kcas_categories[]" value="<?php echo esc_attr($cat->term_id); ?>"
							<?php checked($is_checked); ?> />
						<span class="kcas-cat-name"><?php echo esc_html($cat->name); ?></span>
						<span class="kcas-cat-count"><?php echo (int) $cat->count; ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="kcas-empty-note"><?php esc_html_e('No categories found.', 'kayce-custom-archive-sections'); ?></p>
			<?php endif; ?>
		</div>

		<?php /* ── Specific Pages (shown / hidden via JS) ── */ ?>
		<div class="kcas-section" id="kcas-pages-row"
			style="<?php echo esc_attr(('specific_pages' !== $location) ? 'display:none;' : ''); ?>">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-excerpt-view"></span>
				<?php esc_html_e('Pages', 'kayce-custom-archive-sections'); ?>
			</p>
			<?php if (! empty($all_pages)) : ?>
				<div class="kcas-cat-search-wrap">
					<span class="dashicons dashicons-search kcas-cat-search-icon"></span>
					<input
						type="text"
						id="kcas-page-search"
						class="kcas-cat-search"
						placeholder="<?php esc_attr_e('Filter pages…', 'kayce-custom-archive-sections'); ?>"
						autocomplete="off"
					/>
				</div>
				<div class="kcas-category-list" id="kcas-pages-list">
					<?php foreach ($all_pages as $pg) :
						$is_checked = in_array((int) $pg->ID, array_map('intval', $saved_pages), true);
					?>
					<label class="kcas-cat-item <?php echo esc_attr($is_checked ? 'is-checked' : ''); ?>">
						<input type="checkbox" name="kcas_pages[]" value="<?php echo esc_attr($pg->ID); ?>"
							<?php checked($is_checked); ?> />
						<span class="kcas-cat-name"><?php echo esc_html($pg->post_title); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="kcas-empty-note"><?php esc_html_e('No pages found.', 'kayce-custom-archive-sections'); ?></p>
			<?php endif; ?>
		</div>

		<?php /* ── CPT Picker (shown / hidden via JS) ── */ ?>
		<?php if (! empty($public_cpts)) : ?>
		<div class="kcas-section" id="kcas-cpt-row"
			style="<?php echo esc_attr((! in_array($location, array('cpt_archives', 'cpt_singles'), true)) ? 'display:none;' : ''); ?>">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e('Post type', 'kayce-custom-archive-sections'); ?>
			</p>
			<select name="kcas_cpt" id="kcas-cpt-select" class="widefat">
				<option value=""><?php esc_html_e('— Select a post type —', 'kayce-custom-archive-sections'); ?></option>
				<?php foreach ($public_cpts as $cpt_obj) : ?>
				<option value="<?php echo esc_attr($cpt_obj->name); ?>"
					<?php selected($saved_cpt, $cpt_obj->name); ?>>
					<?php echo esc_html($cpt_obj->labels->singular_name . ' (' . $cpt_obj->name . ')'); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<div class="kcas-divider"></div>

		<?php /* ── Position ── */ ?>
		<div class="kcas-section" id="kcas-position-row">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-sort"></span>
				<?php esc_html_e('Position', 'kayce-custom-archive-sections'); ?>
			</p>
			<div class="kcas-btn-group kcas-btn-group--3">
				<label class="kcas-btn-option <?php echo esc_attr(('before_content' === $position) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_position" value="before_content" <?php checked($position, 'before_content'); ?> />
					<span><span class="dashicons dashicons-layout"></span> <?php esc_html_e('After header', 'kayce-custom-archive-sections'); ?></span>
				</label>
				<label class="kcas-btn-option <?php echo esc_attr(('before' === $position) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_position" value="before" <?php checked($position, 'before'); ?> />
					<span><span class="dashicons dashicons-arrow-up-alt2"></span> <?php esc_html_e('Before posts', 'kayce-custom-archive-sections'); ?></span>
				</label>
				<label class="kcas-btn-option <?php echo esc_attr(('after' === $position) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_position" value="after" <?php checked($position, 'after'); ?> />
					<span><span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e('After posts', 'kayce-custom-archive-sections'); ?></span>
				</label>
			</div>
		</div>

		<div class="kcas-divider"></div>

		<?php /* ── Visibility ── */ ?>
		<div class="kcas-section">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e('Visibility', 'kayce-custom-archive-sections'); ?>
			</p>
			<div class="kcas-btn-group kcas-btn-group--3">
				<label class="kcas-btn-option <?php echo esc_attr(('all' === $visibility) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_visibility" value="all" <?php checked($visibility, 'all'); ?> />
					<span><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Everyone', 'kayce-custom-archive-sections'); ?></span>
				</label>
				<label class="kcas-btn-option <?php echo esc_attr(('logged_in' === $visibility) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_visibility" value="logged_in" <?php checked($visibility, 'logged_in'); ?> />
					<span><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Logged in', 'kayce-custom-archive-sections'); ?></span>
				</label>
				<label class="kcas-btn-option <?php echo esc_attr(('logged_out' === $visibility) ? 'is-selected' : ''); ?>">
					<input type="radio" name="kcas_visibility" value="logged_out" <?php checked($visibility, 'logged_out'); ?> />
					<span><span class="dashicons dashicons-unlock"></span> <?php esc_html_e('Logged out', 'kayce-custom-archive-sections'); ?></span>
				</label>
			</div>
		</div>

		<div class="kcas-divider"></div>

		<?php /* ── v1.3.0: Scheduling ── */ ?>
		<div class="kcas-section">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e('Schedule', 'kayce-custom-archive-sections'); ?>
			</p>
			<div class="kcas-schedule-fields">
				<label class="kcas-schedule-row">
					<span class="kcas-schedule-label"><?php esc_html_e('From', 'kayce-custom-archive-sections'); ?></span>
					<input
						type="date"
						name="kcas_date_from"
						value="<?php echo esc_attr($date_from); ?>"
						class="kcas-date-input"
						placeholder="YYYY-MM-DD"
					/>
				</label>
				<label class="kcas-schedule-row">
					<span class="kcas-schedule-label"><?php esc_html_e('To', 'kayce-custom-archive-sections'); ?></span>
					<input
						type="date"
						name="kcas_date_to"
						value="<?php echo esc_attr($date_to); ?>"
						class="kcas-date-input"
						placeholder="YYYY-MM-DD"
					/>
				</label>
			</div>
			<p class="kcas-field-hint"><?php esc_html_e('Leave blank to always show.', 'kayce-custom-archive-sections'); ?></p>
		</div>

		<div class="kcas-divider"></div>

		<?php /* ── v1.3.0: Devices ── */ ?>
		<div class="kcas-section">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-smartphone"></span>
				<?php esc_html_e('Devices', 'kayce-custom-archive-sections'); ?>
			</p>
			<div class="kcas-devices-row">
				<?php
				$device_options = array(
					'desktop' => array('label' => __('Desktop', 'kayce-custom-archive-sections'), 'icon' => 'dashicons-desktop'),
					'tablet'  => array('label' => __('Tablet',  'kayce-custom-archive-sections'), 'icon' => 'dashicons-tablet'),
					'mobile'  => array('label' => __('Mobile',  'kayce-custom-archive-sections'), 'icon' => 'dashicons-smartphone'),
				);
				foreach ($device_options as $device_value => $device_opt) :
					$device_checked = in_array($device_value, $saved_devices, true);
				?>
				<label class="kcas-device-option <?php echo esc_attr($device_checked ? 'is-checked' : ''); ?>">
					<input
						type="checkbox"
						name="kcas_devices[]"
						value="<?php echo esc_attr($device_value); ?>"
						<?php checked($device_checked); ?>
					/>
					<span class="dashicons <?php echo esc_attr($device_opt['icon']); ?>"></span>
					<span><?php echo esc_html($device_opt['label']); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
			<p class="kcas-field-hint"><?php esc_html_e('Uncheck devices to hide on them.', 'kayce-custom-archive-sections'); ?></p>
		</div>

		<div class="kcas-divider"></div>

		<?php /* ── v1.3.0: User roles ── */ ?>
		<div class="kcas-section" id="kcas-roles-row"
			style="<?php echo esc_attr(('logged_out' === $visibility) ? 'display:none;' : ''); ?>">
			<p class="kcas-section-label">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e('User roles', 'kayce-custom-archive-sections'); ?>
			</p>
			<?php if (! empty($all_roles)) : ?>
				<div class="kcas-category-list" id="kcas-roles-list">
					<?php foreach ($all_roles as $role_slug => $role_data) :
						$role_checked = in_array($role_slug, $saved_roles, true);
					?>
					<label class="kcas-cat-item <?php echo esc_attr($role_checked ? 'is-checked' : ''); ?>">
						<input
							type="checkbox"
							name="kcas_roles[]"
							value="<?php echo esc_attr($role_slug); ?>"
							<?php checked($role_checked); ?>
						/>
						<span class="kcas-cat-name">
							<?php echo esc_html(translate_user_role($role_data['name'])); ?>
						</span>
					</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<p class="kcas-field-hint"><?php esc_html_e('Leave all unchecked to show to all users.', 'kayce-custom-archive-sections'); ?></p>
		</div>

		<?php /* ── Preview link ── */ ?>
		<?php $preview_url = $this->get_preview_url($location, $categories); ?>
		<?php if ($preview_url) : ?>
			<div class="kcas-divider"></div>
			<div class="kcas-section kcas-section--preview">
				<a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="kcas-preview-btn">
					<span class="dashicons dashicons-external"></span>
					<?php esc_html_e('Preview archive page', 'kayce-custom-archive-sections'); ?>
				</a>
			</div>
		<?php endif; ?>

<?php
	}

	/**
	 * Resolve a frontend URL to preview the section's archive page.
	 *
	 * @param string $location
	 * @param array  $categories Saved category IDs.
	 * @return string|null
	 */
	private function get_preview_url($location, $categories)
	{
		global $post;

		switch ($location) {
			case 'blog_index':
				$page_id = (int) get_option('page_for_posts');
				return $page_id ? get_permalink($page_id) : home_url('/');

			case 'front_page':
				$page_id = (int) get_option('page_on_front');
				return $page_id ? get_permalink($page_id) : home_url('/');

			case 'category_archives':
				// Link to the first category found.
				$cats = get_categories(array('number' => 1, 'hide_empty' => true));
				return ! empty($cats) ? get_category_link($cats[0]->term_id) : null;

			case 'specific_categories':
				if (! empty($categories)) {
					return get_category_link((int) reset($categories));
				}
				return null;

			case 'single_post':
				$posts = get_posts(array('numberposts' => 1, 'post_status' => 'publish'));
				return ! empty($posts) ? get_permalink($posts[0]->ID) : null;

			case 'all_pages':
				$pages = get_pages(array('number' => 1, 'post_status' => 'publish'));
				return ! empty($pages) ? get_permalink($pages[0]->ID) : null;

			case 'specific_pages':
				// Use the currently-edited post's saved pages list for the preview.
				if (isset($post->ID)) {
					$saved_pages = get_post_meta($post->ID, '_kcas_pages', true);
					if (is_array($saved_pages) && ! empty($saved_pages)) {
						return get_permalink((int) reset($saved_pages));
					}
				}
				// Fallback: link to first published page.
				$pages = get_pages(array('number' => 1, 'post_status' => 'publish'));
				return ! empty($pages) ? get_permalink($pages[0]->ID) : null;

			case 'tag_archives':
				$tags = get_tags(array('number' => 1, 'hide_empty' => true));
				return ! empty($tags) ? get_tag_link($tags[0]->term_id) : null;

			case 'author_archives':
				$users = get_users(array('number' => 1, 'has_published_posts' => true));
				return ! empty($users) ? get_author_posts_url($users[0]->ID) : null;

			case 'search_results':
				return home_url('/?s=a');

			case 'date_archives':
				return get_year_link((int) gmdate('Y'));

			case 'cpt_archives':
				if (isset($post->ID)) {
					$cpt_slug = get_post_meta($post->ID, '_kcas_cpt', true);
					if ('' !== $cpt_slug) {
						$url = get_post_type_archive_link(sanitize_key($cpt_slug));
						if ($url) {
							return $url;
						}
					}
				}
				return null;

			case 'cpt_singles':
				if (isset($post->ID)) {
					$cpt_slug = get_post_meta($post->ID, '_kcas_cpt', true);
					if ('' !== $cpt_slug) {
						$cpt_posts = get_posts(array('post_type' => sanitize_key($cpt_slug), 'numberposts' => 1, 'post_status' => 'publish'));
						if (! empty($cpt_posts)) {
							return get_permalink($cpt_posts[0]->ID);
						}
					}
				}
				return null;
		}

		return null;
	}

	// =========================================================================
	// Save Meta
	// =========================================================================

	/**
	 * Persist meta values when the section post is saved.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_meta($post_id, $post)
	{
		// Accept either the full meta box nonce or the Quick Edit nonce.
		if (isset($_POST['kcas_meta_nonce'])) {
			if (! wp_verify_nonce(sanitize_key($_POST['kcas_meta_nonce']), 'kcas_save_meta')) {
				return;
			}
		} elseif (isset($_POST['kcas_quick_edit_nonce'])) {
			if (! wp_verify_nonce(sanitize_key($_POST['kcas_quick_edit_nonce']), 'kcas_quick_edit')) {
				return;
			}
		} else {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if ('revision' === $post->post_type) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		// ── Active ────────────────────────────────────────────────────────────
		$active = isset($_POST['kcas_active']) ? '1' : '0';
		update_post_meta($post_id, $this->meta_active, $active);

		// ── Location ──────────────────────────────────────────────────────────
		$location = isset($_POST['kcas_location'])
			? sanitize_text_field(wp_unslash($_POST['kcas_location']))
			: '';

		if (! in_array($location, self::$allowed_locations, true)) {
			$location = '';
		}

		if ('' !== $location) {
			update_post_meta($post_id, $this->meta_location, $location);
		} else {
			delete_post_meta($post_id, $this->meta_location);
		}

		// ── Specific categories ───────────────────────────────────────────────
		// Only save/update when in specific_categories mode. When switching to
		// a different location we intentionally keep the saved category IDs so
		// the user does not have to reselect them if they switch back.
		if ('specific_categories' === $location) {
			if (! empty($_POST['kcas_categories'])) {
				// Sanitise: each value must be a positive integer (term ID).
				$raw_cats = array_map('absint', (array) $_POST['kcas_categories']);
				$cats     = array_filter($raw_cats); // remove zeros
				update_post_meta($post_id, $this->meta_categories, array_values($cats));
			} else {
				// User unchecked all categories while still on specific_categories.
				delete_post_meta($post_id, $this->meta_categories);
			}
		}
		// If location !== specific_categories, leave meta untouched (preserve selection).

		// ── Specific Pages ────────────────────────────────────────────────────
		// Same preserve-on-switch pattern as specific_categories.
		if ('specific_pages' === $location) {
			if (! empty($_POST['kcas_pages'])) {
				$raw_pages = array_map('absint', (array) $_POST['kcas_pages']);
				$pages     = array_filter($raw_pages); // remove zeros
				update_post_meta($post_id, '_kcas_pages', array_values($pages));
			} else {
				// User deselected all pages while still on specific_pages.
				delete_post_meta($post_id, '_kcas_pages');
			}
		}
		// If location !== specific_pages, leave _kcas_pages untouched.

		// ── CPT slug ──────────────────────────────────────────────────────────
		if (in_array($location, array('cpt_archives', 'cpt_singles'), true)) {
			$cpt_slug = isset($_POST['kcas_cpt'])
				? sanitize_key(wp_unslash($_POST['kcas_cpt']))
				: '';
			if ('' !== $cpt_slug) {
				update_post_meta($post_id, '_kcas_cpt', $cpt_slug);
			} else {
				delete_post_meta($post_id, '_kcas_cpt');
			}
		}
		// If location is not CPT-related, leave _kcas_cpt untouched.

		// ── Position ──────────────────────────────────────────────────────────
		$position = isset($_POST['kcas_position'])
			? sanitize_text_field(wp_unslash($_POST['kcas_position']))
			: 'before';

		if (! in_array($position, self::$allowed_positions, true)) {
			$position = 'before';
		}

		update_post_meta($post_id, $this->meta_position, $position);

		// ── Visibility ────────────────────────────────────────────────────────
		$visibility = isset($_POST['kcas_visibility'])
			? sanitize_text_field(wp_unslash($_POST['kcas_visibility']))
			: 'all';

		if (! in_array($visibility, self::$allowed_visibilities, true)) {
			$visibility = 'all';
		}

		update_post_meta($post_id, $this->meta_visibility, $visibility);

		// ── v1.3.0: Scheduling ────────────────────────────────────────────────
		foreach (array('kcas_date_from' => '_kcas_date_from', 'kcas_date_to' => '_kcas_date_to') as $field => $meta_key) {
			$date_value = isset($_POST[$field])
				? sanitize_text_field(wp_unslash($_POST[$field]))
				: '';

			// Accept only valid YYYY-MM-DD dates; discard anything else.
			if ('' !== $date_value) {
				$parsed = date_create_from_format('Y-m-d', $date_value);
				if (! $parsed || $parsed->format('Y-m-d') !== $date_value) {
					$date_value = '';
				}
			}

			if ('' !== $date_value) {
				update_post_meta($post_id, $meta_key, $date_value);
			} else {
				delete_post_meta($post_id, $meta_key);
			}
		}

		// ── v1.3.0: Devices ──────────────────────────────────────────────────
		$allowed_devices = array('desktop', 'tablet', 'mobile');
		if (! empty($_POST['kcas_devices'])) {
			$raw_devices = array_map('sanitize_key', array_map('wp_unslash', (array) $_POST['kcas_devices']));
			$devices     = array_values(
				array_filter($raw_devices, static function ($d) use ($allowed_devices) {
					return in_array($d, $allowed_devices, true);
				})
			);
		} else {
			// All checkboxes unchecked — no visible device selected. Save empty.
			$devices = array();
		}
		update_post_meta($post_id, '_kcas_devices', $devices);

		// ── v1.3.0: User roles ────────────────────────────────────────────────
		// Only save/update when the nonce is from the full meta box (not Quick Edit,
		// which doesn't include role checkboxes).
		if (isset($_POST['kcas_meta_nonce'])) {
			$all_role_keys = array_keys(wp_roles()->roles);
			if (! empty($_POST['kcas_roles'])) {
				$raw_roles = array_map('sanitize_key', array_map('wp_unslash', (array) $_POST['kcas_roles']));
				$roles     = array_values(
					array_filter($raw_roles, static function ($r) use ($all_role_keys) {
						return in_array($r, $all_role_keys, true);
					})
				);
			} else {
				$roles = array();
			}
			update_post_meta($post_id, '_kcas_roles', $roles);
		}
	}

	// =========================================================================
	// List Table Columns
	// =========================================================================

	/**
	 * Define list-table columns for the CPT.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public function add_list_columns($columns)
	{
		// Remove date — we'll re-add it at the end.
		$date = isset($columns['date']) ? $columns['date'] : null;
		unset($columns['date']);

		// Drag-handle column — no heading text, sits before everything else (v1.4.0).
		$columns = array('kcas_sort' => '') + $columns;

		$columns['kcas_active']     = __('Active', 'kayce-custom-archive-sections');
		$columns['kcas_location']   = __('Location', 'kayce-custom-archive-sections');
		$columns['kcas_position']   = __('Position', 'kayce-custom-archive-sections');
		$columns['kcas_visibility'] = __('Visibility', 'kayce-custom-archive-sections');

		if ($date) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Render a custom list-table column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_list_column($column, $post_id)
	{
		switch ($column) {

			case 'kcas_sort':
				// Drag handle — only shown when sortable (isDraggable set in JS).
				echo '<span class="kcas-sort-handle" title="' . esc_attr__('Drag to reorder', 'kayce-custom-archive-sections') . '" aria-hidden="true">'
					. '<span class="dashicons dashicons-menu"></span>'
					. '</span>';
				break;

			case 'kcas_active':
				$active     = get_post_meta($post_id, $this->meta_active,     true);
				$position   = get_post_meta($post_id, $this->meta_position,   true);
				$visibility = get_post_meta($post_id, $this->meta_visibility, true);
				// Hidden spans for Quick Edit JS population.
				echo '<span class="kcas-qe-data" style="display:none;"'
					. ' data-active="'     . esc_attr('' === $active     ? '1'       : $active)     . '"'
					. ' data-position="'   . esc_attr('' === $position   ? 'before'  : $position)   . '"'
					. ' data-visibility="' . esc_attr('' === $visibility ? 'all'     : $visibility) . '"'
					. '></span>';
				if ('1' === $active || '' === $active) {
					echo '<span class="kcas-badge kcas-badge--active" title="' . esc_attr__('Active', 'kayce-custom-archive-sections') . '">&#10003; ' . esc_html__('Yes', 'kayce-custom-archive-sections') . '</span>';
				} else {
					echo '<span class="kcas-badge kcas-badge--inactive" title="' . esc_attr__('Inactive', 'kayce-custom-archive-sections') . '">&#8212; ' . esc_html__('No', 'kayce-custom-archive-sections') . '</span>';
				}
				break;

			case 'kcas_location':
				$location = get_post_meta($post_id, $this->meta_location, true);
				$labels   = array(
					''                    => '<em>' . esc_html__('Disabled', 'kayce-custom-archive-sections') . '</em>',
					'blog_index'          => esc_html__('Blog index', 'kayce-custom-archive-sections'),
					'front_page'          => esc_html__('Front page', 'kayce-custom-archive-sections'),
					'category_archives'   => esc_html__('All categories', 'kayce-custom-archive-sections'),
					'specific_categories' => esc_html__('Specific categories', 'kayce-custom-archive-sections'),
					'single_post'         => esc_html__('Single posts', 'kayce-custom-archive-sections'),
					'all_pages'           => esc_html__('All pages', 'kayce-custom-archive-sections'),
					'specific_pages'      => esc_html__('Specific pages', 'kayce-custom-archive-sections'),
					'tag_archives'        => esc_html__('Tags', 'kayce-custom-archive-sections'),
					'author_archives'     => esc_html__('Authors', 'kayce-custom-archive-sections'),
					'search_results'      => esc_html__('Search results', 'kayce-custom-archive-sections'),
					'date_archives'       => esc_html__('Date archives', 'kayce-custom-archive-sections'),
					'cpt_archives'        => esc_html__('CPT archives', 'kayce-custom-archive-sections'),
					'cpt_singles'         => esc_html__('CPT singles', 'kayce-custom-archive-sections'),
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo isset($labels[$location]) ? $labels[$location] : esc_html($location);

				// Show specific category names as a hint.
				if ('specific_categories' === $location) {
					$cat_ids = get_post_meta($post_id, $this->meta_categories, true);
					if (is_array($cat_ids) && ! empty($cat_ids)) {
						$names = array();
						foreach (array_slice($cat_ids, 0, 3) as $id) {
							$cat = get_category((int) $id);
							if ($cat && ! is_wp_error($cat)) {
								$names[] = esc_html($cat->name);
							}
						}
						if (! empty($names)) {
							echo '<br><span class="kcas-col-hint">' . implode(', ', $names); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each element already passed through esc_html() above
							if (count($cat_ids) > 3) {
								echo ' &hellip;';
							}
							echo '</span>';
						}
					}
				}

				// Show specific page titles as a hint.
				if ('specific_pages' === $location) {
					$page_ids = get_post_meta($post_id, '_kcas_pages', true);
					if (is_array($page_ids) && ! empty($page_ids)) {
						$titles = array();
						foreach (array_slice($page_ids, 0, 3) as $pid) {
							$pg = get_post((int) $pid);
							if ($pg) {
								$titles[] = esc_html($pg->post_title);
							}
						}
						if (! empty($titles)) {
							echo '<br><span class="kcas-col-hint">' . implode(', ', $titles); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each element already passed through esc_html() above
							if (count($page_ids) > 3) {
								echo ' &hellip;';
							}
							echo '</span>';
						}
					}
				}

				// Show the targeted CPT slug as a hint.
				if (in_array($location, array('cpt_archives', 'cpt_singles'), true)) {
					$cpt_slug = get_post_meta($post_id, '_kcas_cpt', true);
					if ('' !== $cpt_slug) {
						$cpt_obj = get_post_type_object(sanitize_key($cpt_slug));
						$hint    = $cpt_obj ? esc_html($cpt_obj->labels->singular_name) : esc_html($cpt_slug);
						echo '<br><span class="kcas-col-hint">' . $hint . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passed through esc_html() above
					}
				}
				break;

			case 'kcas_position':
				$position = get_post_meta($post_id, $this->meta_position, true);
				$pos_labels = array(
					'before_content' => __('After header',  'kayce-custom-archive-sections'),
					'before'         => __('Before posts',  'kayce-custom-archive-sections'),
					'after'          => __('After posts',   'kayce-custom-archive-sections'),
				);
				echo esc_html(isset($pos_labels[$position]) ? $pos_labels[$position] : __('Before posts', 'kayce-custom-archive-sections'));
				break;

			case 'kcas_visibility':
				$visibility = get_post_meta($post_id, $this->meta_visibility, true);
				$labels     = array(
					'all'        => __('Everyone', 'kayce-custom-archive-sections'),
					'logged_in'  => __('Logged in', 'kayce-custom-archive-sections'),
					'logged_out' => __('Logged out', 'kayce-custom-archive-sections'),
				);
				echo esc_html(isset($labels[$visibility]) ? $labels[$visibility] : __('Everyone', 'kayce-custom-archive-sections'));
				break;
		}
	}

	// =========================================================================
	// Duplicate / Clone
	// =========================================================================

	/**
	 * Add a "Duplicate" row action to the list table.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_row_actions($actions, $post)
	{
		if ($post->post_type !== $this->post_type) {
			return $actions;
		}

		if (! current_user_can('edit_posts')) {
			return $actions;
		}

		$url = add_query_arg(
			array(
				'action' => 'kcas_duplicate_section',
				'post'   => $post->ID,
				'nonce'  => wp_create_nonce('kcas_duplicate_' . $post->ID),
			),
			admin_url('admin-post.php')
		);

		$actions['kcas_duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url($url),
			esc_html__('Duplicate', 'kayce-custom-archive-sections')
		);

		return $actions;
	}

	/**
	 * Handle the duplicate section request.
	 *
	 * Creates a draft copy of the section post and all its meta,
	 * then redirects to the new post's edit screen.
	 */
	public function handle_duplicate()
	{
		// Validate presence of required params.
		if (! isset($_GET['post'], $_GET['nonce'])) {
			wp_die(esc_html__('Invalid request.', 'kayce-custom-archive-sections'));
		}

		$original_id = (int) $_GET['post'];

		// Verify nonce.
		if (! wp_verify_nonce(sanitize_key($_GET['nonce']), 'kcas_duplicate_' . $original_id)) {
			wp_die(esc_html__('Security check failed.', 'kayce-custom-archive-sections'));
		}

		// Capability check.
		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('You do not have permission to do this.', 'kayce-custom-archive-sections'));
		}

		$original = get_post($original_id);

		if (! $original || $original->post_type !== $this->post_type) {
			wp_die(esc_html__('Invalid section post.', 'kayce-custom-archive-sections'));
		}

		// Insert the duplicate as a draft.
		$new_id = wp_insert_post(
			array(
				'post_title'   => sprintf(
					/* translators: %s: original post title */
					__('Copy of %s', 'kayce-custom-archive-sections'),
					$original->post_title
				),
				'post_content' => $original->post_content,
				'post_status'  => 'draft',
				'post_type'    => $this->post_type,
				'menu_order'   => $original->menu_order,
			),
			true
		);

		if (is_wp_error($new_id)) {
			wp_die(esc_html($new_id->get_error_message()));
		}

		// Copy all KCAS meta fields.
		$meta_keys = array(
			$this->meta_location,
			$this->meta_position,
			$this->meta_active,
			$this->meta_visibility,
			$this->meta_categories,
			'_kcas_pages',
			'_kcas_cpt',
			'_kcas_date_from',
			'_kcas_date_to',
			'_kcas_devices',
			'_kcas_roles',
		);

		foreach ($meta_keys as $key) {
			$value = get_post_meta($original_id, $key, true);
			if ('' !== $value) {
				update_post_meta($new_id, $key, $value);
			}
		}

		// Send to the new draft's edit screen.
		wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_id));
		exit;
	}

	// =========================================================================
	// Drag-drop reorder (v1.4.0)
	// =========================================================================

	/**
	 * AJAX handler: persist the new section order sent by the drag-drop JS.
	 *
	 * Expects POST fields:
	 *   nonce  — 'kcas_reorder' nonce.
	 *   order  — indexed array of post IDs in the desired display order.
	 *
	 * Each post's menu_order is set to its 0-based position in the array.
	 * Non-KCAS posts are silently ignored.
	 */
	public function handle_reorder()
	{
		check_ajax_referer('kcas_reorder', 'nonce');

		if (! current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'kayce-custom-archive-sections')));
		}

		$order = isset($_POST['order']) ? (array) $_POST['order'] : array();

		if (empty($order)) {
			wp_send_json_error(array('message' => __('No order data received.', 'kayce-custom-archive-sections')));
		}

		foreach ($order as $position => $raw_id) {
			$post_id = absint($raw_id);
			if (! $post_id) {
				continue;
			}
			// Guard: only update our own CPT posts.
			if (get_post_type($post_id) !== $this->post_type) {
				continue;
			}
			wp_update_post(array(
				'ID'         => $post_id,
				'menu_order' => (int) $position,
			));
		}

		wp_send_json_success();
	}

	// =========================================================================
	// Quick Edit (#16)
	// =========================================================================

	/**
	 * Render the Quick Edit fields for our CPT.
	 * Only outputs HTML once — triggered by the 'kcas_active' column.
	 *
	 * @param string $column_name
	 * @param string $post_type
	 */
	public function render_quick_edit_fields($column_name, $post_type)
	{
		if ($post_type !== $this->post_type || 'kcas_active' !== $column_name) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right kcas-quick-edit-fields">
			<div class="inline-edit-col">
				<h4><?php esc_html_e('Archive Section Settings', 'kayce-custom-archive-sections'); ?></h4>
				<?php wp_nonce_field('kcas_quick_edit', 'kcas_quick_edit_nonce'); ?>

				<label class="kcas-qe-active-label">
					<input type="checkbox" name="kcas_active" value="1" />
					<span><?php esc_html_e('Active (display this section)', 'kayce-custom-archive-sections'); ?></span>
				</label>

				<label>
					<span class="title"><?php esc_html_e('Position', 'kayce-custom-archive-sections'); ?></span>
					<select name="kcas_position">
						<option value="before_content"><?php esc_html_e('After header', 'kayce-custom-archive-sections'); ?></option>
						<option value="before"><?php esc_html_e('Before posts', 'kayce-custom-archive-sections'); ?></option>
						<option value="after"><?php esc_html_e('After posts', 'kayce-custom-archive-sections'); ?></option>
					</select>
				</label>

				<label>
					<span class="title"><?php esc_html_e('Show to', 'kayce-custom-archive-sections'); ?></span>
					<select name="kcas_visibility">
						<option value="all"><?php esc_html_e('Everyone', 'kayce-custom-archive-sections'); ?></option>
						<option value="logged_in"><?php esc_html_e('Logged-in users only', 'kayce-custom-archive-sections'); ?></option>
						<option value="logged_out"><?php esc_html_e('Logged-out visitors only', 'kayce-custom-archive-sections'); ?></option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	// =========================================================================
	// Bulk Actions (#17)
	// =========================================================================

	/**
	 * Register "Activate" and "Deactivate" bulk actions.
	 *
	 * @param array $actions
	 * @return array
	 */
	public function add_bulk_actions($actions)
	{
		$actions['kcas_activate']   = __('Activate',   'kayce-custom-archive-sections');
		$actions['kcas_deactivate'] = __('Deactivate', 'kayce-custom-archive-sections');
		return $actions;
	}

	/**
	 * Handle the Activate / Deactivate bulk actions.
	 *
	 * @param string $redirect_to
	 * @param string $action
	 * @param int[]  $post_ids
	 * @return string
	 */
	public function handle_bulk_actions($redirect_to, $action, $post_ids)
	{
		if (! in_array($action, array('kcas_activate', 'kcas_deactivate'), true)) {
			return $redirect_to;
		}

		$value = ('kcas_activate' === $action) ? '1' : '0';
		$count = 0;

		foreach ($post_ids as $post_id) {
			if (! current_user_can('edit_post', (int) $post_id)) {
				continue;
			}
			if (get_post_type((int) $post_id) !== $this->post_type) {
				continue;
			}
			update_post_meta((int) $post_id, $this->meta_active, $value);
			$count++;
		}

		return add_query_arg(
			array(
				'kcas_bulk_action' => $action,
				'kcas_bulk_count'  => $count,
			),
			$redirect_to
		);
	}

	/**
	 * Show an admin notice after a bulk action completes.
	 */
	public function bulk_action_notice()
	{
		$screen = get_current_screen();
		if (! $screen || $screen->post_type !== $this->post_type) {
			return;
		}

		// These GET params are set by handle_bulk_actions() above; WordPress core
		// already verified the bulk-action nonce before triggering that callback.
		if (empty($_GET['kcas_bulk_action']) || ! isset($_GET['kcas_bulk_count'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_key($_GET['kcas_bulk_action']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count  = (int) $_GET['kcas_bulk_count']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ('kcas_activate' === $action) {
			$message = sprintf(
				/* translators: %d: number of sections activated */
				_n('%d section activated.', '%d sections activated.', $count, 'kayce-custom-archive-sections'),
				$count
			);
		} else {
			$message = sprintf(
				/* translators: %d: number of sections deactivated */
				_n('%d section deactivated.', '%d sections deactivated.', $count, 'kayce-custom-archive-sections'),
				$count
			);
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
	}

	// =========================================================================
	// Sortable Columns (#18)
	// =========================================================================

	/**
	 * Declare which custom columns are sortable.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function register_sortable_columns($columns)
	{
		$columns['kcas_active']     = 'kcas_active';
		$columns['kcas_location']   = 'kcas_location';
		$columns['kcas_position']   = 'kcas_position';
		$columns['kcas_visibility'] = 'kcas_visibility';
		return $columns;
	}

	/**
	 * Apply custom meta-based ordering when a sortable column is active.
	 *
	 * @param WP_Query $query
	 */
	public function handle_column_orderby($query)
	{
		if (! is_admin() || ! $query->is_main_query()) {
			return;
		}

		if ($query->get('post_type') !== $this->post_type) {
			return;
		}

		$orderby_map = array(
			'kcas_active'     => $this->meta_active,
			'kcas_location'   => $this->meta_location,
			'kcas_position'   => $this->meta_position,
			'kcas_visibility' => $this->meta_visibility,
		);

		$orderby = $query->get('orderby');

		// No explicit column sort → default to menu_order so drag-drop order is
		// immediately reflected when the user returns to the default list view.
		if (empty($orderby)) {
			$query->set('orderby', array('menu_order' => 'ASC', 'title' => 'ASC'));
			return;
		}

		if (isset($orderby_map[$orderby])) {
			$query->set('meta_key', $orderby_map[$orderby]);
			$query->set('orderby',  'meta_value');
		}
	}

	// =========================================================================
	// Empty State
	// =========================================================================

	/**
	 * Render a friendly empty-state card when no sections exist yet.
	 * Injected via admin_footer-edit.php and shown via JS only when the
	 * default "No posts found" row is present.
	 */
	public function render_empty_state()
	{
		$screen = get_current_screen();
		if (! $screen || $screen->post_type !== $this->post_type) {
			return;
		}

		$add_new_url = admin_url('post-new.php?post_type=' . $this->post_type);
		?>
		<style>
			#kcas-empty-state {
				display: none;
				text-align: center;
				padding: 48px 24px;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				margin-top: 12px;
			}
			#kcas-empty-state .kcas-empty-icon {
				font-size: 48px;
				line-height: 1;
				margin-bottom: 12px;
			}
			#kcas-empty-state h2 {
				font-size: 20px;
				font-weight: 600;
				margin: 0 0 8px;
				color: #1d2327;
			}
			#kcas-empty-state p {
				color: #646970;
				font-size: 14px;
				margin: 0 0 20px;
			}
		</style>
		<div id="kcas-empty-state">
			<div class="kcas-empty-icon">&#9741;</div>
			<h2><?php esc_html_e('No archive sections yet', 'kayce-custom-archive-sections'); ?></h2>
			<p><?php esc_html_e('Create your first section and choose where it should appear on your archive pages.', 'kayce-custom-archive-sections'); ?></p>
			<a href="<?php echo esc_url($add_new_url); ?>" class="button button-primary button-large">
				<?php esc_html_e('Create your first section', 'kayce-custom-archive-sections'); ?>
			</a>
		</div>
		<script>
			(function () {
				var noItems = document.querySelector('#the-list .no-items');
				var emptyState = document.getElementById('kcas-empty-state');
				if (noItems && emptyState) {
					noItems.closest('table').style.display = 'none';
					emptyState.style.display = 'block';
				}
			}());
		</script>
		<?php
	}
}
