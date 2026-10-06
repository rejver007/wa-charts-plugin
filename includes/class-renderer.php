<?php
/**
 * Chart HTML renderer.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a stored chart into HTML plus a library-neutral JSON payload.
 */
final class Renderer {

	public const HANDLE_CHARTJS    = 'wa-charts-chartjs';
	public const HANDLE_DATALABELS = 'wa-charts-datalabels';
	public const HANDLE_OPTIONS    = 'wa-charts-options';
	public const HANDLE_FRONT      = 'wa-charts';

	/**
	 * Per-request instance counter for unique element IDs.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_assets' ) );
	}

	/**
	 * Registers (does not enqueue) front-end assets.
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		$args = array(
			'in_footer' => true,
			'strategy'  => 'defer',
		);
		wp_register_script( self::HANDLE_CHARTJS, WA_CHARTS_URL . 'assets/vendor/chart.umd.min.js', array(), WA_CHARTS_CHARTJS_VERSION, $args );
		wp_register_script( self::HANDLE_DATALABELS, WA_CHARTS_URL . 'assets/vendor/chartjs-plugin-datalabels.min.js', array( self::HANDLE_CHARTJS ), WA_CHARTS_DATALABELS_VERSION, $args );
		wp_register_script( self::HANDLE_OPTIONS, WA_CHARTS_URL . 'assets/front/chart-options.js', array(), WA_CHARTS_VERSION, $args );
		wp_register_script( self::HANDLE_FRONT, WA_CHARTS_URL . 'assets/front/wa-charts.js', array( self::HANDLE_CHARTJS, self::HANDLE_OPTIONS ), WA_CHARTS_VERSION, $args );
		wp_register_style( self::HANDLE_FRONT, WA_CHARTS_URL . 'assets/front/wa-charts.css', array(), WA_CHARTS_VERSION );
	}

	/**
	 * Enqueues the runtime. Data labels make the runtime depend on the plugin script,
	 * so it is printed first.
	 *
	 * @param bool $data_labels Whether a chart on this page shows data labels.
	 * @return void
	 */
	public static function enqueue( bool $data_labels ): void {
		if ( ! wp_script_is( self::HANDLE_FRONT, 'registered' ) ) {
			self::register_assets();
		}
		if ( $data_labels ) {
			$script = wp_scripts()->query( self::HANDLE_FRONT, 'registered' );
			if ( $script && ! in_array( self::HANDLE_DATALABELS, $script->deps, true ) ) {
				$script->deps[] = self::HANDLE_DATALABELS;
			}
		}
		wp_enqueue_script( self::HANDLE_FRONT );
		wp_enqueue_style( self::HANDLE_FRONT );
	}

	/**
	 * Validates per-embed overrides from the shortcode or block.
	 *
	 * @param array $raw Raw overrides.
	 * @return array
	 */
	public static function normalize_overrides( array $raw ): array {
		$out = array();
		if ( isset( $raw['height'] ) && is_numeric( $raw['height'] ) ) {
			$out['height'] = max( 100, min( 2000, absint( $raw['height'] ) ) );
		}
		if ( isset( $raw['layout'] ) && in_array( $raw['layout'], Chart_Data::LAYOUTS, true ) ) {
			$out['layout'] = $raw['layout'];
		}
		if ( isset( $raw['legend'] ) && in_array( $raw['legend'], Chart_Data::LEGEND_POSITIONS, true ) ) {
			$out['legend'] = $raw['legend'];
		}
		$classes = isset( $raw['class'] ) && is_string( $raw['class'] ) ? preg_split( '/\s+/', $raw['class'], -1, PREG_SPLIT_NO_EMPTY ) : array();
		$classes = array_unique( array_filter( array_map( 'sanitize_html_class', $classes ) ) );
		if ( $classes ) {
			$out['class'] = implode( ' ', $classes );
		}
		return $out;
	}

	/**
	 * Renders a chart for display, applying the visibility rules.
	 *
	 * @param int   $post_id   Chart ID.
	 * @param array $overrides Raw overrides.
	 * @return string HTML, or '' (or an editor-only notice) when the chart cannot be shown.
	 */
	public static function output( int $post_id, array $overrides = array() ): string {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || Post_Type::SLUG !== $post->post_type || 'trash' === $post->post_status ) {
			/* translators: %d: chart ID. */
			return self::notice( sprintf( __( 'Chart #%d not found.', 'wa-charts' ), $post_id ) );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
			/* translators: %d: chart ID. */
			return self::notice( sprintf( __( 'Chart #%d is not published.', 'wa-charts' ), $post_id ) );
		}
		return self::render( $post->ID, $overrides );
	}

	/**
	 * Renders a chart without visibility checks.
	 *
	 * @param int   $post_id   Chart ID.
	 * @param array $overrides Raw overrides.
	 * @return string
	 */
	public static function render( int $post_id, array $overrides = array() ): string {
		$overrides = self::normalize_overrides( $overrides );
		$config    = Chart_Data::get( $post_id );
		if ( isset( $overrides['height'] ) ) {
			$config['display']['height'] = $overrides['height'];
		}
		if ( isset( $overrides['layout'] ) ) {
			$config['display']['layout'] = $overrides['layout'];
		}
		if ( isset( $overrides['legend'] ) ) {
			$config['display']['legend']['position'] = $overrides['legend'];
		}
		self::enqueue( (bool) $config['display']['data_labels'] );

		$instance_id = sprintf( 'wa-chart-%d-%d', $post_id, ++self::$counter );
		$html        = self::html( $config, $post_id, $instance_id, $overrides['class'] ?? '' );
		return (string) apply_filters( 'wa_charts_render_html', $html, $config, $post_id );
	}

	/**
	 * Payload read by `wa-charts.js`.
	 *
	 * @param array $config  Sanitized config.
	 * @param int   $post_id Chart ID (0 for previews).
	 * @return array
	 */
	public static function client_payload( array $config, int $post_id ): array {
		$library = apply_filters( 'wa_charts_library_options', array(), $config, $post_id );
		return array(
			'type'             => $config['type'],
			'labels'           => $config['labels'],
			'series'           => $config['series'],
			'pointColors'      => $config['point_colors'],
			'display'          => $config['display'],
			'typeOptions'      => (object) $config['type_options'],
			'libraryOverrides' => (object) ( is_array( $library ) ? $library : array() ),
		);
	}

	/**
	 * Figure markup.
	 *
	 * @param array  $config      Config with overrides applied.
	 * @param int    $post_id     Chart ID.
	 * @param string $instance_id Element ID.
	 * @param string $extra_class Sanitized extra classes.
	 * @return string
	 */
	private static function html( array $config, int $post_id, string $instance_id, string $extra_class ): string {
		$display = $config['display'];
		$classes = array(
			'wa-chart',
			'wa-chart--' . $config['type'],
			'wa-chart--layout-' . $display['layout'],
			'wa-chart--legend-' . $display['legend']['position'],
		);
		if ( '' !== $extra_class ) {
			$classes[] = $extra_class;
		}
		$style = sprintf( '--wa-chart-legend-columns:%d;--wa-chart-height:%dpx', $display['legend']['columns'], $display['height'] );
		if ( '' !== $display['font_family'] ) {
			$style .= ';--wa-chart-font:' . $display['font_family'];
		}

		$label = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_ireplace( array( '<br>', '<br/>', '<br />' ), ' ', $display['heading'] ) ) ) );
		if ( '' === $label ) {
			$label = get_the_title( $post_id );
		}

		$caption = '';
		if ( '' !== $display['heading'] || '' !== $display['subheading'] ) {
			$caption = '<figcaption class="wa-chart__text">';
			if ( '' !== $display['heading'] ) {
				$caption .= '<span class="wa-chart__heading">' . wp_kses( $display['heading'], Chart_Data::HEADING_TAGS ) . '</span>';
			}
			if ( '' !== $display['subheading'] ) {
				$caption .= '<span class="wa-chart__subheading">' . wp_kses( $display['subheading'], Chart_Data::HEADING_TAGS ) . '</span>';
			}
			$caption .= '</figcaption>';
		}

		$json = wp_json_encode( self::client_payload( $config, $post_id ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		return sprintf(
			'<figure class="%1$s" id="%2$s" style="%3$s"><div class="wa-chart__inner"><div class="wa-chart__plot"><div class="wa-chart__canvas"><canvas role="img" aria-label="%4$s"></canvas></div><ul class="wa-chart__legend" hidden></ul></div>%5$s</div><script type="application/json" class="wa-chart__config">%6$s</script>%7$s</figure>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $instance_id ),
			esc_attr( $style ),
			esc_attr( $label ),
			$caption,
			$json,
			self::table( $config )
		);
	}

	/**
	 * Accessible data table, also the no-JS fallback. It lists only what the chart type shows.
	 *
	 * @param array $config Config.
	 * @return string
	 */
	private static function table( array $config ): string {
		$value  = $config['display']['value'];
		$head   = '<th scope="col">' . esc_html__( 'Label', 'wa-charts' ) . '</th>';
		$shown  = Chart_Types::SHAPE_SINGLE === Chart_Types::shape( $config['type'] ) ? array_slice( $config['series'], 0, 1 ) : $config['series'];
		$labels = array_slice( $config['labels'], 0, (int) Chart_Types::get( $config['type'] )['max_rows'], true );
		foreach ( $shown as $series ) {
			$head .= '<th scope="col">' . esc_html( '' !== $series['name'] ? $series['name'] : __( 'Value', 'wa-charts' ) ) . '</th>';
		}
		$rows = '';
		foreach ( $labels as $i => $label ) {
			$rows .= '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $shown as $series ) {
				$rows .= '<td>' . esc_html( self::format_plain( (float) ( $series['values'][ $i ] ?? 0 ), $value ) ) . '</td>';
			}
			$rows .= '</tr>';
		}
		return '<table class="wa-chart__table screen-reader-text"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Number for the fallback table: trailing zeros dropped, comma decimals except en-US.
	 *
	 * @param float $number Value.
	 * @param array $value  `display.value` settings.
	 * @return string
	 */
	private static function format_plain( float $number, array $value ): string {
		$text = number_format( $number, $value['decimals'], '.', '' );
		if ( $value['decimals'] > 0 ) {
			$text = rtrim( rtrim( $text, '0' ), '.' );
		}
		if ( 'en-US' !== $value['locale'] ) {
			$text = str_replace( '.', ',', $text );
		}
		return $value['prefix'] . $text . $value['suffix'];
	}

	/**
	 * Notice shown only to chart editors.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function notice( string $message ): string {
		if ( ! current_user_can( 'edit_wa_charts' ) ) {
			return '';
		}
		wp_enqueue_style( self::HANDLE_FRONT );
		return '<div class="wa-chart-notice">' . esc_html( $message ) . '</div>';
	}
}
