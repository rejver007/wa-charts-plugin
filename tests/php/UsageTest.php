<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Usage;

class UsageTest extends TestCase {

	public function test_finds_shortcode_block_and_elementor_usage() {
		$chart     = $this->create_chart();
		$short     = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '[wa_chart id="' . $chart . '"]',
			)
		);
		$block     = self::factory()->post->create( array( 'post_content' => '<!-- wp:webaula/chart {"chartId":' . $chart . '} /-->' ) );
		$elementor = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $elementor, '_elementor_data', wp_slash( wp_json_encode( array( array( 'settings' => array( 'shortcode' => '[wa_chart id="' . $chart . '"]' ) ) ) ) ) );
		self::factory()->post->create( array( 'post_content' => '[wa_chart id="' . $chart . '9"]' ) );

		$found = Usage::find( $chart );
		sort( $found );
		$expected = array( $short, $block, $elementor );
		sort( $expected );
		$this->assertSame( $expected, $found );
	}

	public function test_cache_is_invalidated_on_save() {
		$chart = $this->create_chart();
		$this->assertSame( array(), Usage::find( $chart ) );
		$page = self::factory()->post->create( array( 'post_content' => '[wa_chart id=' . $chart . ']' ) );
		$this->assertSame( array( $page ), Usage::find( $chart ) );
	}

	public function test_render_list() {
		$chart = $this->create_chart();
		$this->assertStringContainsString( 'Not used', Usage::render_list( $chart ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		self::factory()->post->create(
			array(
				'post_title'   => 'Home <b>',
				'post_content' => '[wa_chart id="' . $chart . '"]',
			)
		);
		$html = Usage::render_list( $chart );
		$this->assertStringContainsString( 'Home &lt;b&gt;', $html );
		$this->assertStringContainsString( 'post.php?post=', $html );
	}

	private function usage_column( int $chart ): string {
		ob_start();
		\WebAula\Charts\Post_Type::column_content( 'wa_usage', $chart );
		return (string) ob_get_clean();
	}

	public function test_usage_column_zero_shows_dash() {
		$chart = $this->create_chart();
		$this->assertSame( '—', $this->usage_column( $chart ) );
	}

	public function test_usage_column_links_count_to_usage_box() {
		$chart = $this->create_chart();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		self::factory()->post->create( array( 'post_content' => '[wa_chart id="' . $chart . '"]' ) );
		$html = $this->usage_column( $chart );
		$this->assertStringContainsString( '#wa-charts-usage', $html );
		$this->assertStringContainsString( '>1</a>', $html );
	}

	public function test_usage_column_marks_capped_result() {
		$chart = $this->create_chart();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_transient( 'wa_charts_usage_' . $chart . '_' . (int) get_option( Usage::GENERATION_OPTION, 0 ), range( 1, 50 ), HOUR_IN_SECONDS );
		$this->assertStringContainsString( '50+', $this->usage_column( $chart ) );
	}
}
