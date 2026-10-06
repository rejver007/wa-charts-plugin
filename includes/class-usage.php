<?php
/**
 * Where charts are used.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Finds posts that embed a chart by shortcode, block or Elementor shortcode widget.
 */
final class Usage {

	public const GENERATION_OPTION = 'wa_charts_usage_generation';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'save_post', array( self::class, 'bump' ) );
		add_action( 'deleted_post', array( self::class, 'bump' ) );
	}

	/**
	 * Invalidates all cached lookups by bumping a generation counter.
	 *
	 * @param int $post_id Saved post ID.
	 * @return void
	 */
	public static function bump( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		update_option( self::GENERATION_OPTION, (int) get_option( self::GENERATION_OPTION, 0 ) + 1, false );
	}

	/**
	 * Posts that embed the chart.
	 *
	 * @param int $chart_id Chart ID.
	 * @return int[]
	 */
	public static function find( int $chart_id ): array {
		$key    = 'wa_charts_usage_' . $chart_id . '_' . (int) get_option( self::GENERATION_OPTION, 0 );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$id       = (string) $chart_id;
		$patterns = array(
			'[wa_chart id="' . $id . '"',
			"[wa_chart id='" . $id . "'",
			'[wa_chart id=' . $id . ' ',
			'[wa_chart id=' . $id . ']',
			'"chartId":' . $id . ',',
			'"chartId":' . $id . '}',
		);
		$likes    = array_map( static fn( $pattern ) => '%' . $wpdb->esc_like( $pattern ) . '%', $patterns );
		$likes[]  = '%' . $wpdb->esc_like( 'wa_chart id=\\"' . $id . '\\"' ) . '%';
		$where    = implode( ' OR ', array_fill( 0, count( $patterns ), 'p.post_content LIKE %s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where contains only placeholders (count not statically visible to the sniff); result is cached.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
				WHERE p.post_type NOT IN ( 'revision', %s )
				AND p.post_status IN ( 'publish', 'draft', 'pending', 'private', 'future' )
				AND ( {$where} OR m.meta_value LIKE %s )
				ORDER BY p.ID ASC LIMIT 50",
				array_merge( array( Post_Type::SLUG ), $likes )
			)
		);
		// phpcs:enable

		$ids = array_map( 'intval', $ids );
		set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
		return $ids;
	}

	/**
	 * HTML list for the edit screen.
	 *
	 * @param int $chart_id Chart ID.
	 * @return string
	 */
	public static function render_list( int $chart_id ): string {
		$ids = self::find( $chart_id );
		if ( ! $ids ) {
			return '<p>' . esc_html__( 'Not used on any page yet.', 'wa-charts' ) . '</p>';
		}
		$items = '';
		foreach ( $ids as $id ) {
			$title = get_the_title( $id );
			if ( '' === $title ) {
				/* translators: %d: post ID. */
				$title = sprintf( __( '#%d (no title)', 'wa-charts' ), $id );
			}
			$link   = get_edit_post_link( $id );
			$items .= $link
				? sprintf( '<li><a href="%s">%s</a></li>', esc_url( $link ), esc_html( $title ) )
				: sprintf( '<li>%s</li>', esc_html( $title ) );
		}
		return '<p><strong>' . esc_html__( 'Used on:', 'wa-charts' ) . '</strong></p><ul class="wa-charts-usage">' . $items . '</ul>';
	}
}
