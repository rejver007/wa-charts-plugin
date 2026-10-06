<?php
/**
 * [wa_chart] shortcode.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the chart shortcode.
 */
final class Shortcode {

	public const TAG = 'wa_chart';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Registers the shortcode.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::TAG, array( self::class, 'handle' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param mixed $atts Shortcode attributes.
	 * @return string
	 */
	public static function handle( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'height' => '',
				'layout' => '',
				'legend' => '',
				'class'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);
		return Renderer::output(
			absint( $atts['id'] ),
			array(
				'height' => $atts['height'],
				'layout' => $atts['layout'],
				'legend' => $atts['legend'],
				'class'  => $atts['class'],
			)
		);
	}
}
