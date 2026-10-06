<?php
/**
 * Chart data format.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * The only code that knows how chart configs are stored and validated.
 */
final class Chart_Data {

	public const META_KEY         = '_wa_chart_config';
	public const SCHEMA           = 1;
	public const MAX_LABELS       = 500;
	public const MAX_SERIES       = 20;
	public const MAX_TEXT         = 200;
	public const MAX_AFFIX        = 50;
	public const MAX_HEADING      = 500;
	public const MAX_BYTES        = 102400;
	public const LAYOUTS          = array( 'left', 'right', 'top' );
	public const LEGEND_POSITIONS = array( 'bottom', 'right', 'none' );
	public const LOCALES          = array( 'fi-FI', 'en-US', 'sv-SE', 'de-DE' );
	public const HEADING_TAGS     = array(
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'span'   => array(),
	);

	/**
	 * Schema migrations: target schema => method name. Empty until schema 2 exists.
	 */
	private const MIGRATIONS = array();

	/**
	 * Default config for a type.
	 *
	 * @param string $type Chart type.
	 * @return array
	 */
	public static function defaults( string $type = 'pie' ): array {
		if ( ! Chart_Types::exists( $type ) ) {
			$type = 'pie';
		}
		return array(
			'schema'       => self::SCHEMA,
			'type'         => $type,
			'labels'       => array(),
			'series'       => array(
				array(
					'name'   => '',
					'color'  => null,
					'render' => null,
					'values' => array(),
				),
			),
			'point_colors' => array(),
			'display'      => self::default_display(),
			'type_options' => self::default_type_options( $type ),
		);
	}

	/**
	 * Default display settings.
	 *
	 * @return array
	 */
	public static function default_display(): array {
		return array(
			'heading'     => '',
			'subheading'  => '',
			'layout'      => 'left',
			'height'      => 320,
			'legend'      => array(
				'position'    => 'bottom',
				'columns'     => 1,
				'show_values' => true,
			),
			'value'       => array(
				'prefix'   => '',
				'suffix'   => '',
				'decimals' => 2,
				'locale'   => 'fi-FI',
			),
			'data_labels' => false,
			'tooltips'    => true,
			'animation'   => true,
			'font_family' => '',
		);
	}

	/**
	 * Default type options for a type.
	 *
	 * @param string $type Chart type.
	 * @return array
	 */
	public static function default_type_options( string $type ): array {
		$definition = Chart_Types::get( $type );
		$out        = array();
		foreach ( $definition ? $definition['options'] : array() as $key => $spec ) {
			$out[ $key ] = $spec['default'];
		}
		return $out;
	}

	/**
	 * Sanitizes untrusted input into a valid config.
	 *
	 * @param mixed $raw Decoded JSON or form input.
	 * @return array{config: array, errors: string[]}
	 */
	public static function sanitize( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array(
				'config' => self::defaults(),
				'errors' => array( __( 'Chart data was not readable and has been reset.', 'wa-charts' ) ),
			);
		}

		$errors = array();
		$type   = isset( $raw['type'] ) && is_string( $raw['type'] ) ? $raw['type'] : '';
		if ( ! Chart_Types::exists( $type ) ) {
			/* translators: %s: the rejected chart type. */
			$errors[] = sprintf( __( 'Unknown chart type "%s"; Pie is used instead.', 'wa-charts' ), sanitize_text_field( $type ) );
			$type     = 'pie';
		}

		$raw     = self::drop_empty_rows( $raw );
		$labels  = self::sanitize_labels( $raw['labels'] ?? array(), $errors );
		$series  = self::sanitize_series( $raw['series'] ?? array(), count( $labels ), $type, $errors );
		$palette = Chart_Types::default_palette();
		$colors  = array();

		// Nothing is dropped when the type changes: the type's shape and max_rows only decide what is displayed.
		$raw_colors = isset( $raw['point_colors'] ) && is_array( $raw['point_colors'] ) ? array_values( $raw['point_colors'] ) : array();
		foreach ( array_keys( $labels ) as $i ) {
			$color    = self::color( $raw_colors[ $i ] ?? null, $errors );
			$colors[] = $color ?? $palette[ $i % count( $palette ) ];
		}
		if ( Chart_Types::SHAPE_MULTI === Chart_Types::shape( $type ) ) {
			foreach ( $series as $n => $item ) {
				if ( null === $item['color'] ) {
					$series[ $n ]['color'] = $palette[ $n % count( $palette ) ];
				}
			}
		}

		return array(
			'config' => array(
				'schema'       => self::SCHEMA,
				'type'         => $type,
				'labels'       => $labels,
				'series'       => $series,
				'point_colors' => $colors,
				'display'      => self::sanitize_display( $raw['display'] ?? array() ),
				'type_options' => self::sanitize_type_options( $type, $raw['type_options'] ?? array(), $errors ),
			),
			'errors' => $errors,
		);
	}

	/**
	 * Upgrades a stored config to the current schema.
	 *
	 * @param array $config Stored config.
	 * @return array
	 */
	public static function migrate( array $config ): array {
		$schema = isset( $config['schema'] ) && is_numeric( $config['schema'] ) ? max( 1, (int) $config['schema'] ) : 1;
		for ( $next = $schema + 1; $next <= self::SCHEMA; $next++ ) {
			if ( isset( self::MIGRATIONS[ $next ] ) ) {
				$config = call_user_func( array( self::class, self::MIGRATIONS[ $next ] ), $config );
			}
		}
		$config['schema'] = self::SCHEMA;
		return $config;
	}

	/**
	 * Reads a chart's config, always sanitized and upgraded.
	 *
	 * @param int $post_id Chart post ID.
	 * @return array
	 */
	public static function get( int $post_id ): array {
		$stored  = get_post_meta( $post_id, self::META_KEY, true );
		$decoded = is_string( $stored ) && '' !== $stored ? json_decode( $stored, true ) : null;
		if ( ! is_array( $decoded ) ) {
			return self::defaults();
		}
		return self::sanitize( self::migrate( $decoded ) )['config'];
	}

	/**
	 * Stores a config. Callers sanitize first.
	 *
	 * @param int   $post_id Chart post ID.
	 * @param array $config  Sanitized config.
	 * @return bool False when the encoded config is too large.
	 */
	public static function save( int $post_id, array $config ): bool {
		$json = wp_json_encode( $config );
		if ( false === $json || strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}
		update_post_meta( $post_id, self::META_KEY, wp_slash( $json ) );
		return true;
	}

	/**
	 * Removes rows with no label and no values; labels, values and point colours stay aligned.
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	private static function drop_empty_rows( array $raw ): array {
		if ( ! isset( $raw['labels'] ) || ! is_array( $raw['labels'] ) ) {
			return $raw;
		}
		$labels = array_values( $raw['labels'] );
		$series = isset( $raw['series'] ) && is_array( $raw['series'] ) ? $raw['series'] : array();
		$empty  = static fn( $value ): bool => null === $value || ( is_string( $value ) && '' === trim( $value ) );
		$drop   = array();
		foreach ( $labels as $i => $label ) {
			if ( is_scalar( $label ) && '' !== trim( (string) $label ) ) {
				continue;
			}
			$blank = true;
			foreach ( $series as $item ) {
				$values = is_array( $item ) && isset( $item['values'] ) && is_array( $item['values'] ) ? array_values( $item['values'] ) : array();
				if ( ! $empty( $values[ $i ] ?? null ) ) {
					$blank = false;
					break;
				}
			}
			if ( $blank ) {
				$drop[ $i ] = true;
			}
		}
		if ( ! $drop ) {
			return $raw;
		}
		$keep          = static fn( array $rows ): array => array_values( array_diff_key( array_values( $rows ), $drop ) );
		$raw['labels'] = $keep( $labels );
		foreach ( $series as $n => $item ) {
			if ( is_array( $item ) && isset( $item['values'] ) && is_array( $item['values'] ) ) {
				$series[ $n ]['values'] = $keep( $item['values'] );
			}
		}
		$raw['series'] = $series;
		if ( isset( $raw['point_colors'] ) && is_array( $raw['point_colors'] ) ) {
			$raw['point_colors'] = $keep( $raw['point_colors'] );
		}
		return $raw;
	}

	/**
	 * Sanitizes labels.
	 *
	 * @param mixed    $raw    Raw labels.
	 * @param string[] $errors Collected errors.
	 * @return string[]
	 */
	private static function sanitize_labels( $raw, array &$errors ): array {
		$labels = is_array( $raw ) ? array_values( $raw ) : array();
		if ( count( $labels ) > self::MAX_LABELS ) {
			/* translators: %d: maximum number of rows. */
			$errors[] = sprintf( _n( 'At most %d row is allowed; extra rows were removed.', 'At most %d rows are allowed; extra rows were removed.', self::MAX_LABELS, 'wa-charts' ), self::MAX_LABELS );
			$labels   = array_slice( $labels, 0, self::MAX_LABELS );
		}
		return array_map( static fn( $label ) => self::text( $label, self::MAX_TEXT ), $labels );
	}

	/**
	 * Sanitizes series.
	 *
	 * @param mixed    $raw         Raw series.
	 * @param int      $label_count Number of labels.
	 * @param string   $type        Chart type.
	 * @param string[] $errors      Collected errors.
	 * @return array
	 */
	private static function sanitize_series( $raw, int $label_count, string $type, array &$errors ): array {
		$items = is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : array();
		if ( count( $items ) > self::MAX_SERIES ) {
			/* translators: %d: maximum number of series. */
			$errors[] = sprintf( __( 'At most %d series are allowed; extra series were removed.', 'wa-charts' ), self::MAX_SERIES );
			$items    = array_slice( $items, 0, self::MAX_SERIES );
		}
		if ( ! $items ) {
			$items = array( array() );
		}
		$out = array();
		foreach ( $items as $item ) {
			$raw_values = isset( $item['values'] ) && is_array( $item['values'] ) ? array_values( $item['values'] ) : array();
			$values     = array();
			for ( $i = 0; $i < $label_count; $i++ ) {
				$values[] = self::number( $raw_values[ $i ] ?? null, $errors );
			}
			$render = isset( $item['render'] ) && in_array( $item['render'], array( 'bar', 'line' ), true ) ? $item['render'] : null;
			if ( 'mixed' === $type && null === $render ) {
				$render = 'bar';
			}
			$out[] = array(
				'name'   => self::text( $item['name'] ?? '', self::MAX_TEXT ),
				'color'  => self::color( $item['color'] ?? null, $errors ),
				'render' => $render,
				'values' => $values,
			);
		}
		return $out;
	}

	/**
	 * Sanitizes display settings. Invalid values fall back to defaults silently.
	 *
	 * @param mixed $raw Raw display settings.
	 * @return array
	 */
	private static function sanitize_display( $raw ): array {
		$defaults = self::default_display();
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		$legend = isset( $raw['legend'] ) && is_array( $raw['legend'] ) ? $raw['legend'] : array();
		$value  = isset( $raw['value'] ) && is_array( $raw['value'] ) ? $raw['value'] : array();

		return array(
			'heading'     => self::heading( $raw['heading'] ?? '' ),
			'subheading'  => self::heading( $raw['subheading'] ?? '' ),
			'layout'      => self::choice( $raw['layout'] ?? null, self::LAYOUTS, $defaults['layout'] ),
			'height'      => (int) self::between( $raw['height'] ?? null, 100, 2000, $defaults['height'], true ),
			'legend'      => array(
				'position'    => self::choice( $legend['position'] ?? null, self::LEGEND_POSITIONS, 'bottom' ),
				'columns'     => (int) self::between( $legend['columns'] ?? null, 1, 2, 1, true ),
				'show_values' => self::boolean( $legend['show_values'] ?? null, true ),
			),
			'value'       => array(
				'prefix'   => self::affix( $value['prefix'] ?? '' ),
				'suffix'   => self::affix( $value['suffix'] ?? '' ),
				'decimals' => (int) self::between( $value['decimals'] ?? null, 0, 6, 2, true ),
				'locale'   => self::choice( $value['locale'] ?? null, self::LOCALES, 'fi-FI' ),
			),
			'data_labels' => self::boolean( $raw['data_labels'] ?? null, false ),
			'tooltips'    => self::boolean( $raw['tooltips'] ?? null, true ),
			'animation'   => self::boolean( $raw['animation'] ?? null, true ),
			'font_family' => self::font_family( $raw['font_family'] ?? '' ),
		);
	}

	/**
	 * Sanitizes type options against the registry specs.
	 *
	 * @param string   $type   Chart type.
	 * @param mixed    $raw    Raw options.
	 * @param string[] $errors Collected errors.
	 * @return array
	 */
	private static function sanitize_type_options( string $type, $raw, array &$errors ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( Chart_Types::get( $type )['options'] as $key => $spec ) {
			$value = $raw[ $key ] ?? null;
			switch ( $spec['type'] ) {
				case 'int':
					$out[ $key ] = (int) self::between( $value, $spec['min'], $spec['max'], $spec['default'], true );
					break;
				case 'float':
					$out[ $key ] = (float) self::between( $value, $spec['min'], $spec['max'], $spec['default'], false );
					break;
				case 'bool':
					$out[ $key ] = self::boolean( $value, $spec['default'] );
					break;
				case 'enum':
					$out[ $key ] = self::choice( $value, array_keys( $spec['choices'] ), $spec['default'] );
					break;
				case 'color':
					$out[ $key ] = self::color( $value, $errors ) ?? $spec['default'];
					break;
			}
		}
		return $out;
	}

	/**
	 * Plain text, tags stripped, length-limited.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	private static function text( $value, int $max ): string {
		return is_scalar( $value ) ? mb_substr( str_replace( '&lt;', '<', sanitize_text_field( (string) $value ) ), 0, $max ) : '';
	}

	/**
	 * Prefix/suffix: like text() but keeps one leading/trailing space.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function affix( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = (string) $value;
		$clean = self::text( $value, self::MAX_AFFIX );
		if ( '' === $clean ) {
			return '' === $value ? '' : ( ctype_space( $value ) ? ' ' : '' );
		}
		$lead  = preg_match( '/^\s/u', $value ) ? ' ' : '';
		$trail = preg_match( '/\s$/u', $value ) ? ' ' : '';
		return $lead . $clean . $trail;
	}

	/**
	 * Heading HTML limited to br/strong/em/span without attributes.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function heading( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$truncated = mb_substr( (string) $value, 0, self::MAX_HEADING );
		$balanced  = force_balance_tags( wp_kses( $truncated, self::HEADING_TAGS ) );
		// force_balance_tags() self-closes void tags; keep the stored form as plain <br>.
		return trim( str_replace( '<br />', '<br>', $balanced ) );
	}

	/**
	 * Font family stack with only safe characters.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function font_family( $value ): string {
		$text = self::text( $value, 100 );
		return trim( (string) preg_replace( '/[^A-Za-z0-9 ,\'"\-]/', '', $text ) );
	}

	/**
	 * Number from int/float or a string with a decimal comma or spaces.
	 *
	 * @param mixed    $value  Raw value.
	 * @param string[] $errors Collected errors.
	 * @return float
	 */
	private static function number( $value, array &$errors ): float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return is_finite( (float) $value ) ? (float) $value : 0.0;
		}
		if ( null === $value || '' === $value ) {
			return 0.0;
		}
		if ( is_string( $value ) ) {
			$normalized = str_replace( array( ' ', "\u{00A0}", "\u{202F}", ',' ), array( '', '', '', '.' ), trim( $value ) );
			if ( is_numeric( $normalized ) && is_finite( (float) $normalized ) ) {
				return (float) $normalized;
			}
		}
		/* translators: %s: the rejected value. */
		$errors[] = sprintf( __( 'Value "%s" is not a number and was set to 0.', 'wa-charts' ), is_scalar( $value ) ? sanitize_text_field( (string) $value ) : gettype( $value ) );
		return 0.0;
	}

	/**
	 * Hex colour (#RGB, #RRGGBB or #RRGGBBAA) or null.
	 *
	 * @param mixed    $value  Raw value.
	 * @param string[] $errors Collected errors.
	 * @return string|null
	 */
	private static function color( $value, array &$errors ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( is_string( $value ) ) {
			$hex = sanitize_hex_color( $value );
			if ( $hex ) {
				return $hex;
			}
			if ( preg_match( '/^#[0-9a-fA-F]{8}\z/', $value ) ) {
				return $value;
			}
		}
		/* translators: %s: the rejected colour. */
		$errors[] = sprintf( __( 'Colour "%s" is not valid and was replaced.', 'wa-charts' ), is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '' );
		return null;
	}

	/**
	 * Value from an allowed list.
	 *
	 * @param mixed  $value   Raw value.
	 * @param array  $allowed Allowed values.
	 * @param string $fallback Default.
	 * @return string
	 */
	private static function choice( $value, array $allowed, string $fallback ): string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Clamped number.
	 *
	 * @param mixed     $value    Raw value.
	 * @param int|float $min      Minimum.
	 * @param int|float $max      Maximum.
	 * @param int|float $fallback Default when not numeric.
	 * @param bool      $integer  Round to an integer.
	 * @return int|float
	 */
	private static function between( $value, $min, $max, $fallback, bool $integer ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		$number = $integer ? (int) round( (float) $value ) : (float) $value;
		return max( $min, min( $max, $number ) );
	}

	/**
	 * Boolean from bool/"1"/"yes"/"true"/"0"/…
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Default.
	 * @return bool
	 */
	private static function boolean( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( null === $value ) {
			return $fallback;
		}
		$parsed = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		return null === $parsed ? $fallback : $parsed;
	}
}
