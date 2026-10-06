<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Block;

class BlockTest extends TestCase {

	public function test_block_is_registered() {
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'webaula/chart' ) );
	}

	public function test_renders_same_markup_as_shortcode() {
		$chart = $this->create_chart();
		$html  = do_blocks( '<!-- wp:webaula/chart {"chartId":' . $chart . ',"height":240,"align":"wide","className":"charts"} /-->' );
		$this->assertStringContainsString( 'id="wa-chart-' . $chart . '-', $html );
		$this->assertStringContainsString( '--wa-chart-height:240px', $html );
		$this->assertStringContainsString( 'charts alignwide', $html );
	}

	public function test_empty_chart_id_renders_nothing() {
		$this->assertSame( '', Block::render( array() ) );
	}
}
