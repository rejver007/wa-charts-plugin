<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Settings;
use WebAula\Charts\Updater;
use WebAula\Charts\Upgrader;

class LifecycleTest extends TestCase {

	public function test_delete_option_is_registered_and_off_by_default() {
		Settings::register_setting();
		$this->assertArrayHasKey( Settings::OPTION_DELETE, get_registered_settings() );
		$this->assertFalse( (bool) get_option( Settings::OPTION_DELETE ) );
	}

	public function test_upgrader_rewrites_old_configs_in_batches() {
		$a = $this->create_chart();
		$b = $this->create_chart();
		update_post_meta(
			$a,
			Chart_Data::META_KEY,
			wp_slash(
				wp_json_encode(
					array(
						'type'   => 'pie',
						'labels' => array( 'x' ),
					)
				)
			)
		);
		update_option( Upgrader::OPTION_SCHEMA, 0 );

		$this->assertTrue( Upgrader::run_batch() );
		$stored = json_decode( get_post_meta( $a, Chart_Data::META_KEY, true ), true );
		$this->assertSame( Chart_Data::SCHEMA, $stored['schema'] );
		$this->assertSame( Chart_Data::SCHEMA, (int) get_option( Upgrader::OPTION_SCHEMA ) );
		$this->assertFalse( get_option( Upgrader::OPTION_CURSOR ) );
		$this->assertNotEmpty( get_post_meta( $b, Chart_Data::META_KEY, true ) );
	}

	public function test_upgrader_leaves_undecodable_data_untouched() {
		$corrupt = $this->create_chart();
		$valid   = $this->create_chart();
		update_post_meta( $corrupt, Chart_Data::META_KEY, wp_slash( '{not json' ) );
		update_post_meta(
			$valid,
			Chart_Data::META_KEY,
			wp_slash(
				wp_json_encode(
					array(
						'type'   => 'pie',
						'labels' => array( 'x' ),
					)
				)
			)
		);
		update_option( Upgrader::OPTION_SCHEMA, 0 );

		Upgrader::run_batch();

		$this->assertSame( '{not json', get_post_meta( $corrupt, Chart_Data::META_KEY, true ) );
		$stored = json_decode( get_post_meta( $valid, Chart_Data::META_KEY, true ), true );
		$this->assertSame( 1, $stored['schema'] );
	}

	public function test_updater_needs_only_the_library() {
		$this->assertTrue( Updater::should_boot( true ) );
		$this->assertFalse( Updater::should_boot( false ) );
	}

	public function test_updater_token_is_optional() {
		$this->assertNull( Updater::auth_token( null ) );
		$this->assertNull( Updater::auth_token( '' ) );
		$this->assertNull( Updater::auth_token( false ) );
		$this->assertSame( 'token', Updater::auth_token( 'token' ) );
	}

	public function test_uninstall_respects_option() {
		$chart = $this->create_chart();
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wa-charts/wa-charts.php' );
		}

		include WA_CHARTS_DIR . 'uninstall.php';
		$this->assertNotNull( get_post( $chart ) );

		update_option( Settings::OPTION_DELETE, true );
		include WA_CHARTS_DIR . 'uninstall.php';
		$this->assertNull( get_post( $chart ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'edit_wa_charts' ) );
	}
}
