<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Renderer;

class RendererTest extends TestCase {

	public function set_up() {
		parent::set_up();
		wp_dequeue_script( Renderer::HANDLE_FRONT );
		wp_dequeue_style( Renderer::HANDLE_FRONT );
	}

	public function tear_down() {
		remove_all_filters( 'wa_charts_library_options' );
		remove_all_filters( 'wa_charts_render_html' );
		wp_dequeue_script( Renderer::HANDLE_FRONT );
		wp_dequeue_script( Renderer::HANDLE_DATALABELS );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function payload_from( string $html ): array {
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="wa-chart__config">(.*?)</script>#s', $html, $m ) );
		return json_decode( $m[1], true );
	}

	public function test_renders_published_chart_structure() {
		$id   = $this->create_chart(
			array(
				'display' => array(
					'heading'    => 'Public<br>budget',
					'subheading' => '9,2M €',
				),
			)
		);
		$html = Renderer::output( $id );
		$this->assertStringContainsString( '<figure class="wa-chart wa-chart--pie wa-chart--layout-left wa-chart--legend-bottom"', $html );
		$this->assertMatchesRegularExpression( '/id="wa-chart-' . $id . '-\d+"/', $html );
		$this->assertStringContainsString( '--wa-chart-height:320px', $html );
		$this->assertStringContainsString( '<canvas role="img" aria-label="Public budget"></canvas>', $html );
		$this->assertStringContainsString( '<span class="wa-chart__heading">Public<br>budget</span>', $html );
		$this->assertStringContainsString( '<span class="wa-chart__subheading">9,2M €</span>', $html );
		$this->assertStringContainsString( '<table class="wa-chart__table screen-reader-text">', $html );
		$this->assertStringContainsString( '<th scope="row">UVA</th><td>3,65</td>', $html );

		$payload = $this->payload_from( $html );
		$this->assertSame( 'pie', $payload['type'] );
		$this->assertSame( array( 3.65, 1.6 ), $payload['series'][0]['values'] );
		$this->assertSame( array( '#1A98D1', '#08588C' ), $payload['pointColors'] );
	}

	public function test_omits_caption_without_text() {
		$this->assertStringNotContainsString( 'figcaption', Renderer::output( $this->create_chart() ) );
	}

	public function test_instance_ids_are_unique() {
		$id = $this->create_chart();
		preg_match( '/id="(wa-chart-[\d-]+)"/', Renderer::output( $id ), $a );
		preg_match( '/id="(wa-chart-[\d-]+)"/', Renderer::output( $id ), $b );
		$this->assertNotSame( $a[1], $b[1] );
	}

	public function test_labels_cannot_break_out_of_json() {
		$id   = $this->create_chart( array( 'labels' => array( 'x</script><img src=x onerror=alert(1)>', 'y' ) ) );
		$html = Renderer::output( $id );
		$this->assertSame( 1, substr_count( $html, '</script>' ) );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_unsanitized_payload_values_are_escaped_in_json() {
		$hostile = '</script><img src=x onerror=alert(1)>&\'"';
		add_filter(
			'wa_charts_library_options',
			static fn() => array( 'x' => $hostile )
		);
		$html = Renderer::output( $this->create_chart() );
		$this->assertSame( 1, substr_count( $html, '</script>' ) );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'onerror=alert(1)>', $html );
		$this->assertSame( $hostile, $this->payload_from( $html )['libraryOverrides']['x'] );
	}

	public function test_overrides_are_validated() {
		$html = Renderer::output(
			$this->create_chart(),
			array(
				'height' => '50',
				'layout' => 'top',
				'legend' => 'evil',
				'class'  => 'my-class foo" onmouseover="x',
			)
		);
		$this->assertStringContainsString( '--wa-chart-height:100px', $html );
		$this->assertStringContainsString( 'wa-chart--layout-top', $html );
		$this->assertStringContainsString( 'wa-chart--legend-bottom', $html );
		$this->assertStringContainsString( 'my-class', $html );
		$this->assertStringNotContainsString( 'onmouseover="', $html );
	}

	public function test_visibility_rules() {
		$draft = $this->create_chart( array(), 'draft' );
		$post  = self::factory()->post->create();

		wp_set_current_user( 0 );
		$this->assertSame( '', Renderer::output( $draft ) );
		$this->assertSame( '', Renderer::output( 999999 ) );
		$this->assertSame( '', Renderer::output( $post ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringContainsString( '<figure', Renderer::output( $draft ) );
		$this->assertStringContainsString( 'wa-chart-notice', Renderer::output( 999999 ) );
	}

	public function test_assets_are_enqueued_only_when_rendering() {
		$this->assertFalse( wp_script_is( Renderer::HANDLE_FRONT, 'enqueued' ) );
		Renderer::output( $this->create_chart() );
		$this->assertTrue( wp_script_is( Renderer::HANDLE_FRONT, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Renderer::HANDLE_FRONT, 'enqueued' ) );
		$this->assertFalse( wp_script_is( Renderer::HANDLE_DATALABELS, 'enqueued' ) );

		Renderer::output( $this->create_chart( array( 'display' => array( 'data_labels' => true ) ) ) );
		$this->assertContains( Renderer::HANDLE_DATALABELS, wp_scripts()->query( Renderer::HANDLE_FRONT )->deps );
	}

	public function test_filters() {
		add_filter( 'wa_charts_library_options', static fn() => array( 'plugins' => array( 'tooltip' => array( 'enabled' => false ) ) ) );
		add_filter( 'wa_charts_render_html', static fn( $html ) => '<div class="wrap">' . $html . '</div>' );
		$html = Renderer::output( $this->create_chart() );
		$this->assertStringStartsWith( '<div class="wrap">', $html );
		$this->assertFalse( $this->payload_from( $html )['libraryOverrides']['plugins']['tooltip']['enabled'] );
	}

	public function test_payload_type_options_is_object_even_when_empty() {
		$config = \WebAula\Charts\Chart_Data::sanitize(
			array(
				'type'   => 'polar',
				'labels' => array( 'a' ),
				'series' => array( array( 'values' => array( 1 ) ) ),
			)
		)['config'];
		$json   = wp_json_encode( Renderer::client_payload( $config, 0 ) );
		$this->assertStringContainsString( '"typeOptions":{}', $json );
		$this->assertStringContainsString( '"libraryOverrides":{}', $json );
	}
}
