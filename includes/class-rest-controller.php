<?php
/**
 * REST controller for the chart post type.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Same as the core posts controller, but only chart editors may read charts.
 */
final class Rest_Controller extends \WP_REST_Posts_Controller {

	/**
	 * Listing requires `edit_wa_charts`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_wa_charts' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view charts.', 'wa-charts' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Single reads require `edit_wa_charts`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_wa_charts' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view charts.', 'wa-charts' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return parent::get_item_permissions_check( $request );
	}
}
