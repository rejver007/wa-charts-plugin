<?php
/**
 * Plugin settings page.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Charts → Settings, with the "delete data on uninstall" switch.
 */
final class Settings {

	public const OPTION_DELETE = 'wa_charts_delete_on_uninstall';
	public const PAGE          = 'wa-charts-settings';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
	}

	/**
	 * Registers the option and its field.
	 *
	 * @return void
	 */
	public static function register_setting(): void {
		register_setting(
			self::PAGE,
			self::OPTION_DELETE,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		add_settings_section( 'wa_charts_main', '', '__return_false', self::PAGE );
		add_settings_field( self::OPTION_DELETE, __( 'Uninstall', 'wa-charts' ), array( self::class, 'render_field' ), self::PAGE, 'wa_charts_main' );
	}

	/**
	 * Adds Charts → Settings.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		add_submenu_page( 'edit.php?post_type=' . Post_Type::SLUG, __( 'Chart settings', 'wa-charts' ), __( 'Settings', 'wa-charts' ), 'manage_options', self::PAGE, array( self::class, 'render_page' ) );
	}

	/**
	 * Checkbox field.
	 *
	 * @return void
	 */
	public static function render_field(): void {
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_DELETE ),
			checked( (bool) get_option( self::OPTION_DELETE ), true, false ),
			esc_html__( 'Delete all charts and plugin settings when the plugin is deleted.', 'wa-charts' )
		);
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1><form action="options.php" method="post">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
