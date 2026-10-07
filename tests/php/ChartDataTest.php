<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Post_Type;

class ChartDataTest extends TestCase {

	private function pie( array $overrides = array() ): array {
		return array_replace_recursive(
			array(
				'type'         => 'pie',
				'labels'       => array( 'UVA', 'Aalto' ),
				'series'       => array(
					array(
						'name'   => 'Budget',
						'values' => array( 3.65, 1.6 ),
					),
				),
				'point_colors' => array( '#1A98D1', '#08588C' ),
			),
			$overrides
		);
	}

	public function test_defaults() {
		$d = Chart_Data::defaults();
		$this->assertSame( 1, $d['schema'] );
		$this->assertSame( 'pie', $d['type'] );
		$this->assertSame( 'left', $d['display']['layout'] );
		$this->assertSame( 320, $d['display']['height'] );
		$this->assertSame( array( 'start_angle' => 0 ), $d['type_options'] );
		$this->assertSame( 'pie', Chart_Data::defaults( 'nope' )['type'] );
	}

	public function test_non_array_input_resets_with_error() {
		$r = Chart_Data::sanitize( 'garbage' );
		$this->assertSame( Chart_Data::defaults(), $r['config'] );
		$this->assertCount( 1, $r['errors'] );
	}

	public function test_unknown_type_falls_back_to_pie() {
		$r = Chart_Data::sanitize( $this->pie( array( 'type' => 'heatmap' ) ) );
		$this->assertSame( 'pie', $r['config']['type'] );
		$this->assertNotEmpty( $r['errors'] );
	}

	public function test_valid_pie_round_trips_without_errors() {
		$r = Chart_Data::sanitize( $this->pie() );
		$this->assertSame( array(), $r['errors'] );
		$this->assertSame( array( 'UVA', 'Aalto' ), $r['config']['labels'] );
		$this->assertSame( array( 3.65, 1.6 ), $r['config']['series'][0]['values'] );
		$this->assertSame( array( '#1A98D1', '#08588C' ), $r['config']['point_colors'] );
		$this->assertNull( $r['config']['series'][0]['color'] );
	}

	public function test_labels_are_stripped_and_truncated() {
		$r = Chart_Data::sanitize( $this->pie( array( 'labels' => array( '<b>UVA</b><script>x</script>', str_repeat( 'a', 300 ) ) ) ) );
		$this->assertSame( 'UVA', $r['config']['labels'][0] );
		$this->assertSame( 200, mb_strlen( $r['config']['labels'][1] ) );
	}

	public function test_values_accept_decimal_comma_and_flag_invalid() {
		$r = Chart_Data::sanitize( $this->pie( array( 'series' => array( array( 'values' => array( '3,65', 'abc' ) ) ) ) ) );
		$this->assertSame( array( 3.65, 0.0 ), $r['config']['series'][0]['values'] );
		$this->assertCount( 1, $r['errors'] );
	}

	public function test_missing_values_become_zero_without_error() {
		$raw           = $this->pie();
		$raw['series'] = array(
			array(
				'name'   => 'Budget',
				'values' => array( 1 ),
			),
		);
		$r             = Chart_Data::sanitize( $raw );
		$this->assertSame( array( 1.0, 0.0 ), $r['config']['series'][0]['values'] );
		$this->assertSame( array(), $r['errors'] );
	}

	public function test_single_shape_keeps_extra_series_and_fills_colours() {
		$raw                 = $this->pie();
		$raw['series'][]     = array(
			'name'   => 'Extra',
			'values' => array( 1, 2 ),
		);
		$raw['point_colors'] = array( 'not-a-colour' );
		$r                   = Chart_Data::sanitize( $raw );
		$this->assertCount( 2, $r['config']['series'] );
		$this->assertSame( 'Extra', $r['config']['series'][1]['name'] );
		$this->assertSame( array( 1.0, 2.0 ), $r['config']['series'][1]['values'] );
		$this->assertSame( array( '#1A98D1', '#08588C' ), $r['config']['point_colors'] );
		$this->assertCount( 1, $r['errors'] );
	}

	public function test_type_switch_round_trip_loses_nothing() {
		$labels = array();
		$first  = array();
		$second = array();
		for ( $i = 0; $i < 8; $i++ ) {
			$labels[] = 'Row ' . $i;
			$first[]  = $i + 1;
			$second[] = ( $i + 1 ) * 10;
		}
		$raw = array(
			'type'         => 'pie',
			'labels'       => $labels,
			'series'       => array(
				array(
					'name'   => 'A',
					'values' => $first,
				),
				array(
					'name'   => 'B',
					'values' => $second,
					'color'  => '#ff0000',
					'render' => 'line',
				),
			),
			'point_colors' => array( '#111111', '#222222' ),
		);
		$pie = Chart_Data::sanitize( $raw );
		$this->assertSame( array(), $pie['errors'] );
		$gauge = Chart_Data::sanitize( array_merge( $pie['config'], array( 'type' => 'radial' ) ) );
		$this->assertSame( array(), $gauge['errors'] );
		$this->assertCount( 8, $gauge['config']['labels'] );
		$back = Chart_Data::sanitize( array_merge( $gauge['config'], array( 'type' => 'pie' ) ) );
		$this->assertSame( $pie['config']['labels'], $back['config']['labels'] );
		$this->assertSame( $pie['config']['series'], $back['config']['series'] );
		$this->assertSame( $pie['config']['point_colors'], $back['config']['point_colors'] );
		$this->assertCount( 8, $back['config']['point_colors'] );
	}

	public function test_legacy_pie_config_passes_through_unchanged() {
		$legacy = array(
			'schema'       => 1,
			'type'         => 'pie',
			'labels'       => array( 'A', 'B' ),
			'series'       => array(
				array(
					'name'   => 'Budget',
					'color'  => null,
					'render' => null,
					'values' => array( 1.0, 2.0 ),
				),
			),
			'point_colors' => array( '#111111', '#222222' ),
		);
		$config = Chart_Data::sanitize( $legacy )['config'];
		$this->assertSame( $legacy['series'], $config['series'] );
		$this->assertSame( $legacy['point_colors'], $config['point_colors'] );

		$id = self::factory()->post->create( array( 'post_type' => Post_Type::SLUG ) );
		update_post_meta( $id, Chart_Data::META_KEY, wp_slash( wp_json_encode( $legacy ) ) );
		$stored = Chart_Data::get( $id );
		$this->assertSame( $legacy['series'], $stored['series'] );
		$this->assertSame( $legacy['point_colors'], $stored['point_colors'] );
	}

	public function test_legacy_bar_config_keeps_series_and_render() {
		$legacy = array(
			'schema'       => 1,
			'type'         => 'bar',
			'labels'       => array( 'Q1', 'Q2' ),
			'series'       => array(
				array(
					'name'   => 'Sales',
					'color'  => '#ff0000',
					'render' => null,
					'values' => array( 1.0, 2.0 ),
				),
				array(
					'name'   => 'Cost',
					'color'  => '#00ff00',
					'render' => null,
					'values' => array( 3.0, 4.0 ),
				),
			),
			'point_colors' => array(),
		);
		$config = Chart_Data::sanitize( $legacy )['config'];
		$this->assertSame( $legacy['series'], $config['series'] );
		$this->assertSame( 'bar', $config['type'] );

		$id = self::factory()->post->create( array( 'post_type' => Post_Type::SLUG ) );
		update_post_meta( $id, Chart_Data::META_KEY, wp_slash( wp_json_encode( $legacy ) ) );
		$this->assertSame( $legacy['series'], Chart_Data::get( $id )['series'] );
	}

	public function test_eight_digit_hex_is_allowed() {
		$r = Chart_Data::sanitize( $this->pie( array( 'point_colors' => array( '#02010100', '#08588C' ) ) ) );
		$this->assertSame( '#02010100', $r['config']['point_colors'][0] );
	}

	public function test_multi_shape_series_colours_and_render() {
		$raw = array(
			'type'   => 'mixed',
			'labels' => array( 'Q1', 'Q2' ),
			'series' => array(
				array(
					'name'   => 'Sales',
					'values' => array( 1, 2 ),
					'render' => 'line',
				),
				array(
					'name'   => 'Cost',
					'values' => array( 3, 4 ),
					'color'  => '#ff0000',
					'render' => 'pie',
				),
			),
		);
		$c   = Chart_Data::sanitize( $raw )['config'];
		$this->assertSame( '#1A98D1', $c['series'][0]['color'] );
		$this->assertSame( '#ff0000', $c['series'][1]['color'] );
		$this->assertSame( 'line', $c['series'][0]['render'] );
		$this->assertSame( 'bar', $c['series'][1]['render'] );
		$this->assertSame( array( '#1A98D1', '#08588C' ), $c['point_colors'] );

		$raw['type'] = 'bar';
		$this->assertSame( 'line', Chart_Data::sanitize( $raw )['config']['series'][0]['render'] );
		$this->assertNull( Chart_Data::sanitize( $raw )['config']['series'][1]['render'] );
		unset( $raw['series'][0]['render'] );
		$this->assertNull( Chart_Data::sanitize( $raw )['config']['series'][0]['render'] );
	}

	public function test_row_and_series_limits() {
		$r = Chart_Data::sanitize( $this->pie( array( 'labels' => array_fill( 0, 501, 'x' ) ) ) );
		$this->assertCount( 500, $r['config']['labels'] );
		$this->assertNotEmpty( $r['errors'] );

		$r = Chart_Data::sanitize(
			array(
				'type'   => 'bar',
				'labels' => array( 'a' ),
				'series' => array_fill( 0, 21, array( 'values' => array( 1 ) ) ),
			)
		);
		$this->assertCount( 20, $r['config']['series'] );
		$this->assertNotEmpty( $r['errors'] );

		$r = Chart_Data::sanitize( $this->pie( array( 'type' => 'radial' ) ) );
		$this->assertSame( array( 'UVA', 'Aalto' ), $r['config']['labels'] );
		$this->assertSame( array(), $r['errors'] );
	}

	public function test_display_sanitizing() {
		$r = Chart_Data::sanitize(
			$this->pie(
				array(
					'display' => array(
						'heading'     => 'Public research<br> <span onclick="x()">project</span> <script>alert(1)</script>',
						'layout'      => 'diagonal',
						'height'      => 99999,
						'legend'      => array(
							'position'    => 'top',
							'columns'     => 5,
							'show_values' => 'yes',
						),
						'value'       => array(
							'prefix'   => ' ',
							'suffix'   => ' M€',
							'decimals' => 9,
							'locale'   => 'xx',
						),
						'font_family' => 'IBM Plex Sans; } body { color: red',
					),
				)
			)
		);
		$d = $r['config']['display'];
		$this->assertSame( 'Public research<br> <span>project</span> alert(1)', $d['heading'] );
		$this->assertSame( 'left', $d['layout'] );
		$this->assertSame( 2000, $d['height'] );
		$this->assertSame(
			array(
				'position'    => 'bottom',
				'columns'     => 2,
				'show_values' => true,
				'on_click'    => 'toggle',
			),
			$d['legend']
		);
		$this->assertSame( ' M€', $d['value']['suffix'] );
		$this->assertSame( ' ', $d['value']['prefix'] );
		$this->assertSame( 6, $d['value']['decimals'] );
		$this->assertSame( 'fi-FI', $d['value']['locale'] );
		$this->assertSame( 'IBM Plex Sans  body  color red', $d['font_family'] );
	}

	public function test_label_format_defaults_to_value() {
		$this->assertSame( 'value', Chart_Data::default_display()['label_format'] );
		$d = Chart_Data::sanitize( $this->pie() )['config']['display'];
		$this->assertSame( 'value', $d['label_format'] );
	}

	public function test_label_format_percent_is_kept() {
		$r = Chart_Data::sanitize( $this->pie( array( 'display' => array( 'label_format' => 'percent' ) ) ) );
		$this->assertSame( 'percent', $r['config']['display']['label_format'] );
	}

	public function test_invalid_label_format_falls_back_to_value() {
		$r = Chart_Data::sanitize( $this->pie( array( 'display' => array( 'label_format' => 'ratio' ) ) ) );
		$this->assertSame( 'value', $r['config']['display']['label_format'] );
	}

	public function test_legacy_config_without_label_format_gets_value() {
		$r = Chart_Data::sanitize( $this->pie( array( 'display' => array( 'data_labels' => true ) ) ) );
		$this->assertSame( 'value', $r['config']['display']['label_format'] );
		$this->assertTrue( $r['config']['display']['data_labels'] );
	}

	public function test_legend_click_defaults_to_toggle() {
		$this->assertSame( 'toggle', Chart_Data::default_display()['legend']['on_click'] );
		$d = Chart_Data::sanitize( $this->pie() )['config']['display'];
		$this->assertSame( 'toggle', $d['legend']['on_click'] );
	}

	public function test_legend_click_highlight_is_kept() {
		$r = Chart_Data::sanitize( $this->pie( array( 'display' => array( 'legend' => array( 'on_click' => 'highlight' ) ) ) ) );
		$this->assertSame( 'highlight', $r['config']['display']['legend']['on_click'] );
	}

	public function test_invalid_legend_click_falls_back_to_toggle() {
		$r = Chart_Data::sanitize( $this->pie( array( 'display' => array( 'legend' => array( 'on_click' => 'explode' ) ) ) ) );
		$this->assertSame( 'toggle', $r['config']['display']['legend']['on_click'] );
	}

	public function test_type_options_are_clamped() {
		$c = Chart_Data::sanitize(
			array(
				'type'         => 'donut',
				'labels'       => array( 'a' ),
				'series'       => array( array( 'values' => array( 1 ) ) ),
				'type_options' => array(
					'hole_size'   => 5,
					'start_angle' => '45',
					'bogus'       => 1,
				),
			)
		)['config'];
		$this->assertSame(
			array(
				'hole_size'   => 30,
				'start_angle' => 45,
			),
			$c['type_options']
		);

		$r = Chart_Data::sanitize(
			array(
				'type'         => 'radial',
				'labels'       => array( 'a' ),
				'series'       => array( array( 'values' => array( 1 ) ) ),
				'type_options' => array(
					'max'         => -5,
					'track_color' => 'nope',
				),
			)
		);
		$this->assertSame( 0.000001, $r['config']['type_options']['max'] );
		$this->assertSame( '#E5E7EB', $r['config']['type_options']['track_color'] );
		$this->assertNotEmpty( $r['errors'] );

		$c = Chart_Data::sanitize(
			array(
				'type'         => 'line',
				'labels'       => array( 'a' ),
				'series'       => array( array( 'values' => array( 1 ) ) ),
				'type_options' => array(
					'curve'   => 'zigzag',
					'markers' => '0',
				),
			)
		)['config'];
		$this->assertSame(
			array(
				'curve'   => 'smooth',
				'markers' => false,
			),
			$c['type_options']
		);
	}

	public function test_infinite_value_string_becomes_zero_with_error() {
		$raw           = $this->pie();
		$raw['series'] = array(
			array(
				'name'   => 'Budget',
				'values' => array( '1e999', 2 ),
			),
		);
		$r             = Chart_Data::sanitize( $raw );
		$this->assertSame( array( 0.0, 2.0 ), $r['config']['series'][0]['values'] );
		$this->assertCount( 1, $r['errors'] );
	}

	public function test_long_heading_is_not_cut_inside_a_tag() {
		$raw            = $this->pie();
		$raw['display'] = array( 'heading' => str_repeat( 'a', 498 ) . '<strong>bold</strong>' );
		$heading        = Chart_Data::sanitize( $raw )['config']['display']['heading'];
		$this->assertDoesNotMatchRegularExpression( '/<[^>]*$/', $heading );
		$this->assertSame( substr_count( $heading, '<strong>' ), substr_count( $heading, '</strong>' ) );
	}

	public function test_migrate_sets_schema() {
		$this->assertSame( 1, Chart_Data::migrate( array( 'type' => 'pie' ) )['schema'] );
	}

	public function test_save_and_get_round_trip() {
		$id     = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$config = Chart_Data::sanitize( $this->pie( array( 'labels' => array( 'Quote " and \\ slash', 'Ä' ) ) ) )['config'];
		$this->assertTrue( Chart_Data::save( $id, $config ) );
		$this->assertSame( $config, Chart_Data::get( $id ) );
	}

	public function test_save_refuses_oversized_payload() {
		$id = self::factory()->post->create();
		$this->assertFalse( Chart_Data::save( $id, array( 'labels' => array( str_repeat( 'x', 110000 ) ) ) ) );
		$this->assertSame( '', get_post_meta( $id, Chart_Data::META_KEY, true ) );
	}

	public function test_get_with_corrupt_meta_returns_defaults() {
		$id = self::factory()->post->create();
		update_post_meta( $id, Chart_Data::META_KEY, '{not json' );
		$this->assertSame( Chart_Data::defaults(), Chart_Data::get( $id ) );
	}

	public function test_drops_empty_rows_but_keeps_explicit_zero() {
		$result = Chart_Data::sanitize(
			array(
				'type'         => 'pie',
				'labels'       => array( '', 'A', '' ),
				'series'       => array(
					array(
						'name'   => 'S',
						'values' => array( null, 1, '0' ),
					),
				),
				'point_colors' => array( '#111111', '#222222', '#333333' ),
			)
		)['config'];
		$this->assertSame( array( 'A', '' ), $result['labels'] );
		$this->assertSame( array( 1.0, 0.0 ), $result['series'][0]['values'] );
		$this->assertSame( array( '#222222', '#333333' ), $result['point_colors'] );
	}

	public function test_all_empty_rows_give_no_labels() {
		$result = Chart_Data::sanitize(
			array(
				'type'   => 'pie',
				'labels' => array( '', ' ' ),
				'series' => array( array( 'values' => array( '', null ) ) ),
			)
		)['config'];
		$this->assertSame( array(), $result['labels'] );
	}

	public function test_lone_less_than_survives_but_tags_are_stripped() {
		$result = Chart_Data::sanitize( $this->pie( array( 'labels' => array( '<5 %', '<b>x</b>' ) ) ) )['config'];
		$this->assertSame( '<5 %', $result['labels'][0] );
		$this->assertSame( 'x', $result['labels'][1] );
	}

	public function test_eight_digit_hex_with_trailing_newline_is_rejected() {
		$result = Chart_Data::sanitize( $this->pie( array( 'point_colors' => array( "#12345678\n", '#12345678' ) ) ) )['config'];
		$this->assertNotSame( "#12345678\n", $result['point_colors'][0] );
		$this->assertSame( '#12345678', $result['point_colors'][1] );
	}
}
