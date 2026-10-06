<?php
/**
 * Graphina → wa-charts mapping.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts\Cli;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Chart_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Graphina Elementor widget settings into wa-charts configs. Has no side effects.
 */
final class Graphina_Mapper {

	public const SUPPORTED = array(
		'pie_chart'    => 'pie',
		'donut_chart'  => 'donut',
		'polar_chart'  => 'polar',
		'radial_chart' => 'radial',
	);

	/**
	 * Whether an Elementor element is a Graphina widget.
	 *
	 * @param array $element Elementor element.
	 * @return bool
	 */
	public static function is_graphina( array $element ): bool {
		$type = $element['widgetType'] ?? '';
		if ( ! is_string( $type ) || ! str_ends_with( $type, '_chart' ) ) {
			return false;
		}
		foreach ( array_keys( (array) ( $element['settings'] ?? array() ) ) as $key ) {
			if ( str_starts_with( (string) $key, 'iq_' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Maps a supported widget to a config.
	 *
	 * @param string $widget_type Graphina widget type, e.g. `pie_chart`.
	 * @param array  $settings    Widget settings.
	 * @return array|null Null when the type is not supported.
	 */
	public static function map( string $widget_type, array $settings ): ?array {
		if ( ! isset( self::SUPPORTED[ $widget_type ] ) ) {
			return null;
		}
		$type    = self::SUPPORTED[ $widget_type ];
		$prefix  = 'iq_' . $widget_type . '_';
		$flags   = array();
		$palette = Chart_Types::default_palette();

		$count = (int) ( $settings[ $prefix . 'data_series_count' ] ?? 0 );
		if ( $count <= 0 ) {
			$count   = self::guess_count( $settings, $prefix );
			$flags[] = sprintf( 'data_series_count missing; using %d', $count );
		}
		if ( 'radial' === $type && $count > 1 ) {
			$flags[] = 'the gauge keeps only the first value';
			$count   = 1;
		}

		$labels   = array();
		$values   = array();
		$colors   = array();
		$decimals = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$label    = (string) ( $settings[ $prefix . 'label' . $i ] ?? '' );
			$labels[] = $label;

			$raw = $settings[ $prefix . 'value' . $i ] ?? null;
			if ( is_numeric( $raw ) ) {
				$values[] = (float) $raw;
				$decimals = max( $decimals, self::decimals( (string) $raw ) );
			} else {
				$values[] = 0.0;
				$flags[]  = sprintf( 'value %d (%s) missing, set to 0', $i, $label );
			}

			$color = $settings[ $prefix . 'gradient_1_' . $i ] ?? '';
			if ( is_string( $color ) && ( sanitize_hex_color( $color ) || preg_match( '/^#[0-9a-fA-F]{8}\z/', $color ) ) ) {
				$colors[] = $color;
			} else {
				$colors[] = $palette[ $i % count( $palette ) ];
				$flags[]  = sprintf( 'colour %d (%s) missing, palette colour used', $i, $label );
			}
		}

		$css    = (string) ( $settings['custom_css'] ?? '' );
		$suffix = '';
		if ( preg_match( '/legend-text[^{]*::after\s*\{[^}]*content:\s*"([^"]*)"/i', $css, $match ) ) {
			$suffix  = $match[1];
			$flags[] = sprintf( 'value suffix "%s" taken from custom CSS', $suffix );
		}
		$columns = 1;
		if ( preg_match( '/apexcharts-legend-series\s*\{[^}]*width:\s*4\d%/i', $css ) ) {
			$columns = 2;
			$flags[] = 'legend columns = 2 taken from custom CSS';
		}

		$heading = (string) ( $settings[ $prefix . 'heading' ] ?? '' );
		$result  = Chart_Data::sanitize(
			array(
				'type'         => $type,
				'labels'       => $labels,
				'series'       => array( array( 'values' => $values ) ),
				'point_colors' => $colors,
				'display'      => array(
					'heading'     => $heading,
					'subheading'  => (string) ( $settings[ $prefix . 'content' ] ?? '' ),
					'layout'      => 'left',
					'legend'      => array(
						'position'    => 'bottom',
						'columns'     => $columns,
						'show_values' => 'yes' === ( $settings[ $prefix . 'legend_show_series_value' ] ?? '' ),
					),
					'value'       => array(
						'prefix'   => '',
						'suffix'   => $suffix,
						'decimals' => min( 6, $decimals ),
						'locale'   => 'en-US',
					),
					'font_family' => (string) ( $settings[ $prefix . 'font_family' ] ?? '' ),
				),
			)
		);

		$title = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) preg_replace( '/<br\s*\/?>/i', ' ', $heading ) ) ) );

		return array(
			'config'     => $result['config'],
			'title'      => '' !== $title ? $title : 'Graphina chart',
			'flags'      => array_merge( $flags, $result['errors'] ),
			'custom_css' => $css,
		);
	}

	/**
	 * Replaces supported Graphina widgets in an Elementor tree with shortcode widgets.
	 *
	 * @param array    $elements Elementor elements.
	 * @param callable $create   Creates the chart: fn( array $config, string $title ): int.
	 * @param array    $report   Collected report items.
	 * @return array
	 */
	public static function convert_tree( array $elements, callable $create, array &$report ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( self::is_graphina( $element ) ) {
				$mapped = self::map( (string) $element['widgetType'], (array) $element['settings'] );
				if ( null === $mapped ) {
					$report[] = array(
						'status'   => 'manual',
						'widget'   => $element['widgetType'],
						'id'       => $element['id'] ?? '',
						'settings' => $element['settings'],
					);
					continue;
				}
				$chart_id           = (int) $create( $mapped['config'], $mapped['title'] );
				$elements[ $index ] = self::shortcode_element( $element, $chart_id );
				$report[]           = array(
					'status'     => 'converted',
					'widget'     => $element['widgetType'],
					'id'         => $element['id'] ?? '',
					'chart_id'   => $chart_id,
					'title'      => $mapped['title'],
					'flags'      => $mapped['flags'],
					'custom_css' => $mapped['custom_css'],
				);
				continue;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$elements[ $index ]['elements'] = self::convert_tree( $element['elements'], $create, $report );
			}
		}
		return $elements;
	}

	/**
	 * Elementor Shortcode widget that keeps the original's non-Graphina settings.
	 *
	 * @param array $element  Original widget.
	 * @param int   $chart_id New chart ID.
	 * @return array
	 */
	public static function shortcode_element( array $element, int $chart_id ): array {
		$settings = array();
		foreach ( (array) ( $element['settings'] ?? array() ) as $key => $value ) {
			$key = (string) $key;
			if ( str_starts_with( $key, 'iq_' ) || 'custom_css' === $key ) {
				continue;
			}
			if ( '__globals__' === $key && is_array( $value ) ) {
				$value = array_filter( $value, static fn( $global_key ) => ! str_starts_with( (string) $global_key, 'iq_' ), ARRAY_FILTER_USE_KEY );
			}
			$settings[ $key ] = $value;
		}
		$settings['shortcode'] = sprintf( '[wa_chart id="%d"]', $chart_id );

		$element['widgetType'] = 'shortcode';
		$element['settings']   = $settings;
		$element['elements']   = array();
		return $element;
	}

	/**
	 * Highest valueN index + 1.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $prefix   Key prefix.
	 * @return int
	 */
	private static function guess_count( array $settings, string $prefix ): int {
		$max = -1;
		foreach ( array_keys( $settings ) as $key ) {
			if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . 'value(\d+)$/', (string) $key, $match ) ) {
				$max = max( $max, (int) $match[1] );
			}
		}
		return $max + 1;
	}

	/**
	 * Number of decimals in a numeric string.
	 *
	 * @param string $number Number.
	 * @return int
	 */
	private static function decimals( string $number ): int {
		$dot = strpos( $number, '.' );
		return false === $dot ? 0 : strlen( rtrim( substr( $number, $dot + 1 ), '0' ) );
	}
}
