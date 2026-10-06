<?php
/**
 * Plugin Name:       WebAula Charts
 * Plugin URI:        https://github.com/rejver007/wa-charts-plugin
 * Update URI:        https://github.com/rejver007/wa-charts-plugin
 * Description:       Charts as a custom post type, shown with a shortcode or block. Replaces Graphina.
 * Version:           1.1.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            WebAula Oy
 * Author URI:        https://webaula.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wa-charts
 * Domain Path:       /languages
 *
 * @package WebAula\Charts
 */

defined( 'ABSPATH' ) || exit;

define( 'WA_CHARTS_VERSION', '1.1.1' );
define( 'WA_CHARTS_FILE', __FILE__ );
define( 'WA_CHARTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WA_CHARTS_URL', plugin_dir_url( __FILE__ ) );
define( 'WA_CHARTS_CHARTJS_VERSION', '4.5.1' );
define( 'WA_CHARTS_DATALABELS_VERSION', '2.2.0' );

/**
 * Shows an admin notice when the server does not meet the requirements.
 *
 * @return void
 */
function wa_charts_requirements_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WebAula Charts needs PHP 8.1 and WordPress 6.6 or newer. The plugin is not running.', 'wa-charts' );
	echo '</p></div>';
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
	add_action( 'admin_notices', 'wa_charts_requirements_notice' );
	return;
}

require_once WA_CHARTS_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( \WebAula\Charts\Plugin::class, 'activate' ) );
add_action( 'plugins_loaded', array( \WebAula\Charts\Plugin::class, 'boot' ) );
