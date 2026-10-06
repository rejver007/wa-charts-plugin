<?php
namespace WebAula\Charts\Tests;

use WebAula\Charts\Chart_Data;
use WebAula\Charts\Post_Type;
use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Post_Type::grant_caps();
	}

	/**
	 * Creates a chart post with a sanitized config.
	 */
	protected function create_chart( array $raw = array(), string $status = 'publish' ): int {
		$id  = self::factory()->post->create(
			array(
				'post_type'   => Post_Type::SLUG,
				'post_status' => $status,
				'post_title'  => 'Test chart',
			)
		);
		$raw = array_replace_recursive(
			array(
				'type'         => 'pie',
				'labels'       => array( 'UVA', 'Aalto' ),
				'series'       => array( array( 'values' => array( 3.65, 1.6 ) ) ),
				'point_colors' => array( '#1A98D1', '#08588C' ),
			),
			$raw
		);
		Chart_Data::save( $id, Chart_Data::sanitize( $raw )['config'] );
		return $id;
	}
}
