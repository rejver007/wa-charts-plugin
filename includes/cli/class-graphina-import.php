<?php
/**
 * `wp wa-charts import-graphina`.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts\Cli;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Graphina widgets in Elementor content into wa-charts shortcodes.
 */
final class Graphina_Import {

	public const BACKUP_META  = '_wa_charts_graphina_backup';
	public const CREATED_META = '_wa_charts_graphina_created';

	/**
	 * Registers the command.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'wa-charts import-graphina', self::class );
	}

	/**
	 * Converts Graphina chart widgets in Elementor content to wa-charts shortcodes.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything. Also works with --rollback.
	 *
	 * [--post[=<id>]]
	 * : Only process this post ID. Always pass a value: --post=<id>.
	 *
	 * [--rollback]
	 * : Restore Elementor data from the backup and trash the charts the import created.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wa-charts import-graphina --dry-run
	 *     wp wa-charts import-graphina --post=2
	 *     wp wa-charts import-graphina --rollback
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$only = self::post_arg( $assoc_args );
		if ( null === $only ) {
			\WP_CLI::error( 'Invalid --post value; use --post=<id>.' );
			return;
		}
		$dry_run = isset( $assoc_args['dry-run'] );
		$log     = static function ( string $message, string $level = 'log' ): void {
			if ( 'warning' === $level ) {
				\WP_CLI::warning( $message );
			} elseif ( 'line' === $level ) {
				\WP_CLI::line( $message );
			} else {
				\WP_CLI::log( $message );
			}
		};

		if ( isset( $assoc_args['rollback'] ) ) {
			$ids = $only ? array( $only ) : self::find_backed_up_posts();
			foreach ( $ids as $id ) {
				$this->rollback_post( (int) $id, $dry_run, $log );
			}
			if ( ! $dry_run ) {
				self::clear_elementor_cache();
			}
			\WP_CLI::success( $dry_run ? 'Rollback dry run finished.' : 'Rollback finished.' );
			return;
		}

		$ids = $only ? array( $only ) : self::find_posts();
		if ( ! $ids ) {
			\WP_CLI::success( 'No posts with Graphina widgets found.' );
			return;
		}
		$converted = 0;
		foreach ( $ids as $id ) {
			$converted += $this->convert_post( (int) $id, $dry_run, $log )['converted'];
		}
		if ( ! $dry_run && $converted ) {
			self::clear_elementor_cache();
		}
		\WP_CLI::success( sprintf( '%s %d widget(s).', $dry_run ? 'Would convert' : 'Converted', $converted ) );
	}

	/**
	 * Parses the --post argument.
	 *
	 * @param array $assoc_args Named arguments.
	 * @return int|null 0 when not given, the post ID, or null when the value is invalid (e.g. a bare --post).
	 */
	public static function post_arg( array $assoc_args ): ?int {
		if ( ! array_key_exists( 'post', $assoc_args ) ) {
			return 0;
		}
		$value = $assoc_args['post'];
		return ( is_string( $value ) && ctype_digit( $value ) && (int) $value > 0 ) ? (int) $value : null;
	}

	/**
	 * Post IDs whose Elementor data mentions a *_chart widget.
	 *
	 * @return int[]
	 */
	public static function find_posts(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off CLI migration.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND m.meta_value LIKE %s ORDER BY m.post_id",
				'%' . $wpdb->esc_like( '_chart"' ) . '%'
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Post IDs that hold an import backup.
	 *
	 * @return int[]
	 */
	public static function find_backed_up_posts(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off CLI migration.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id", self::BACKUP_META ) );
		return array_map( 'intval', $ids );
	}

	/**
	 * Converts one post. Fails safe: on any problem nothing is left half-written.
	 *
	 * @param int      $post_id Post ID.
	 * @param bool     $dry_run Whether to skip writing.
	 * @param callable $log     fn( string $message, string $level = 'log' ): void.
	 * @return array{converted:int, charts:int[]}
	 * @throws \RuntimeException Never; failures raised inside are caught and undone here.
	 */
	public function convert_post( int $post_id, bool $dry_run, callable $log ): array {
		$none = array(
			'converted' => 0,
			'charts'    => array(),
		);
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$json = is_array( $raw ) ? (string) wp_json_encode( $raw ) : (string) $raw;
		$tree = json_decode( $json, true );
		if ( ! is_array( $tree ) ) {
			$log( sprintf( 'Post %d: Elementor data is not valid JSON, skipped.', $post_id ), 'warning' );
			return $none;
		}

		// First pass writes nothing; it only tells us what would be converted.
		$report = array();
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Callback signature is fixed.
		Graphina_Mapper::convert_tree( $tree, static fn( array $config, string $title ): int => 0, $report );
		$converted = count( array_filter( $report, static fn( $item ) => 'converted' === $item['status'] ) );
		if ( $dry_run ) {
			$this->print_report( $post_id, $report, $log );
			return array(
				'converted' => $converted,
				'charts'    => array(),
			);
		}
		if ( 0 === $converted ) {
			$this->print_report( $post_id, $report, $log );
			return $none;
		}

		// Confirm the backup before anything is created or overwritten.
		$backup_is_new = false;
		$backup        = get_post_meta( $post_id, self::BACKUP_META, true );
		if ( '' === $backup || false === $backup ) {
			add_post_meta( $post_id, self::BACKUP_META, wp_slash( $json ), true );
			$backup        = get_post_meta( $post_id, self::BACKUP_META, true );
			$backup_is_new = true;
			if ( ! is_string( $backup ) || $backup !== $json ) {
				$log( sprintf( 'Post %d: backup could not be verified, skipped.', $post_id ), 'warning' );
				delete_post_meta( $post_id, self::BACKUP_META );
				return $none;
			}
		} elseif ( ! is_string( $backup ) || '' === $backup ) {
			$log( sprintf( 'Post %d: existing backup is unusable, skipped.', $post_id ), 'warning' );
			return $none;
		}

		$created = array();
		$create  = function ( array $config, string $title ) use ( $post_id, &$created ): int {
			$id = wp_insert_post(
				array(
					'post_type'   => Post_Type::SLUG,
					'post_status' => 'publish',
					'post_title'  => wp_slash( $title ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				throw new \RuntimeException( $id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput -- CLI, not output to a browser.
			}
			$created[] = $id;
			// Recorded at once so rollback can always clean up, even if a later step fails.
			add_post_meta( $post_id, self::CREATED_META, $id );
			if ( ! Chart_Data::save( $id, $config ) ) {
				throw new \RuntimeException( sprintf( 'Saving chart %d failed.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- CLI, not output to a browser.
			}
			return $id;
		};

		try {
			$report   = array();
			$new_tree = Graphina_Mapper::convert_tree( $tree, $create, $report );
			$new_json = (string) wp_json_encode( $new_tree );
			update_post_meta( $post_id, '_elementor_data', wp_slash( $new_json ) );
			if ( get_post_meta( $post_id, '_elementor_data', true ) !== $new_json ) {
				throw new \RuntimeException( 'Elementor data could not be written.' );
			}
		} catch ( \RuntimeException $e ) {
			$this->undo( $post_id, $created, $json, $backup_is_new, $log );
			$log( sprintf( 'Post %d: %s Changes undone, skipped.', $post_id, $e->getMessage() ), 'warning' );
			return $none;
		}

		$this->print_report( $post_id, $report, $log );
		return array(
			'converted' => $converted,
			'charts'    => $created,
		);
	}

	/**
	 * Undoes a failed conversion: trashes the charts made in this run and restores the data.
	 *
	 * @param int      $post_id       Post ID.
	 * @param int[]    $created       Charts created in this run.
	 * @param string   $json          Original Elementor JSON.
	 * @param bool     $backup_is_new Whether this run wrote the backup.
	 * @param callable $log           Logger.
	 * @return void
	 */
	private function undo( int $post_id, array $created, string $json, bool $backup_is_new, callable $log ): void {
		foreach ( $created as $chart_id ) {
			wp_trash_post( $chart_id );
			delete_post_meta( $post_id, self::CREATED_META, $chart_id );
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
		if ( ! $backup_is_new ) {
			return;
		}
		if ( get_post_meta( $post_id, '_elementor_data', true ) === $json ) {
			delete_post_meta( $post_id, self::BACKUP_META );
		} else {
			$log( sprintf( 'Post %d: restore could not be verified; backup kept.', $post_id ), 'warning' );
		}
	}

	/**
	 * Prints the per-widget report.
	 *
	 * @param int      $post_id Post ID.
	 * @param array    $report  Report items.
	 * @param callable $log     Logger.
	 * @return void
	 */
	private function print_report( int $post_id, array $report, callable $log ): void {
		foreach ( $report as $item ) {
			if ( 'manual' === $item['status'] ) {
				$log( sprintf( 'Post %d: %s (%s) is not supported; migrate it by hand. Settings:', $post_id, $item['widget'], $item['id'] ), 'warning' );
				$log( (string) wp_json_encode( $item['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 'line' );
				continue;
			}
			$log( sprintf( 'Post %d: %s (%s) -> chart #%d "%s"', $post_id, $item['widget'], $item['id'], $item['chart_id'], $item['title'] ) );
			foreach ( $item['flags'] as $flag ) {
				$log( '    check: ' . $flag );
			}
			if ( '' !== trim( $item['custom_css'] ) ) {
				$log( '    custom CSS to move into the theme by hand:' );
				$log( $item['custom_css'] );
			}
		}
	}

	/**
	 * Restores one post from its backup and trashes the charts the import created.
	 *
	 * @param int      $post_id Post ID.
	 * @param bool     $dry_run Whether to only report.
	 * @param callable $log     Logger.
	 * @return bool Whether the post was (or would be) restored.
	 */
	public function rollback_post( int $post_id, bool $dry_run, callable $log ): bool {
		$backup = get_post_meta( $post_id, self::BACKUP_META, true );
		if ( ! is_string( $backup ) || '' === $backup ) {
			$log( sprintf( 'Post %d has no backup, skipped.', $post_id ), 'warning' );
			return false;
		}
		$created = self::created_ids( $post_id );
		if ( $dry_run ) {
			$log( sprintf( 'Would restore post %d and trash charts [%s]', $post_id, implode( ', ', $created ) ) );
			return true;
		}

		update_post_meta( $post_id, '_elementor_data', wp_slash( $backup ) );
		if ( get_post_meta( $post_id, '_elementor_data', true ) !== $backup ) {
			$log( sprintf( 'Post %d: restore could not be verified; backup kept.', $post_id ), 'warning' );
			return false;
		}
		foreach ( $created as $chart_id ) {
			if ( Post_Type::SLUG !== get_post_type( $chart_id ) ) {
				$log( sprintf( 'Post %d: #%d is not a chart, left alone.', $post_id, $chart_id ), 'warning' );
				continue;
			}
			wp_trash_post( $chart_id );
		}
		delete_post_meta( $post_id, self::BACKUP_META );
		delete_post_meta( $post_id, self::CREATED_META );
		$log( sprintf( 'Post %d restored.', $post_id ) );
		return true;
	}

	/**
	 * The chart IDs recorded for a post, normalised (legacy array rows expanded, junk dropped).
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function created_ids( int $post_id ): array {
		$rows = get_post_meta( $post_id, self::CREATED_META, false );
		$ids  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			foreach ( is_array( $row ) ? $row : array( $row ) as $value ) {
				if ( is_scalar( $value ) && ctype_digit( (string) $value ) && (int) $value > 0 ) {
					$ids[] = (int) $value;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Clears Elementor's generated CSS so pages re-render.
	 *
	 * @return void
	 */
	private static function clear_elementor_cache(): void {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}
