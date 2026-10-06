<?php
/**
 * Chart type registry.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-data registry of supported chart types. Read by the sanitizer, renderer and admin UI.
 */
final class Chart_Types {

	public const SHAPE_SINGLE = 'single';
	public const SHAPE_MULTI  = 'multi';

	/**
	 * All chart types after the `wa_charts_chart_types` filter.
	 *
	 * The filter may only remove types or change labels: new types also need JS support.
	 * Pie is the fallback type and is always kept (it can still be relabelled).
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		$builtin  = self::builtin();
		$filtered = apply_filters( 'wa_charts_chart_types', $builtin );
		if ( ! is_array( $filtered ) ) {
			return $builtin;
		}
		$types = array();
		foreach ( $filtered as $id => $definition ) {
			if ( ! isset( $builtin[ $id ] ) ) {
				continue;
			}
			$types[ $id ] = $builtin[ $id ];
			if ( is_array( $definition ) && isset( $definition['label'] ) && is_string( $definition['label'] ) ) {
				$types[ $id ]['label'] = $definition['label'];
			}
		}
		if ( ! isset( $types['pie'] ) ) {
			$types = array( 'pie' => $builtin['pie'] ) + $types;
		}
		return $types;
	}

	/**
	 * Type IDs in display order.
	 *
	 * @return string[]
	 */
	public static function ids(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether a type exists.
	 *
	 * @param string $id Type ID.
	 * @return bool
	 */
	public static function exists( string $id ): bool {
		return isset( self::all()[ $id ] );
	}

	/**
	 * One type definition.
	 *
	 * @param string $id Type ID.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Data shape of a type; unknown types count as single.
	 *
	 * @param string $id Type ID.
	 * @return string
	 */
	public static function shape( string $id ): string {
		$type = self::get( $id );
		return $type ? $type['shape'] : self::SHAPE_SINGLE;
	}

	/**
	 * Default colour palette, filterable with `wa_charts_default_palette`.
	 *
	 * @return string[]
	 */
	public static function default_palette(): array {
		$palette = apply_filters(
			'wa_charts_default_palette',
			array( '#1A98D1', '#08588C', '#113C56', '#A1BF23', '#D3E0E6', '#4B4C4B', '#2E2E2E', '#7A7A7A', '#F2A93B', '#D9534F', '#5CB85C', '#8E6CC4' )
		);
		$clean   = array_values( array_filter( array_map( 'sanitize_hex_color', is_array( $palette ) ? $palette : array() ) ) );
		return $clean ? $clean : array( '#1A98D1' );
	}

	/**
	 * Built-in definitions.
	 *
	 * @return array<string, array>
	 */
	private static function builtin(): array {
		$start_angle = array(
			'type'    => 'int',
			'label'   => __( 'Start angle (°)', 'wa-charts' ),
			'min'     => -360,
			'max'     => 360,
			'default' => 0,
		);
		$bar_width   = array(
			'type'    => 'int',
			'label'   => __( 'Bar width (%)', 'wa-charts' ),
			'min'     => 10,
			'max'     => 100,
			'default' => 70,
		);
		$curve       = array(
			'type'    => 'enum',
			'label'   => __( 'Line curve', 'wa-charts' ),
			'choices' => array(
				'straight' => __( 'Straight', 'wa-charts' ),
				'smooth'   => __( 'Smooth', 'wa-charts' ),
			),
			'default' => 'smooth',
		);
		$markers     = array(
			'type'    => 'bool',
			'label'   => __( 'Show markers', 'wa-charts' ),
			'default' => true,
		);

		return array(
			'pie'            => self::def( __( 'Pie', 'wa-charts' ), self::SHAPE_SINGLE, 'chart-pie', array( 'start_angle' => $start_angle ) ),
			'donut'          => self::def(
				__( 'Donut', 'wa-charts' ),
				self::SHAPE_SINGLE,
				'marker',
				array(
					'hole_size'   => array(
						'type'    => 'int',
						'label'   => __( 'Hole size (%)', 'wa-charts' ),
						'min'     => 30,
						'max'     => 90,
						'default' => 65,
					),
					'start_angle' => $start_angle,
				)
			),
			'polar'          => self::def( __( 'Polar area', 'wa-charts' ), self::SHAPE_SINGLE, 'sos', array() ),
			'radial'         => self::def(
				__( 'Gauge', 'wa-charts' ),
				self::SHAPE_SINGLE,
				'dashboard',
				array(
					'max'         => array(
						'type'    => 'float',
						'label'   => __( 'Maximum value', 'wa-charts' ),
						'min'     => 0.000001,
						'max'     => 1000000000000,
						'default' => 100,
					),
					'track_color' => array(
						'type'    => 'color',
						'label'   => __( 'Track colour', 'wa-charts' ),
						'default' => '#E5E7EB',
					),
				),
				1
			),
			'bar'            => self::def( __( 'Bar', 'wa-charts' ), self::SHAPE_MULTI, 'chart-bar', array( 'bar_width' => $bar_width ) ),
			'bar-horizontal' => self::def( __( 'Horizontal bar', 'wa-charts' ), self::SHAPE_MULTI, 'align-left', array( 'bar_width' => $bar_width ) ),
			'stacked'        => self::def(
				__( 'Stacked bar', 'wa-charts' ),
				self::SHAPE_MULTI,
				'database',
				array(
					'bar_width' => $bar_width,
					'stack_100' => array(
						'type'    => 'bool',
						'label'   => __( 'Stack to 100 %', 'wa-charts' ),
						'default' => false,
					),
				)
			),
			'line'           => self::def(
				__( 'Line', 'wa-charts' ),
				self::SHAPE_MULTI,
				'chart-line',
				array(
					'curve'   => $curve,
					'markers' => $markers,
				)
			),
			'area'           => self::def(
				__( 'Area', 'wa-charts' ),
				self::SHAPE_MULTI,
				'chart-area',
				array(
					'curve'   => $curve,
					'markers' => $markers,
				)
			),
			'mixed'          => self::def(
				__( 'Bar + line', 'wa-charts' ),
				self::SHAPE_MULTI,
				'analytics',
				array(
					'curve'     => $curve,
					'markers'   => $markers,
					'bar_width' => $bar_width,
				)
			),
			'radar'          => self::def( __( 'Radar', 'wa-charts' ), self::SHAPE_MULTI, 'star-empty', array( 'curve' => $curve ) ),
		);
	}

	/**
	 * Builds one definition.
	 *
	 * @param string $label    Human label.
	 * @param string $shape    Data shape.
	 * @param string $icon     Dashicon name without the `dashicons-` prefix.
	 * @param array  $options  Type-specific option specs.
	 * @param int    $max_rows Rows the chart displays (the rest are kept in the data but not drawn).
	 * @return array
	 */
	private static function def( string $label, string $shape, string $icon, array $options, int $max_rows = 500 ): array {
		return array(
			'label'    => $label,
			'shape'    => $shape,
			'icon'     => $icon,
			'max_rows' => $max_rows,
			'options'  => $options,
		);
	}
}
