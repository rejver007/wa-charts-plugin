<?php
/**
 * Admin-only REST endpoints.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Live preview endpoint for the chart editor. Saves nothing.
 */
final class Rest {

	public const ROUTE_NAMESPACE = 'wa-charts/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => static fn() => current_user_can( 'edit_wa_charts' ),
				'args'                => array(
					'config'  => array(
						'required' => true,
						'type'     => 'object',
					),
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Returns the sanitized config and client payload for the posted form state.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function preview( \WP_REST_Request $request ) {
		if ( strlen( (string) $request->get_body() ) > Chart_Data::MAX_BYTES * 2 ) {
			return new \WP_Error( 'wa_charts_too_large', __( 'Chart data is too large.', 'wa-charts' ), array( 'status' => 413 ) );
		}
		$result = Chart_Data::sanitize( $request->get_param( 'config' ) );
		return rest_ensure_response(
			array(
				'payload' => Renderer::client_payload( $result['config'], (int) $request->get_param( 'post_id' ) ),
				'config'  => $result['config'],
				'errors'  => $result['errors'],
			)
		);
	}
}
