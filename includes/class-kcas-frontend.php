 <?php

    if (! defined('ABSPATH')) {
        exit; // Exit if accessed directly.
    }

    class KCAS_Frontend
    {

        protected $post_type;
        protected $meta_location;
        protected $meta_position;

        public function __construct($post_type, $meta_location, $meta_position)
        {
            $this->post_type = $post_type;
            $this->meta_location = $meta_location;
            $this->meta_position = $meta_position;
            if (!is_admin()) {
                add_action('loop_start', [$this, 'maybe_output_archive_sections_before']);
                add_action('loop_end', [$this, 'maybe_output_archive_sections_after']);
            }
        }

        public function maybe_output_archive_sections_before($query)
        {
            if (!$query->is_main_query() || is_admin()) {
                return;
            }

            if (is_home()) {
                $this->output_sections_for('blog_index', 'before');
            }
            if (is_category()) {
                $this->output_sections_for('category_archives', 'before');
            }
        }
        public function maybe_output_archive_sections_after($query)
        {

            // Only affect the main query on the frontend.
            if (! $query->is_main_query() || is_admin()) {
                return;
            }

            // Blog index (posts page).
            if (is_home()) {
                $this->output_sections_for('blog_index', 'after');
            }

            // Category archive pages.
            if (is_category()) {
                $this->output_sections_for('category_archives', 'after');
            }
        }

        public function output_sections_for($location, $position)
        {
            $allowed_locations = ['blog_index', 'category_archives'];
            $allowed_positions = ['before', 'after'];

            if (!in_array($location, $allowed_locations, true)) {
                return;
            }

            if (!in_array($position, $allowed_positions, true)) {
                return;
            }
            //Query all matching archive sections. 
            $sections_query = new WP_Query(
                [
                    'post_type' => $this->post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'orderby' => 'menu_order title',
                    'order' => 'ASC',
                    'no_found_rows' => true,
                    'meta_query' => [
                        [
                            'key' => $this->meta_location,
                            'value' => $location,
                        ],
                        [
                            'key' => $this->meta_position,
                            'value' => $position,
                        ],
                    ],
                ]
            );
            if (!$sections_query->have_posts()) {
                return;
            }
            echo '<div class="kcas-archive-sections kcas-archive-sections--' . esc_attr($position) . '">';
            while ($sections_query->have_posts()) {
                $sections_query->the_post();
                echo '<section class="kcas-archive-section" id="kcas-section-' . esc_attr(get_the_ID()) . '">';

                the_content();
                echo '</section>';
            }
            echo '</div>';
            wp_reset_postdata();
        }
    }
