<?php
/**
 * Chart block.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `webaula/chart`, rendered on the server with the shortcode renderer.
 */
final class Block {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Registers the block from the built metadata.
	 *
	 * @return void
	 */
	public static function register(): void {
		$dir = WA_CHARTS_DIR . 'build/blocks/chart';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $dir, array( 'render_callback' => array( self::class, 'render' ) ) );
		wp_set_script_translations( 'webaula-chart-editor-script', 'wa-charts', WA_CHARTS_DIR . 'languages' );
	}

	/**
	 * Server-side render.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$chart_id = isset( $attributes['chartId'] ) ? absint( $attributes['chartId'] ) : 0;
		if ( ! $chart_id ) {
			return '';
		}
		$classes = array();
		if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}
		if ( ! empty( $attributes['align'] ) && is_string( $attributes['align'] ) ) {
			$classes[] = 'align' . $attributes['align'];
		}
		return Renderer::output(
			$chart_id,
			array(
				'height' => $attributes['height'] ?? '',
				'layout' => $attributes['layout'] ?? '',
				'legend' => $attributes['legend'] ?? '',
				'class'  => implode( ' ', $classes ),
			)
		);
	}
}
