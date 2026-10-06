<?php
/**
 * Class autoloader.
 *
 * @package WebAula\Charts
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'WebAula\\Charts\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$name  = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = WA_CHARTS_DIR . 'includes/' . $dir . 'class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
