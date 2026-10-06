<?php
namespace WebAula\Charts\Tests;

class ShortcodeTest extends TestCase {

	public function test_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'wa_chart' ) );
	}

	public function test_renders_chart_with_overrides() {
		$id   = $this->create_chart();
		$html = do_shortcode( '[wa_chart id="' . $id . '" height="250" layout="right" class="charts" bogus="1"]' );
		$this->assertStringContainsString( 'id="wa-chart-' . $id . '-', $html );
		$this->assertStringContainsString( '--wa-chart-height:250px', $html );
		$this->assertStringContainsString( 'wa-chart--layout-right', $html );
		$this->assertStringContainsString( ' charts"', $html );
	}

	public function test_bad_id_renders_nothing_for_visitors() {
		wp_set_current_user( 0 );
		$this->assertSame( '', do_shortcode( '[wa_chart id="abc"]' ) );
		$this->assertSame( '', do_shortcode( '[wa_chart]' ) );
	}
}
