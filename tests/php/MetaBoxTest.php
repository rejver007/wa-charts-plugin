<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Meta_Box;

class MetaBoxTest extends TestCase {

	private int $chart;

	public function set_up() {
		parent::set_up();
		$this->chart = $this->create_chart();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	public function tear_down() {
		$_POST = array();
		delete_transient( Meta_Box::error_key() );
		parent::tear_down();
	}

	private function post( array $config, ?string $nonce = null ): void {
		$_POST[ Meta_Box::NONCE_NAME ] = $nonce ?? wp_create_nonce( Meta_Box::NONCE_ACTION );
		$_POST[ Meta_Box::FIELD ]      = wp_slash( wp_json_encode( $config ) );
	}

	public function test_saves_sanitized_config() {
		$this->post(
			array(
				'type'   => 'bar',
				'labels' => array( 'Q1' ),
				'series' => array(
					array(
						'name'   => 'S',
						'values' => array( '2,5' ),
					),
				),
			)
		);
		Meta_Box::save( $this->chart );
		$config = Chart_Data::get( $this->chart );
		$this->assertSame( 'bar', $config['type'] );
		$this->assertSame( array( 2.5 ), $config['series'][0]['values'] );
		$this->assertFalse( get_transient( Meta_Box::error_key() ) );
	}

	public function test_invalid_nonce_is_ignored() {
		$before = Chart_Data::get( $this->chart );
		$this->post( array( 'type' => 'bar' ), 'bad' );
		Meta_Box::save( $this->chart );
		$this->assertSame( $before, Chart_Data::get( $this->chart ) );
	}

	public function test_user_without_capability_is_ignored() {
		$before = Chart_Data::get( $this->chart );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->post( array( 'type' => 'bar' ) );
		Meta_Box::save( $this->chart );
		$this->assertSame( $before, Chart_Data::get( $this->chart ) );
	}

	public function test_errors_are_stored_for_notice() {
		$this->post(
			array(
				'type'   => 'pie',
				'labels' => array( 'A' ),
				'series' => array( array( 'values' => array( 'abc' ) ) ),
			)
		);
		Meta_Box::save( $this->chart );
		$this->assertCount( 1, get_transient( Meta_Box::error_key() ) );

		ob_start();
		Meta_Box::notices();
		$this->assertStringContainsString( 'abc', ob_get_clean() );
		$this->assertFalse( get_transient( Meta_Box::error_key() ) );
	}

	public function test_oversized_config_is_rejected_with_error() {
		$before = Chart_Data::get( $this->chart );
		$this->post(
			array(
				'type'   => 'bar',
				'labels' => array_fill( 0, 500, str_repeat( 'x', 200 ) ),
				'series' => array_fill( 0, 3, array( 'values' => array_fill( 0, 500, 123456.123456 ) ) ),
			)
		);
		Meta_Box::save( $this->chart );
		$this->assertSame( $before, Chart_Data::get( $this->chart ) );
		$this->assertNotEmpty( get_transient( Meta_Box::error_key() ) );
	}

	public function test_type_box_outputs_nonce_and_config() {
		ob_start();
		Meta_Box::render_type_box( get_post( $this->chart ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="' . Meta_Box::NONCE_NAME . '"', $html );
		$this->assertStringContainsString( 'id="wa-chart-config-input"', $html );
		$this->assertStringContainsString( '&quot;UVA&quot;', $html );
	}

	public function test_editor_settings() {
		$settings = Meta_Box::editor_settings( $this->chart );
		$this->assertSame( $this->chart, $settings['postId'] );
		$this->assertArrayHasKey( 'radial', $settings['types'] );
		$this->assertSame( '/wa-charts/v1/preview', $settings['previewPath'] );
		$this->assertSame(
			array(
				'labels' => 500,
				'series' => 20,
			),
			$settings['limits']
		);
	}

	public function test_editor_settings_script_escapes_markup() {
		add_filter(
			'wa_charts_chart_types',
			static function ( $types ) {
				$types['pie']['label'] = '</script><b>x</b>';
				return $types;
			}
		);
		$script = Meta_Box::editor_settings_script( $this->chart );
		$this->assertStringStartsWith( 'window.waChartsEditor = ', $script );
		$this->assertStringNotContainsString( '</script>', $script );
		$this->assertStringNotContainsString( '<b>', $script );
	}

	public function test_unreadable_input_does_not_overwrite_saved_data() {
		$before                        = Chart_Data::get( $this->chart );
		$_POST[ Meta_Box::NONCE_NAME ] = wp_create_nonce( Meta_Box::NONCE_ACTION );
		$_POST[ Meta_Box::FIELD ]      = '{not json';
		Meta_Box::save( $this->chart );
		$this->assertSame( $before, Chart_Data::get( $this->chart ) );
		$this->assertCount( 1, get_transient( Meta_Box::error_key() ) );
	}
}
