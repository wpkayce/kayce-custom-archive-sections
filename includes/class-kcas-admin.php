<?php

if (! defined('ABSPATH')) {
    exit;
}

class KCAS_Admin
{

    protected $post_type;
    protected $meta_location;
    protected $meta_position;

    public function __construct($post_type, $meta_location, $meta_position)
    {

        $this->post_type     = $post_type;
        $this->meta_location = $meta_location;
        $this->meta_position = $meta_position;

        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . $this->post_type, [$this, 'save_archive_section_meta'], 10, 2);
    }

    public function register_meta_boxes()
    {

        add_meta_box(
            'kcas_display_settings',
            __('Archive Section Settings', 'kayce-custom-archive-sections'),
            [
                $this,
                'render_display_settings_metabox'
            ],
            $this->post_type,
            'side',
            'default'
        );
    }

    public function render_display_settings_metabox($post)
    {

        wp_nonce_field('kcas_save_display_settings', 'kcas_display_settings_nonce');

        $current_location = get_post_meta($post->ID, $this->meta_location, true);
        $current_position = get_post_meta($post->ID, $this->meta_position, true);

        // Default position if nothing saved yet (optional).
        if ($current_position === '') {
            $current_position = 'before';
        }
?>
        <style>
            .kcas-field-group {
                margin-bottom: 16px;
                padding: 12px;
                background: #f6f7f7;
                border: 1px solid #dcdcde;
                border-radius: 4px;
            }

            .kcas-field-title {
                font-weight: 600;
                margin: 0 0 4px;
            }

            .kcas-meta-help {
                margin: 0 0 8px;
                font-size: 12px;
                color: #555d66;
            }

            .kcas-inline-options label {
                display: block;
                margin: 3px 0;
                font-size: 13px;
            }
        </style>

        <div class="kcas-field-group">
            <p class="kcas-field-title">
                <?php esc_html_e('Where should this section appear?', 'kayce-custom-archive-sections'); ?>
            </p>
            <p class="kcas-meta-help">
                <?php esc_html_e('Choose which archive pages will display this section.', 'kayce-custom-archive-sections'); ?>
            </p>

            <div class="kcas-inline-options">
                <label>
                    <input type="radio" name="kcas_location" value="blog_index"
                        <?php checked($current_location, 'blog_index'); ?> />
                    <?php esc_html_e('Blog index (posts page)', 'kayce-custom-archive-sections'); ?>
                </label>

                <label>
                    <input type="radio" name="kcas_location" value="category_archives"
                        <?php checked($current_location, 'category_archives'); ?> />
                    <?php esc_html_e('All category archives', 'kayce-custom-archive-sections'); ?>
                </label>
                <lablel>
                    <input type="radio" name="kcas_location" value="single_post"
                        <?php checked($current_location, 'single_post'); ?> />
                    <?php esc_html_e('Single Post', 'kayce-custom-archive-sections'); ?>
                    </label>

                    <label>
                        <input type="radio" name="kcas_location" value="" <?php checked($current_location, ''); ?> />
                        <?php esc_html_e('Do not display on any archive (disabled)', 'kayce-custom-archive-sections'); ?>
                    </label>
            </div>
        </div>

        <div class="kcas-field-group">
            <p class="kcas-field-title">
                <?php esc_html_e('Position on archive page', 'kayce-custom-archive-sections'); ?>
            </p>
            <p class="kcas-meta-help">
                <?php esc_html_e('Should this section appear before or after the list of posts?', 'kayce-custom-archive-sections'); ?>
            </p>

            <div class="kcas-inline-options">
                <label>
                    <input type="radio" name="kcas_position" value="before" <?php checked($current_position, 'before'); ?> />
                    <?php esc_html_e('Before posts', 'kayce-custom-archive-sections'); ?>
                </label>

                <label>
                    <input type="radio" name="kcas_position" value="after" <?php checked($current_position, 'after'); ?> />
                    <?php esc_html_e('After posts', 'kayce-custom-archive-sections'); ?>
                </label>
            </div>
        </div>
<?php
    }



    public function save_archive_section_meta($post_id, $post)
    {

        // Check if our nonce is present.
        if (! isset($_POST['kcas_display_settings_nonce'])) {
            return;
        }

        // Verify nonce to ensure the request is valid.
        $nonce = sanitize_text_field(wp_unslash($_POST['kcas_display_settings_nonce']));
        if (! wp_verify_nonce($nonce, 'kcas_save_display_settings')) {
            return;
        }

        // Avoid autosaves and revisions.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ('revision' === $post->post_type) {
            return;
        }

        // Check user capability (can they edit this post?).
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Get values from $_POST and sanitize.
        $location = isset($_POST['kcas_location']) ? sanitize_text_field(wp_unslash($_POST['kcas_location'])) : '';
        $position = isset($_POST['kcas_position']) ? sanitize_text_field(wp_unslash($_POST['kcas_position'])) : '';

        // Whitelist allowed values
        $allowed_locations = ['', 'blog_index', 'category_archives', 'single_post'];
        $allowed_positions = ['', 'before', 'after'];

        if (! in_array($location, $allowed_locations, true)) {
            $location = '';
        }

        if (! in_array($position, $allowed_positions, true)) {
            $position = '';
        }

        // Save or delete meta.
        if ('' !== $location) {
            update_post_meta($post_id, $this->meta_location, $location);
        } else {
            delete_post_meta($post_id, $this->meta_location);
        }

        if ('' !== $position) {
            update_post_meta($post_id, $this->meta_position, $position);
        } else {
            delete_post_meta($post_id, $this->meta_position);
        }
    }
}
