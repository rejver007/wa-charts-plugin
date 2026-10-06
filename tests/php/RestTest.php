<?php
namespace WebAula\Charts\Tests;

use WP_REST_Request;

class RestTest extends TestCase {

	private function request( array $body ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wa-charts/v1/preview' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_get_server()->dispatch( $request );
	}

	private function config(): array {
		return array(
			'type'   => 'pie',
			'labels' => array( 'A', 'B' ),
			'series' => array( array( 'values' => array( '1,5', 'x' ) ) ),
		);
	}

	public function test_requires_chart_capability() {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( array( 'config' => $this->config() ) )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 403, $this->request( array( 'config' => $this->config() ) )->get_status() );
	}

	public function test_returns_sanitized_payload_and_errors() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = $this->request( array( 'config' => $this->config() ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( array( 1.5, 0.0 ), $data['payload']['series'][0]['values'] );
		$this->assertCount( 1, $data['errors'] );
		$this->assertSame( 1, $data['config']['schema'] );
	}

	public function test_rejects_oversized_body() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$config           = $this->config();
		$config['labels'] = array_fill( 0, 400, str_repeat( 'x', 600 ) );
		$this->assertSame( 413, $this->request( array( 'config' => $config ) )->get_status() );
	}

	public function test_saves_nothing() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$chart  = $this->create_chart();
		$before = get_post_meta( $chart, \WebAula\Charts\Chart_Data::META_KEY, true );
		$this->request(
			array(
				'config'  => $this->config(),
				'post_id' => $chart,
			)
		);
		$this->assertSame( $before, get_post_meta( $chart, \WebAula\Charts\Chart_Data::META_KEY, true ) );
	}
}
