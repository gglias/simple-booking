<?php
/**
 * Plugin Name:       Simple Booking
 * Plugin URI:        https://github.com/gglias/simple-booking
 * Description:       Appointment booking for a single hair salon: fixed staff, guest bookings, no online payments.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Smartup
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-booking
 * Domain Path:       /languages
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 *
 * Money is always stored and passed around as integer euro cents.
 * Datetimes are always stored in UTC; SBK_TIMEZONE is the salon's wall clock.
 */
define( 'SBK_VERSION', '0.1.0' );
define( 'SBK_DB_VERSION', '1.0.0' );
define( 'SBK_PLUGIN_FILE', __FILE__ );
define( 'SBK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SBK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SBK_TIMEZONE', 'Europe/Athens' );

require_once SBK_PLUGIN_DIR . 'includes/class-sbk-tables.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-helpers.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-activator.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-staff-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-service-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-settings.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-schedule-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-exception-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-extra-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-customer-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-appointment-repository.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-availability.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-booking-service.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-mailer.php';
require_once SBK_PLUGIN_DIR . 'includes/class-sbk-rest-controller.php';

require_once SBK_PLUGIN_DIR . 'includes/frontend/class-sbk-shortcode.php';
require_once SBK_PLUGIN_DIR . 'includes/frontend/class-sbk-appointment-page.php';

SBK_Mailer::init();
SBK_Rest_Controller::init();
SBK_Shortcode::init();
SBK_Appointment_Page::init();

if ( is_admin() ) {
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-appointments.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-staff.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-services.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-extras.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-schedule.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-exceptions.php';
	require_once SBK_PLUGIN_DIR . 'includes/admin/class-sbk-admin-settings.php';

	SBK_Admin::init();
}

register_activation_hook( __FILE__, array( 'SBK_Activator', 'activate' ) );

/**
 * Load translations.
 *
 * @return void
 */
function sbk_load_textdomain() {
	load_plugin_textdomain( 'simple-booking', false, dirname( SBK_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'init', 'sbk_load_textdomain' );

/**
 * Run pending schema upgrades when the stored DB version is behind the code.
 *
 * Covers sites updated via FTP/Git where the activation hook never fires.
 *
 * @return void
 */
function sbk_maybe_upgrade_db() {
	if ( get_option( 'sbk_db_version' ) === SBK_DB_VERSION ) {
		return;
	}

	SBK_Activator::install();
}
add_action( 'plugins_loaded', 'sbk_maybe_upgrade_db' );
