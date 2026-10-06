<?php
/**
 * PHPUnit bootstrap (runs inside wp-env tests-cli).
 *
 * @package WebAula\Charts
 */

$wa_charts_root = dirname( __DIR__, 2 );
require_once $wa_charts_root . '/vendor/autoload.php';

$wa_charts_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : getenv( 'WP_PHPUNIT__DIR' );
require_once $wa_charts_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wa_charts_root ) {
		require $wa_charts_root . '/wa-charts.php';
	}
);

require $wa_charts_tests_dir . '/includes/bootstrap.php';
