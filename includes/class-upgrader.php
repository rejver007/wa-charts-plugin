<?php
/**
 * Stored-data upgrades.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Re-saves charts in batches of 50 after the schema version changes.
 */
final class Upgrader {

	public const OPTION_SCHEMA = 'wa_charts_db_schema';
	public const OPTION_CURSOR = 'wa_charts_upgrade_cursor';
	public const BATCH         = 50;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
	}

	/**
	 * Runs one batch per admin request until the stored schema is current.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPTION_SCHEMA, 0 ) < Chart_Data::SCHEMA ) {
			self::run_batch();
		}
	}

	/**
	 * Upgrades the next batch of charts.
	 *
	 * @return bool True when all charts are done.
	 */
	public static function run_batch(): bool {
		global $wpdb;
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off batched maintenance query.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d", Post_Type::SLUG, $cursor, self::BATCH ) );
		foreach ( $ids as $id ) {
			$id      = (int) $id;
			$raw     = get_post_meta( $id, Chart_Data::META_KEY, true );
			$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
			if ( is_array( $decoded ) ) {
				// Undecodable data is left untouched so it stays recoverable.
				Chart_Data::save( $id, Chart_Data::sanitize( Chart_Data::migrate( $decoded ) )['config'] );
			}
		}
		if ( count( $ids ) < self::BATCH ) {
			update_option( self::OPTION_SCHEMA, Chart_Data::SCHEMA, false );
			delete_option( self::OPTION_CURSOR );
			return true;
		}
		update_option( self::OPTION_CURSOR, (int) end( $ids ), false );
		return false;
	}
}
