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
	);

	/** @var string[] */
	private static $allowed_positions = array('before', 'after');

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

		// Empty state.
		add_action('admin_footer-edit.php', array($this, 'render_empty_state'));
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
			array(),
			KCAS_PLUGIN_VERSION,
			true
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

		$all_cats = get_categories(array('hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
?>

		<?php /* ── Active toggle ── */ ?>
		<div class="kcas-field-group">
			<label class="kcas-active-label">
				<input type="checkbox" name="kcas_active" value="1" <?php checked($active, '1'); ?> />
				<strong><?php esc_html_e('Active — display this section', 'kayce-custom-archive-sections'); ?></strong>
			</label>
			<p class="kcas-meta-help">
				<?php esc_html_e('Uncheck to hide this section without deleting it.', 'kayce-custom-archive-sections'); ?>
			</p>
		</div>

		<?php /* ── Location ── */ ?>
		<div class="kcas-field-group">
			<p class="kcas-field-title">
				<?php esc_html_e('Where should this section appear?', 'kayce-custom-archive-sections'); ?></p>
			<p class="kcas-meta-help">
				<?php esc_html_e('Choose the archive pages that will display this section.', 'kayce-custom-archive-sections'); ?>
			</p>

			<div class="kcas-inline-options" id="kcas-location-options">
				<?php
				$location_options = array(
					''                    => __('Disabled (do not display)', 'kayce-custom-archive-sections'),
					'blog_index'          => __('Blog index (posts page)', 'kayce-custom-archive-sections'),
					'category_archives'   => __('All category archives', 'kayce-custom-archive-sections'),
					'specific_categories' => __('Specific categories only', 'kayce-custom-archive-sections'),
					'single_post' 		  => __('Single Posts', 'kayce-custom-archive-sections'),
					'tag_archives'        => __('Tag archives', 'kayce-custom-archive-sections'),
					'author_archives'     => __('Author archives', 'kayce-custom-archive-sections'),
					'search_results'      => __('Search results page', 'kayce-custom-archive-sections'),
					'date_archives'       => __('Date archives (day/month/year)', 'kayce-custom-archive-sections'),
				);

				foreach ($location_options as $value => $label) :
				?>
					<label>
						<input type="radio" name="kcas_location" value="<?php echo esc_attr($value); ?>"
							<?php checked($location, $value); ?> />
						<?php echo esc_html($label); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<?php /* ── Specific Categories (shown / hidden via JS) ── */ ?>
		<div class="kcas-field-group" id="kcas-categories-row"
			style="<?php echo ('specific_categories' !== $location) ? 'display:none;' : ''; ?>">
			<p class="kcas-field-title"><?php esc_html_e('Choose categories', 'kayce-custom-archive-sections'); ?></p>
			<p class="kcas-meta-help">
				<?php esc_html_e('The section will appear on the archive pages for the selected categories only.', 'kayce-custom-archive-sections'); ?>
			</p>

			<?php if (! empty($all_cats)) : ?>
				<div class="kcas-category-list">
					<?php foreach ($all_cats as $cat) : ?>
						<label>
							<input type="checkbox" name="kcas_categories[]" value="<?php echo esc_attr($cat->term_id); ?>"
								<?php checked(in_array((int) $cat->term_id, array_map('intval', $categories), true)); ?> />
							<?php echo esc_html($cat->name); ?>
							<span class="kcas-cat-count">(<?php echo (int) $cat->count; ?>)</span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="kcas-meta-help"><?php esc_html_e('No categories found.', 'kayce-custom-archive-sections'); ?></p>
			<?php endif; ?>
		</div>

		<?php /* ── Position ── */ ?>
		<div class="kcas-field-group">
			<p class="kcas-field-title"><?php esc_html_e('Position on archive page', 'kayce-custom-archive-sections'); ?></p>
			<p class="kcas-meta-help">
				<?php esc_html_e('Should this section appear before or after the list of posts?', 'kayce-custom-archive-sections'); ?>
			</p>

			<div class="kcas-inline-options">
				<label>
					<input type="radio" name="kcas_position" value="before" <?php checked($position, 'before'); ?> />
					<?php esc_html_e('Before posts', 'kayce-custom-archive-sections'); ?>
				</label>
				<label>
					<input type="radio" name="kcas_position" value="after" <?php checked($position, 'after'); ?> />
					<?php esc_html_e('After posts', 'kayce-custom-archive-sections'); ?>
				</label>
			</div>
		</div>

		<?php /* ── Visibility ── */ ?>
		<div class="kcas-field-group">
			<p class="kcas-field-title"><?php esc_html_e('Show to', 'kayce-custom-archive-sections'); ?></p>
			<p class="kcas-meta-help">
				<?php esc_html_e('Control which visitors see this section.', 'kayce-custom-archive-sections'); ?></p>

			<div class="kcas-inline-options">
				<label>
					<input type="radio" name="kcas_visibility" value="all" <?php checked($visibility, 'all'); ?> />
					<?php esc_html_e('Everyone', 'kayce-custom-archive-sections'); ?>
				</label>
				<label>
					<input type="radio" name="kcas_visibility" value="logged_in" <?php checked($visibility, 'logged_in'); ?> />
					<?php esc_html_e('Logged-in users only', 'kayce-custom-archive-sections'); ?>
				</label>
				<label>
					<input type="radio" name="kcas_visibility" value="logged_out"
						<?php checked($visibility, 'logged_out'); ?> />
					<?php esc_html_e('Logged-out visitors only', 'kayce-custom-archive-sections'); ?>
				</label>
			</div>
		</div>

		<?php /* ── Preview link ── */ ?>
		<?php $preview_url = $this->get_preview_url($location, $categories); ?>
		<?php if ($preview_url) : ?>
			<div class="kcas-field-group kcas-preview-group">
				<p class="kcas-field-title"><?php esc_html_e('Preview', 'kayce-custom-archive-sections'); ?></p>
				<a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="button button-small">
					<?php esc_html_e('View archive page →', 'kayce-custom-archive-sections'); ?>
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
		switch ($location) {
			case 'blog_index':
				$page_id = (int) get_option('page_for_posts');
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
				return !empty($posts) ? get_permalink($posts[0]->ID) : null;

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
		if (! isset($_POST['kcas_meta_nonce'])) {
			return;
		}

		if (! wp_verify_nonce(sanitize_key($_POST['kcas_meta_nonce']), 'kcas_save_meta')) {
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
		if ('specific_categories' === $location && ! empty($_POST['kcas_categories'])) {
			// Sanitise: each value must be a positive integer (term ID).
			$raw_cats = array_map('absint', (array) $_POST['kcas_categories']);
			$cats     = array_filter($raw_cats); // remove zeros
			update_post_meta($post_id, $this->meta_categories, array_values($cats));
		} else {
			delete_post_meta($post_id, $this->meta_categories);
		}

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

			case 'kcas_active':
				$active = get_post_meta($post_id, $this->meta_active, true);
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
					'category_archives'   => esc_html__('All categories', 'kayce-custom-archive-sections'),
					'specific_categories' => esc_html__('Specific categories', 'kayce-custom-archive-sections'),
					'single_post' 		  => esc_html__('Single posts', 'kayce-custom-archive-sections'),
					'tag_archives'        => esc_html__('Tags', 'kayce-custom-archive-sections'),
					'author_archives'     => esc_html__('Authors', 'kayce-custom-archive-sections'),
					'search_results'      => esc_html__('Search results', 'kayce-custom-archive-sections'),
					'date_archives'       => esc_html__('Date archives', 'kayce-custom-archive-sections'),
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
				break;

			case 'kcas_position':
				$position = get_post_meta($post_id, $this->meta_position, true);
				if ('after' === $position) {
					esc_html_e('After posts', 'kayce-custom-archive-sections');
				} else {
					esc_html_e('Before posts', 'kayce-custom-archive-sections');
				}
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
