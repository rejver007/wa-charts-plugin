<?php
/**
 * Chart edit screen.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes, assets and the save handler for the chart edit screen.
 */
final class Meta_Box {

	public const NONCE_ACTION  = 'wa_charts_save';
	public const NONCE_NAME    = 'wa_charts_nonce';
	public const FIELD         = 'wa_chart_config';
	public const HANDLE_EDITOR = 'wa-charts-editor';
	public const HANDLE_STATE  = 'wa-charts-state';
	public const HANDLE_PASTE  = 'wa-charts-paste';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'add_meta_boxes_' . Post_Type::SLUG, array( self::class, 'add_boxes' ) );
		add_action( 'save_post_' . Post_Type::SLUG, array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
	}

	/**
	 * Registers the meta boxes.
	 *
	 * @return void
	 */
	public static function add_boxes(): void {
		add_meta_box( 'wa-charts-type', __( 'Chart type', 'wa-charts' ), array( self::class, 'render_type_box' ), Post_Type::SLUG, 'normal', 'high' );
		add_meta_box( 'wa-charts-data', __( 'Data', 'wa-charts' ), array( self::class, 'render_data_box' ), Post_Type::SLUG, 'normal', 'high' );
		add_meta_box( 'wa-charts-display', __( 'Display', 'wa-charts' ), array( self::class, 'render_display_box' ), Post_Type::SLUG, 'normal', 'default' );
		add_meta_box( 'wa-charts-preview-box', __( 'Preview', 'wa-charts' ), array( self::class, 'render_preview_box' ), Post_Type::SLUG, 'side', 'high' );
		add_meta_box( 'wa-charts-usage', __( 'Shortcode and usage', 'wa-charts' ), array( self::class, 'render_usage_box' ), Post_Type::SLUG, 'side', 'default' );
	}

	/**
	 * Type box; also holds the nonce and the hidden config field.
	 *
	 * @param \WP_Post $post Chart.
	 * @return void
	 */
	public static function render_type_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		printf(
			'<input type="hidden" id="wa-chart-config-input" name="%1$s" value="%2$s" />',
			esc_attr( self::FIELD ),
			esc_attr( (string) wp_json_encode( Chart_Data::get( $post->ID ) ) )
		);
		echo '<div id="wa-charts-type-picker" class="wa-charts-type-picker"></div>';
		echo '<noscript><p>' . esc_html__( 'The chart editor needs JavaScript.', 'wa-charts' ) . '</p></noscript>';
	}

	/**
	 * Data box container.
	 *
	 * @return void
	 */
	public static function render_data_box(): void {
		echo '<div id="wa-charts-data-editor"></div>';
	}

	/**
	 * Display box container.
	 *
	 * @return void
	 */
	public static function render_display_box(): void {
		echo '<div id="wa-charts-display-editor"></div>';
	}

	/**
	 * Preview box container.
	 *
	 * @return void
	 */
	public static function render_preview_box(): void {
		echo '<div id="wa-charts-preview" class="wa-chart wa-chart--preview"></div>';
		echo '<ul id="wa-charts-preview-errors" class="wa-charts-errors"></ul>';
	}

	/**
	 * Shortcode and usage box.
	 *
	 * @param \WP_Post $post Chart.
	 * @return void
	 */
	public static function render_usage_box( \WP_Post $post ): void {
		echo '<p>' . Post_Type::shortcode_field( $post->ID ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in shortcode_field().
		echo Usage::render_list( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_list().
	}

	/**
	 * Editor assets on the chart edit screen only.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue( string $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || Post_Type::SLUG !== $screen->post_type ) {
			return;
		}
		global $post;
		$post_id = $post instanceof \WP_Post ? $post->ID : 0;

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( Renderer::HANDLE_FRONT );
		wp_enqueue_style( self::HANDLE_EDITOR, WA_CHARTS_URL . 'assets/admin/editor.css', array(), WA_CHARTS_VERSION );

		wp_register_script( self::HANDLE_PASTE, WA_CHARTS_URL . 'assets/admin/paste-parser.js', array(), WA_CHARTS_VERSION, true );
		wp_register_script( self::HANDLE_STATE, WA_CHARTS_URL . 'assets/admin/editor-state.js', array( self::HANDLE_PASTE ), WA_CHARTS_VERSION, true );
		wp_enqueue_script(
			self::HANDLE_EDITOR,
			WA_CHARTS_URL . 'assets/admin/editor.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker', 'wp-api-fetch', 'wp-i18n', self::HANDLE_STATE, Renderer::HANDLE_FRONT, Renderer::HANDLE_DATALABELS ),
			WA_CHARTS_VERSION,
			true
		);
		wp_enqueue_script( Post_Type::COPY_HANDLE );
		wp_set_script_translations( self::HANDLE_EDITOR, 'wa-charts', WA_CHARTS_DIR . 'languages' );
		wp_add_inline_script( self::HANDLE_EDITOR, self::editor_settings_script( $post_id ), 'before' );
	}

	/**
	 * Inline script that hands the settings to editor.js, safe inside a script tag.
	 *
	 * @param int $post_id Chart ID.
	 * @return string
	 */
	public static function editor_settings_script( int $post_id ): string {
		return 'window.waChartsEditor = ' . wp_json_encode( self::editor_settings( $post_id ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';';
	}

	/**
	 * Settings passed to editor.js.
	 *
	 * @param int $post_id Chart ID.
	 * @return array
	 */
	public static function editor_settings( int $post_id ): array {
		return array(
			'postId'      => $post_id,
			'types'       => Chart_Types::all(),
			'palette'     => Chart_Types::default_palette(),
			'limits'      => array(
				'labels' => Chart_Data::MAX_LABELS,
				'series' => Chart_Data::MAX_SERIES,
			),
			'previewPath' => '/' . Rest::ROUTE_NAMESPACE . '/preview',
		);
	}

	/**
	 * Saves the chart config from the hidden field.
	 *
	 * @param int $post_id Chart ID.
	 * @return void
	 */
	public static function save( int $post_id ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}
		$raw = is_string( $_POST[ self::FIELD ] ) ? json_decode( wp_unslash( $_POST[ self::FIELD ] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded JSON is sanitized by Chart_Data::sanitize().
		if ( ! is_array( $raw ) ) {
			set_transient( self::error_key(), array( __( 'The chart data could not be read, so nothing was saved.', 'wa-charts' ) ), MINUTE_IN_SECONDS );
			return;
		}
		$result = Chart_Data::sanitize( $raw );
		$errors = $result['errors'];
		if ( ! Chart_Data::save( $post_id, $result['config'] ) ) {
			$errors[] = __( 'The chart data is larger than 100 KB and was not saved.', 'wa-charts' );
		}
		if ( $errors ) {
			set_transient( self::error_key(), $errors, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Shows (once) the errors collected on the last save.
	 *
	 * @return void
	 */
	public static function notices(): void {
		$errors = get_transient( self::error_key() );
		if ( ! is_array( $errors ) || ! $errors ) {
			return;
		}
		delete_transient( self::error_key() );
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Some chart data was adjusted when saving:', 'wa-charts' ) . '</strong></p><ul>';
		foreach ( array_slice( $errors, 0, 20 ) as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Per-user transient key for save errors.
	 *
	 * @return string
	 */
	public static function error_key(): string {
		return 'wa_charts_errors_' . get_current_user_id();
	}
}
