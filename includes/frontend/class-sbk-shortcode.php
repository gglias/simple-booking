<?php
/**
 * The [simple_booking] shortcode.
 *
 * Renders an empty shell; every list is fetched from the REST API, so the page
 * itself stays cacheable and carries no availability data.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the booking form.
 */
class SBK_Shortcode {

	const HANDLE = 'sbk-booking';

	/**
	 * Alpine.js version loaded from the CDN. No build step by design.
	 */
	const ALPINE_VERSION = '3.14.9';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'simple_booking', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * The Alpine.js CDN URL, shared with the admin booking form.
	 *
	 * @return string
	 */
	public static function alpine_url() {
		return 'https://cdn.jsdelivr.net/npm/alpinejs@' . self::ALPINE_VERSION . '/dist/cdn.min.js';
	}

	/**
	 * Register (but do not enqueue) the frontend assets.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( self::HANDLE, SBK_PLUGIN_URL . 'assets/frontend/booking.css', array(), SBK_VERSION );

		wp_register_script(
			'sbk-calendar',
			SBK_PLUGIN_URL . 'assets/shared/sbk-calendar.js',
			array(),
			SBK_VERSION,
			array( 'strategy' => 'defer' )
		);

		wp_register_script(
			self::HANDLE,
			SBK_PLUGIN_URL . 'assets/frontend/booking.js',
			array( 'sbk-calendar' ),
			SBK_VERSION,
			array( 'strategy' => 'defer' )
		);

		// Alpine depends on our script so WordPress prints ours first: the
		// alpine:init listener must exist before Alpine boots.
		wp_register_script(
			'alpinejs',
			self::alpine_url(),
			array( self::HANDLE ),
			self::ALPINE_VERSION,
			array( 'strategy' => 'defer' )
		);
	}

	/**
	 * Render the form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'staff_id' => 0,
			),
			$atts,
			'simple_booking'
		);

		// Block themes render the content before wp_enqueue_scripts fires, so the
		// handles may not exist yet: registering here keeps wp_localize_script working.
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		wp_enqueue_script( 'alpinejs' );

		wp_localize_script( self::HANDLE, 'sbkConfig', self::config( (int) $atts['staff_id'] ) );

		ob_start();
		require SBK_PLUGIN_DIR . 'includes/frontend/views/booking-form.php';

		return ob_get_clean();
	}

	/**
	 * Everything the Alpine component needs that only PHP knows.
	 *
	 * @param int $preselect_staff Staff id to preselect, 0 for none.
	 * @return array
	 */
	private static function config( $preselect_staff = 0 ) {
		$weekdays = SBK_Helpers::weekday_labels();

		return array(
			'restUrl'        => esc_url_raw( rest_url( SBK_Rest_Controller::NAMESPACE_V1 ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'today'          => ( new DateTime( 'now', SBK_Helpers::salon_timezone() ) )->format( 'Y-m-d' ),
			'maxAdvanceDays' => SBK_Settings::get( 'max_advance_days' ),
			'preselectStaff' => $preselect_staff,
			'weekdaysShort'  => array(
				// Monday first, matching the calendar grid.
				mb_substr( $weekdays[1], 0, 3 ),
				mb_substr( $weekdays[2], 0, 3 ),
				mb_substr( $weekdays[3], 0, 3 ),
				mb_substr( $weekdays[4], 0, 3 ),
				mb_substr( $weekdays[5], 0, 3 ),
				mb_substr( $weekdays[6], 0, 3 ),
				mb_substr( $weekdays[0], 0, 3 ),
			),
			'months'         => array(
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
			'steps'          => array(
				__( 'Κουρέας', 'simple-booking' ),
				__( 'Υπηρεσία', 'simple-booking' ),
				__( 'Extras', 'simple-booking' ),
				__( 'Ημερομηνία', 'simple-booking' ),
				__( 'Στοιχεία', 'simple-booking' ),
				__( 'Σύνοψη', 'simple-booking' ),
				__( 'Επιβεβαίωση', 'simple-booking' ),
			),
			'i18n'           => array(
				'genericError'  => __( 'Κάτι πήγε στραβά. Δοκίμασε ξανά.', 'simple-booking' ),
				'networkError'  => __( 'Δεν υπάρχει σύνδεση με τον διακομιστή.', 'simple-booking' ),
				'requiredName'  => __( 'Συμπλήρωσε το όνομά σου.', 'simple-booking' ),
				'requiredEmail' => __( 'Συμπλήρωσε ένα έγκυρο email.', 'simple-booking' ),
				'requiredPhone' => __( 'Συμπλήρωσε ένα έγκυρο τηλέφωνο.', 'simple-booking' ),
				'continue'      => __( 'Συνέχεια', 'simple-booking' ),
				'skipExtras'    => __( 'Χωρίς extras', 'simple-booking' ),
			),
		);
	}
}
