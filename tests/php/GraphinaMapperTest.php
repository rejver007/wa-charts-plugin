<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Cli\Graphina_Mapper;

class GraphinaMapperTest extends TestCase {

	private function fixture( string $name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.
		return json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name . '.json' ), true );
	}

	public function test_detects_graphina_widgets_only() {
		$this->assertTrue(
			Graphina_Mapper::is_graphina(
				array(
					'widgetType' => 'pie_chart',
					'settings'   => array( 'iq_pie_chart_heading' => 'x' ),
				)
			)
		);
		$this->assertFalse(
			Graphina_Mapper::is_graphina(
				array(
					'widgetType' => 'counter',
					'settings'   => array( 'iq_x' => 1 ),
				)
			)
		);
		$this->assertFalse(
			Graphina_Mapper::is_graphina(
				array(
					'widgetType' => 'my_chart',
					'settings'   => array( 'title' => 'x' ),
				)
			)
		);
	}

	public function test_maps_public_research_chart() {
		$result = Graphina_Mapper::map( 'pie_chart', $this->fixture( 'graphina-public-research' ) );
		$config = $result['config'];

		$this->assertSame( 'Public research project budget', $result['title'] );
		$this->assertSame( 'pie', $config['type'] );
		$this->assertSame( array( 'UVA', 'Aalto', 'VTT', 'ÅAU', 'TAU', 'LUT', 'UOULU', 'UTU' ), $config['labels'] );
		$this->assertSame( array( 3.65, 1.6, 1.46, 0.74, 0.5, 0.41, 0.68, 0.16 ), $config['series'][0]['values'] );
		$this->assertSame( array( '#1A98D1', '#08588C', '#113C56', '#A1BF23', '#D3E0E6', '#4B4C4B', '#333333', '#000000' ), $config['point_colors'] );
		$this->assertSame( 'Public research<br> project budget', $config['display']['heading'] );
		$this->assertSame( '9,2M €', $config['display']['subheading'] );
		$this->assertSame( 'left', $config['display']['layout'] );
		$this->assertSame(
			array(
				'position'    => 'bottom',
				'columns'     => 2,
				'show_values' => true,
			),
			$config['display']['legend']
		);
		$this->assertSame(
			array(
				'prefix'   => '',
				'suffix'   => 'M€',
				'decimals' => 2,
				'locale'   => 'en-US',
			),
			$config['display']['value']
		);
		$this->assertSame( 'IBM Plex Sans', $config['display']['font_family'] );
		$this->assertStringContainsString( '42px', $result['custom_css'] );
		$this->assertCount( 2, $result['flags'] );
	}

	public function test_maps_two_slice_chart_with_one_legend_column() {
		$result = Graphina_Mapper::map( 'pie_chart', $this->fixture( 'graphina-total-budget' ) );
		$this->assertSame( array( 'Public research project', 'Companies budget' ), $result['config']['labels'] );
		$this->assertSame( 1, $result['config']['display']['legend']['columns'] );
		$this->assertSame( 1, $result['config']['display']['value']['decimals'] );
	}

	public function test_flags_missing_values_and_colours() {
		$result = Graphina_Mapper::map(
			'donut_chart',
			array(
				'iq_donut_chart_data_series_count' => 2,
				'iq_donut_chart_label0'            => 'A',
				'iq_donut_chart_value0'            => 1,
				'iq_donut_chart_label1'            => 'B',
			)
		);
		$this->assertSame( 'donut', $result['config']['type'] );
		$this->assertSame( array( 1.0, 0.0 ), $result['config']['series'][0]['values'] );
		$this->assertCount( 3, $result['flags'] );
	}

	public function test_unsupported_type_returns_null() {
		$this->assertNull( Graphina_Mapper::map( 'column_chart', array( 'iq_column_chart_heading' => 'x' ) ) );
	}

	public function test_convert_tree_replaces_nested_widgets_and_reports() {
		$settings = $this->fixture( 'graphina-public-research' );
		$tree     = array(
			array(
				'id'       => 'sec1',
				'elType'   => 'section',
				'elements' => array(
					array(
						'id'       => 'col1',
						'elType'   => 'column',
						'elements' => array(
							array(
								'id'         => 'w1',
								'elType'     => 'widget',
								'widgetType' => 'pie_chart',
								'settings'   => $settings,
								'elements'   => array(),
							),
							array(
								'id'         => 'w2',
								'elType'     => 'widget',
								'widgetType' => 'column_chart',
								'settings'   => array( 'iq_column_chart_heading' => 'Bars' ),
								'elements'   => array(),
							),
							array(
								'id'         => 'w3',
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => array( 'title' => 'Hi' ),
								'elements'   => array(),
							),
						),
					),
				),
			),
		);
		$report   = array();
		$created  = array();
		$result   = Graphina_Mapper::convert_tree(
			$tree,
			static function ( array $config, string $title ) use ( &$created ) {
				$created[] = $title;
				return 77;
			},
			$report
		);

		$widget = $result[0]['elements'][0]['elements'][0];
		$this->assertSame( 'w1', $widget['id'] );
		$this->assertSame( 'shortcode', $widget['widgetType'] );
		$this->assertSame( '[wa_chart id="77"]', $widget['settings']['shortcode'] );
		$this->assertSame( 'charts', $widget['settings']['_css_classes'] );
		$this->assertArrayHasKey( 'hide_mobile', $widget['settings'] );
		$this->assertArrayNotHasKey( 'custom_css', $widget['settings'] );
		$this->assertArrayNotHasKey( 'iq_pie_chart_heading', $widget['settings'] );
		$this->assertSame( array(), $widget['settings']['__globals__'] );

		$this->assertSame( 'column_chart', $result[0]['elements'][0]['elements'][1]['widgetType'] );
		$this->assertSame( 'heading', $result[0]['elements'][0]['elements'][2]['widgetType'] );
		$this->assertSame( array( 'Public research project budget' ), $created );
		$this->assertSame( array( 'converted', 'manual' ), array_column( $report, 'status' ) );
	}
}
