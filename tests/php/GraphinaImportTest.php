<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Cli\Graphina_Import;
use WebAula\Charts\Post_Type;

class GraphinaImportTest extends TestCase {

	private string $original = '';
	private int $post_id     = 0;
	private array $messages  = array();

	public function set_up() {
		parent::set_up();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.
		$settings = json_decode( file_get_contents( __DIR__ . '/fixtures/graphina-public-research.json' ), true );
		$tree     = array(
			array(
				'id'       => 's1',
				'elType'   => 'section',
				'elements' => array(
					array(
						'id'       => 'c1',
						'elType'   => 'column',
						'elements' => array(
							array(
								'id'         => 'w1',
								'elType'     => 'widget',
								'widgetType' => 'pie_chart',
								'settings'   => $settings,
								'elements'   => array(),
							),
						),
					),
				),
			),
		);

		$this->original = (string) wp_json_encode( $tree );
		$this->post_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $this->post_id, '_elementor_data', wp_slash( $this->original ) );
		$this->messages = array();
	}

	private function log(): callable {
		return function ( string $message, string $level = 'log' ) {
			$this->messages[] = $level . ': ' . $message;
		};
	}

	private function charts( string $status = 'any' ): array {
		return get_posts(
			array(
				'post_type'   => Post_Type::SLUG,
				'post_status' => $status,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
	}

	public function test_dry_run_writes_nothing() {
		$import = new Graphina_Import();
		$result = $import->convert_post( $this->post_id, true, $this->log() );

		$this->assertSame( 1, $result['converted'] );
		$this->assertSame( array(), $this->charts() );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, '_elementor_data', true ) );
		$this->assertSame( '', get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		$this->assertSame( array(), get_post_meta( $this->post_id, Graphina_Import::CREATED_META, false ) );
	}

	public function test_real_run_creates_chart_and_exact_backup() {
		$import = new Graphina_Import();
		$result = $import->convert_post( $this->post_id, false, $this->log() );

		$this->assertSame( 1, $result['converted'] );
		$charts = $this->charts();
		$this->assertCount( 1, $charts );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		$this->assertSame( $charts, array_map( 'intval', get_post_meta( $this->post_id, Graphina_Import::CREATED_META, false ) ) );
		$this->assertStringContainsString( sprintf( '[wa_chart id=\"%d\"]', $charts[0] ), get_post_meta( $this->post_id, '_elementor_data', true ) );
	}

	public function test_rerun_creates_no_duplicates_and_keeps_backup() {
		$import = new Graphina_Import();
		$import->convert_post( $this->post_id, false, $this->log() );
		$again = $import->convert_post( $this->post_id, false, $this->log() );

		$this->assertSame( 0, $again['converted'] );
		$this->assertCount( 1, $this->charts() );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		$this->assertCount( 1, get_post_meta( $this->post_id, Graphina_Import::CREATED_META, false ) );
	}

	public function test_rollback_restores_exactly_and_cleans_up() {
		$import = new Graphina_Import();
		$import->convert_post( $this->post_id, false, $this->log() );
		$chart_ids = $this->charts();

		$this->assertTrue( $import->rollback_post( $this->post_id, false, $this->log() ) );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, '_elementor_data', true ) );
		$this->assertSame( 'trash', get_post_status( $chart_ids[0] ) );
		$this->assertSame( '', get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		$this->assertSame( array(), get_post_meta( $this->post_id, Graphina_Import::CREATED_META, false ) );
	}

	public function test_rollback_dry_run_changes_nothing() {
		$import = new Graphina_Import();
		$import->convert_post( $this->post_id, false, $this->log() );
		$converted = get_post_meta( $this->post_id, '_elementor_data', true );
		$chart_ids = $this->charts();

		$this->assertTrue( $import->rollback_post( $this->post_id, true, $this->log() ) );
		$this->assertSame( $converted, get_post_meta( $this->post_id, '_elementor_data', true ) );
		$this->assertSame( 'publish', get_post_status( $chart_ids[0] ) );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		$this->assertNotEmpty( get_post_meta( $this->post_id, Graphina_Import::CREATED_META, false ) );
	}

	public function test_skips_post_when_backup_cannot_be_confirmed() {
		// A non-string backup stands in for a backup that cannot be trusted.
		add_post_meta( $this->post_id, Graphina_Import::BACKUP_META, array( 'broken' ), true );
		$import = new Graphina_Import();
		$result = $import->convert_post( $this->post_id, false, $this->log() );

		$this->assertSame( 0, $result['converted'] );
		$this->assertSame( array(), $this->charts() );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, '_elementor_data', true ) );
	}

	public function test_failed_chart_save_leaves_no_orphans() {
		// Make chart creation fail: wp_insert_post returns a WP_Error for empty content.
		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		$import = new Graphina_Import();
		$result = $import->convert_post( $this->post_id, false, $this->log() );

		$this->assertSame( 0, $result['converted'] );
		$this->assertSame( $this->original, get_post_meta( $this->post_id, '_elementor_data', true ) );
		$this->assertSame( '', get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		remove_filter( 'wp_insert_post_empty_content', '__return_true' );
	}

	public function test_post_arg_parsing() {
		$this->assertSame( 0, Graphina_Import::post_arg( array() ) );
		$this->assertSame( 2, Graphina_Import::post_arg( array( 'post' => '2' ) ) );
		$this->assertNull( Graphina_Import::post_arg( array( 'post' => true ) ) );
		$this->assertNull( Graphina_Import::post_arg( array( 'post' => 'abc' ) ) );
		$this->assertNull( Graphina_Import::post_arg( array( 'post' => '0' ) ) );
	}

	public function test_rollback_only_trashes_real_charts_from_legacy_rows() {
		$import = new Graphina_Import();
		$import->convert_post( $this->post_id, false, $this->log() );
		$chart_a = $this->charts()[0];
		$chart_b = self::factory()->post->create( array( 'post_type' => Post_Type::SLUG ) );
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		add_post_meta( $this->post_id, Graphina_Import::CREATED_META, array( $chart_a, $chart_b ) );
		add_post_meta( $this->post_id, Graphina_Import::CREATED_META, $page );

		$this->assertTrue( $import->rollback_post( $this->post_id, false, $this->log() ) );
		$this->assertSame( 'trash', get_post_status( $chart_a ) );
		$this->assertSame( 'trash', get_post_status( $chart_b ) );
		$this->assertSame( 'publish', get_post_status( $page ) );
		$this->assertNotSame( 'trash', get_post_status( 1 ) );
	}

	public function test_undo_keeps_backup_when_restore_is_not_verified() {
		// After the backup is stored, every read of the Elementor data returns something else:
		// neither the new write nor the restore can be verified.
		$garbled = static function ( $check, $object_id, $meta_key ) {
			return '_elementor_data' === $meta_key ? array( 'garbled' ) : $check;
		};
		add_action(
			'added_post_meta',
			static function ( $mid, $id, $key ) use ( $garbled ) {
				if ( Graphina_Import::BACKUP_META === $key ) {
					add_filter( 'get_post_metadata', $garbled, 10, 3 );
				}
			},
			10,
			3
		);
		$import = new Graphina_Import();
		$import->convert_post( $this->post_id, false, $this->log() );
		remove_filter( 'get_post_metadata', $garbled, 10 );

		$this->assertSame( $this->original, get_post_meta( $this->post_id, Graphina_Import::BACKUP_META, true ) );
		$this->assertNotEmpty( preg_grep( '/backup kept/', $this->messages ) );
	}
}
