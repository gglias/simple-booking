<?php
/**
 * Admin bootstrap: menus, assets, notices.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Simple Booking admin area.
 */
class SBK_Admin {

	/**
	 * Capability required for every screen and handler.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Wire up hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_sbk_save_settings', array( 'SBK_Admin_Settings', 'handle_save' ) );
		add_action( 'admin_post_sbk_test_mail', array( 'SBK_Admin_Settings', 'handle_test_mail' ) );
		add_action( 'admin_post_sbk_update_appointment', array( 'SBK_Admin_Appointments', 'handle_update' ) );
		add_action( 'admin_post_sbk_create_appointment', array( 'SBK_Admin_Appointments', 'handle_create' ) );
		add_action( 'admin_post_sbk_save_staff', array( 'SBK_Admin_Staff', 'handle_save' ) );
		add_action( 'admin_post_sbk_delete_staff', array( 'SBK_Admin_Staff', 'handle_delete' ) );
		add_action( 'admin_post_sbk_save_service', array( 'SBK_Admin_Services', 'handle_save' ) );
		add_action( 'admin_post_sbk_delete_service', array( 'SBK_Admin_Services', 'handle_delete' ) );
		add_action( 'admin_post_sbk_save_extra', array( 'SBK_Admin_Extras', 'handle_save' ) );
		add_action( 'admin_post_sbk_delete_extra', array( 'SBK_Admin_Extras', 'handle_delete' ) );
		add_action( 'admin_post_sbk_save_schedule', array( 'SBK_Admin_Schedule', 'handle_save' ) );
		add_action( 'admin_post_sbk_copy_schedule', array( 'SBK_Admin_Schedule', 'handle_copy' ) );
		add_action( 'admin_post_sbk_save_exception', array( 'SBK_Admin_Exceptions', 'handle_save' ) );
		add_action( 'admin_post_sbk_delete_exception', array( 'SBK_Admin_Exceptions', 'handle_delete' ) );
	}

	/**
	 * Top-level menu plus one submenu per entity.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Κρατήσεις', 'simple-booking' ),
			__( 'Κρατήσεις', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-appointments',
			array( 'SBK_Admin_Appointments', 'render' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Ραντεβού', 'simple-booking' ),
			__( 'Ραντεβού', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-appointments',
			array( 'SBK_Admin_Appointments', 'render' )
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Προσωπικό', 'simple-booking' ),
			__( 'Προσωπικό', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-staff',
			array( 'SBK_Admin_Staff', 'render' )
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Υπηρεσίες', 'simple-booking' ),
			__( 'Υπηρεσίες', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-services',
			array( 'SBK_Admin_Services', 'render' )
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Extras', 'simple-booking' ),
			__( 'Extras', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-extras',
			array( 'SBK_Admin_Extras', 'render' )
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Ωράριο', 'simple-booking' ),
			__( 'Ωράριο', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-schedule',
			array( 'SBK_Admin_Schedule', 'render' )
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Εξαιρέσεις', 'simple-booking' ),
			__( 'Εξαιρέσεις', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-exceptions',
			array( 'SBK_Admin_Exceptions', 'render' )
		);

		add_submenu_page(
			'sbk-appointments',
			__( 'Ρυθμίσεις', 'simple-booking' ),
			__( 'Ρυθμίσεις', 'simple-booking' ),
			self::CAPABILITY,
			'sbk-settings',
			array( 'SBK_Admin_Settings', 'render' )
		);
	}

	/**
	 * Load admin CSS/JS only on our screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		$screens = array( 'sbk-appointments', 'sbk-staff', 'sbk-services', 'sbk-extras', 'sbk-schedule', 'sbk-exceptions', 'sbk-settings' );
		$ours    = false;

		foreach ( $screens as $screen ) {
			if ( false !== strpos( $hook, $screen ) ) {
				$ours = true;
				break;
			}
		}

		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style(
			'sbk-admin',
			SBK_PLUGIN_URL . 'assets/admin/admin.css',
			array(),
			SBK_VERSION
		);

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		wp_enqueue_script(
			'sbk-admin',
			SBK_PLUGIN_URL . 'assets/admin/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			SBK_VERSION,
			true
		);

		wp_localize_script(
			'sbk-admin',
			'sbkAdmin',
			array(
				'chooseImage'  => __( 'Επιλογή φωτογραφίας', 'simple-booking' ),
				'useImage'     => __( 'Χρήση φωτογραφίας', 'simple-booking' ),
				'confirmStaff' => __( 'Να διαγραφεί οριστικά αυτό το άτομο;', 'simple-booking' ),
				'confirmSvc'   => __( 'Να διαγραφεί οριστικά αυτή η υπηρεσία;', 'simple-booking' ),
				'confirmExc'   => __( 'Να διαγραφεί αυτή η εξαίρεση;', 'simple-booking' ),
				'confirmExtra' => __( 'Να διαγραφεί οριστικά αυτό το extra;', 'simple-booking' ),
				'confirmCopy'  => __( 'Το ωράριο της ημέρας θα αντιγραφεί σε όλες τις υπόλοιπες. Συνέχεια;', 'simple-booking' ),
				'removeLabel'  => __( 'Αφαίρεση διαστήματος', 'simple-booking' ),
			)
		);

		// The booking form reuses the customer-facing calendar, driven by the
		// same REST endpoints, so availability cannot disagree between the two.
		if ( self::is_booking_form() ) {
			wp_enqueue_style(
				'sbk-booking',
				SBK_PLUGIN_URL . 'assets/frontend/booking.css',
				array(),
				SBK_VERSION
			);

			wp_enqueue_script(
				'sbk-calendar',
				SBK_PLUGIN_URL . 'assets/shared/sbk-calendar.js',
				array(),
				SBK_VERSION,
				array( 'strategy' => 'defer' )
			);

			wp_enqueue_script(
				'sbk-admin-calendar',
				SBK_PLUGIN_URL . 'assets/admin/appointment-calendar.js',
				array( 'sbk-calendar' ),
				SBK_VERSION,
				array( 'strategy' => 'defer' )
			);

			// Alpine depends on our script so WordPress prints ours first.
			wp_enqueue_script(
				'alpinejs',
				SBK_Shortcode::alpine_url(),
				array( 'sbk-admin-calendar' ),
				SBK_Shortcode::ALPINE_VERSION,
				array( 'strategy' => 'defer' )
			);

			wp_localize_script( 'sbk-admin-calendar', 'sbkAdminCal', self::calendar_config() );
		}
	}

	/**
	 * Whether the current request is the admin booking form.
	 *
	 * @return bool
	 */
	private static function is_booking_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return SBK_Admin_Appointments::PAGE === $page && 'new' === $view;
	}

	/**
	 * Config for the admin calendar widget.
	 *
	 * @return array
	 */
	private static function calendar_config() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only form state.
		$staff_id   = isset( $_GET['staff_id'] ) ? (int) $_GET['staff_id'] : 0;
		$service_id = isset( $_GET['service_id'] ) ? (int) $_GET['service_id'] : 0;
		$extras     = isset( $_GET['extras'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['extras'] ) ) : array();
		$date       = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$today    = ( new DateTime( 'now', SBK_Helpers::salon_timezone() ) )->format( 'Y-m-d' );
		$max      = new DateTime( 'now', SBK_Helpers::salon_timezone() );
		$weekdays = SBK_Helpers::weekday_labels();

		$max->modify( sprintf( '+%d days', SBK_Settings::get( 'max_advance_days' ) ) );

		return array(
			'restUrl'       => esc_url_raw( rest_url( SBK_Rest_Controller::NAMESPACE_V1 ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'staffId'       => $staff_id,
			'serviceId'     => $service_id,
			'extras'        => array_values( $extras ),
			'today'         => $today,
			'maxDate'       => $max->format( 'Y-m-d' ),
			'initialDate'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : $today,
			'weekdaysShort' => array(
				mb_substr( $weekdays[1], 0, 3 ),
				mb_substr( $weekdays[2], 0, 3 ),
				mb_substr( $weekdays[3], 0, 3 ),
				mb_substr( $weekdays[4], 0, 3 ),
				mb_substr( $weekdays[5], 0, 3 ),
				mb_substr( $weekdays[6], 0, 3 ),
				mb_substr( $weekdays[0], 0, 3 ),
			),
			'months'        => array(
				__( 'Ιανουάριος', 'simple-booking' ),
				__( 'Φεβρουάριος', 'simple-booking' ),
				__( 'Μάρτιος', 'simple-booking' ),
				__( 'Απρίλιος', 'simple-booking' ),
				__( 'Μάιος', 'simple-booking' ),
				__( 'Ιούνιος', 'simple-booking' ),
				__( 'Ιούλιος', 'simple-booking' ),
				__( 'Αύγουστος', 'simple-booking' ),
				__( 'Σεπτέμβριος', 'simple-booking' ),
				__( 'Οκτώβριος', 'simple-booking' ),
				__( 'Νοέμβριος', 'simple-booking' ),
				__( 'Δεκέμβριος', 'simple-booking' ),
			),
			// Genitive, because a date reads "22 Σεπτεμβρίου", not "22 Σεπτέμβριος".
			'monthsGenitive' => array(
				__( 'Ιανουαρίου', 'simple-booking' ),
				__( 'Φεβρουαρίου', 'simple-booking' ),
				__( 'Μαρτίου', 'simple-booking' ),
				__( 'Απριλίου', 'simple-booking' ),
				__( 'Μαΐου', 'simple-booking' ),
				__( 'Ιουνίου', 'simple-booking' ),
				__( 'Ιουλίου', 'simple-booking' ),
				__( 'Αυγούστου', 'simple-booking' ),
				__( 'Σεπτεμβρίου', 'simple-booking' ),
				__( 'Οκτωβρίου', 'simple-booking' ),
				__( 'Νοεμβρίου', 'simple-booking' ),
				__( 'Δεκεμβρίου', 'simple-booking' ),
			),
			'i18n'          => array(
				'error'    => __( 'Κάτι πήγε στραβά. Δοκίμασε ξανά.', 'simple-booking' ),
				'pickTime' => __( 'Δεν έχει επιλεγεί ώρα.', 'simple-booking' ),
			),
		);
	}

	/**
	 * Bail out unless the current user may manage bookings.
	 *
	 * @return void
	 */
	public static function require_cap() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Δεν έχεις δικαίωμα πρόσβασης σε αυτή τη σελίδα.', 'simple-booking' ), 403 );
		}
	}

	/**
	 * Store a one-shot notice for the next page load.
	 *
	 * @param string $message Message text.
	 * @param string $type    'success' or 'error'.
	 * @return void
	 */
	public static function add_notice( $message, $type = 'success' ) {
		set_transient(
			'sbk_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => 'error' === $type ? 'error' : 'success',
			),
			60
		);
	}

	/**
	 * Print and clear the stored notice.
	 *
	 * @return void
	 */
	public static function print_notice() {
		$key    = 'sbk_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! $notice ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Redirect back to a plugin screen after a write.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 * @return void
	 */
	public static function redirect( $page, $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );

		wp_safe_redirect( $url );
		exit;
	}
}
