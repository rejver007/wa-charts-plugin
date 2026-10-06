<?php
/**
 * Plugin wiring.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Boots all plugin components.
 */
final class Plugin {

	/**
	 * Registers every component's hooks. Runs on `plugins_loaded`.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );
		Post_Type::register_hooks();
		Renderer::register_hooks();
		Shortcode::register_hooks();
		Rest::register_hooks();
		Meta_Box::register_hooks();
		Usage::register_hooks();
		Block::register_hooks();
		Settings::register_hooks();
		Upgrader::register_hooks();
		Updater::maybe_boot();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli\Graphina_Import::register();
		}
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Post_Type::register();
		Post_Type::grant_caps();
		add_option( Upgrader::OPTION_SCHEMA, Chart_Data::SCHEMA, '', false );
	}

	/**
	 * Loads bundled translations.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'wa-charts', false, dirname( plugin_basename( WA_CHARTS_FILE ) ) . '/languages' );
	}
}
