<?php
/**
 * Uninstall: removes data only when the site opted in under Charts → Settings.
 *
 * @package WebAula\Charts
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'wa_charts_delete_on_uninstall' ) ) {
	return;
}

$wa_charts_ids = get_posts(
	array(
		'post_type'      => 'wa_chart',
		'post_status'    => array_keys( get_post_stati() ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $wa_charts_ids as $wa_charts_id ) {
	wp_delete_post( $wa_charts_id, true );
}

foreach ( array( 'wa_charts_delete_on_uninstall', 'wa_charts_db_schema', 'wa_charts_upgrade_cursor', 'wa_charts_usage_generation' ) as $wa_charts_option ) {
	delete_option( $wa_charts_option );
}

foreach ( wp_roles()->role_objects as $wa_charts_role ) {
	foreach ( array_keys( $wa_charts_role->capabilities ) as $wa_charts_cap ) {
		if ( str_ends_with( $wa_charts_cap, '_wa_charts' ) ) {
			$wa_charts_role->remove_cap( $wa_charts_cap );
		}
	}
}
