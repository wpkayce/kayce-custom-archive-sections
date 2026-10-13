<?php

/**
 * Gutenberg block for embedding an archive section.
 *
 * The plugin has always shipped a [kcas_section] shortcode, but a shortcode
 * cannot be placed in a Full Site Editing template and gives no preview while
 * editing. This block covers both, and deliberately renders through the same
 * method the shortcode uses so the two can never drift apart.
 *
 * @package Kayce_Custom_Archive_Sections
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class KCAS_Block
 */
class KCAS_Block
{

	const BLOCK_NAME = 'kayce/archive-section';

	/** @var KCAS_Frontend Renderer shared with the shortcode. */
	private $frontend;

	/** @var string Section post type slug. */
	private $post_type;

	/**
	 * @param KCAS_Frontend $frontend  The frontend instance that does the rendering.
	 * @param string        $post_type Section post type slug.
	 */
	public function __construct($frontend, $post_type)
	{
		$this->frontend  = $frontend;
		$this->post_type = $post_type;

		add_action('init', array($this, 'register_block'));
	}

	/**
	 * Register the block type and its editor script.
	 */
	public function register_block()
	{
		if (! function_exists('register_block_type')) {
			return;
		}

		wp_register_script(
			'kcas-block',
			KCAS_PLUGIN_URL . 'assets/js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-data',
				'wp-i18n',
			),
			KCAS_PLUGIN_VERSION,
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('kcas-block', 'kayce-custom-archive-sections');
		}

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 3,
				'title'           => __('Archive Section', 'kayce-custom-archive-sections'),
				'description'     => __('Embed one of your reusable archive sections.', 'kayce-custom-archive-sections'),
				'category'        => 'widgets',
				'icon'            => 'layout',
				'editor_script'   => 'kcas-block',
				'attributes'      => array(
					'sectionId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
				'supports'        => array(
					'html'   => false,
					'anchor' => true,
				),
				'render_callback' => array($this, 'render'),
			)
		);
	}

	/**
	 * Server-side render.
	 *
	 * Delegates to the shortcode renderer, which already applies the active
	 * flag, login-state visibility, the schedule window, role targeting and the
	 * device classes. Reusing it means a section embedded with the block behaves
	 * identically to one embedded with the shortcode.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render($attributes)
	{
		$section_id = isset($attributes['sectionId']) ? absint($attributes['sectionId']) : 0;

		if (! $section_id) {
			return '';
		}

		return $this->frontend->render_shortcode(array('id' => $section_id));
	}
}
