<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Chart_Types;

class ChartTypesTest extends TestCase {

	public function tear_down() {
		remove_all_filters( 'wa_charts_chart_types' );
		remove_all_filters( 'wa_charts_default_palette' );
		parent::tear_down();
	}

	public function test_all_eleven_types_in_order() {
		$this->assertSame(
			array( 'pie', 'donut', 'polar', 'radial', 'bar', 'bar-horizontal', 'stacked', 'line', 'area', 'mixed', 'radar' ),
			Chart_Types::ids()
		);
	}

	public function test_shapes() {
		foreach ( array( 'pie', 'donut', 'polar', 'radial' ) as $id ) {
			$this->assertSame( Chart_Types::SHAPE_SINGLE, Chart_Types::shape( $id ), $id );
		}
		foreach ( array( 'bar', 'bar-horizontal', 'stacked', 'line', 'area', 'mixed', 'radar' ) as $id ) {
			$this->assertSame( Chart_Types::SHAPE_MULTI, Chart_Types::shape( $id ), $id );
		}
	}

	public function test_radial_is_limited_to_one_row() {
		$this->assertSame( 1, Chart_Types::get( 'radial' )['max_rows'] );
		$this->assertSame( 500, Chart_Types::get( 'pie' )['max_rows'] );
	}

	public function test_type_options() {
		$donut = Chart_Types::get( 'donut' )['options'];
		$this->assertSame( array( 'hole_size', 'start_angle' ), array_keys( $donut ) );
		$this->assertSame( 65, $donut['hole_size']['default'] );
		$this->assertSame( array( 'bar_width', 'stack_100' ), array_keys( Chart_Types::get( 'stacked' )['options'] ) );
		$this->assertSame( array( 'curve', 'markers', 'bar_width' ), array_keys( Chart_Types::get( 'mixed' )['options'] ) );
		$this->assertSame( array( 'curve' ), array_keys( Chart_Types::get( 'radar' )['options'] ) );
		$this->assertSame( array(), Chart_Types::get( 'polar' )['options'] );
	}

	public function test_unknown_type() {
		$this->assertNull( Chart_Types::get( 'heatmap' ) );
		$this->assertFalse( Chart_Types::exists( 'heatmap' ) );
		$this->assertSame( Chart_Types::SHAPE_SINGLE, Chart_Types::shape( 'heatmap' ) );
	}

	public function test_filter_can_relabel_and_remove_but_not_add() {
		add_filter(
			'wa_charts_chart_types',
			static function ( $types ) {
				unset( $types['radar'] );
				$types['pie']['label'] = 'Piirakka';
				$types['pie']['shape'] = 'multi';
				$types['heatmap']      = array( 'label' => 'Heat' );
				return $types;
			}
		);
		$this->assertFalse( Chart_Types::exists( 'radar' ) );
		$this->assertFalse( Chart_Types::exists( 'heatmap' ) );
		$this->assertSame( 'Piirakka', Chart_Types::get( 'pie' )['label'] );
		$this->assertSame( Chart_Types::SHAPE_SINGLE, Chart_Types::shape( 'pie' ) );
	}

	public function test_default_palette_and_filter() {
		$this->assertSame( '#1A98D1', Chart_Types::default_palette()[0] );
		$this->assertCount( 12, Chart_Types::default_palette() );

		add_filter( 'wa_charts_default_palette', static fn() => array( '#ff0000', 'red', 'javascript:alert(1)' ) );
		$this->assertSame( array( '#ff0000' ), Chart_Types::default_palette() );

		add_filter( 'wa_charts_default_palette', static fn() => array(), 20 );
		$this->assertSame( array( '#1A98D1' ), Chart_Types::default_palette() );
	}

	public function test_pie_survives_a_filter_that_removes_it() {
		add_filter(
			'wa_charts_chart_types',
			static function ( $types ) {
				unset( $types['pie'] );
				return $types;
			}
		);
		$this->assertTrue( Chart_Types::exists( 'pie' ) );
	}
}
