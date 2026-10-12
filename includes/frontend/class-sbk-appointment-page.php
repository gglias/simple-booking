<?php
/**
 * The customer's appointment page, reached from the confirmation email.
 *
 * Read-only on purpose: cancelling is done by phone, so this page never offers
 * it. The manage token in the URL is the only credential; it is random, 32
 * characters, and unique per appointment.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders [simple_booking_appointment] and serves the calendar file.
 */
class SBK_Appointment_Page {

	const SHORTCODE = 'simple_booking_appointment';
	const HANDLE    = 'sbk-appointment';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_ics' ) );
	}

	/**
	 * The public URL of one appointment.
	 *
	 * @param string $token Manage token.
	 * @param array  $args  Extra query args.
	 * @return string
	 */
	public static function url( $token, $args = array() ) {
		$page_id = (int) SBK_Settings::get( 'appointment_page_id' );
		$base    = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		return add_query_arg( array_merge( array( 'sbk_token' => $token ), $args ), $base );
	}

	/**
	 * The token from the current request, if it looks like one.
	 *
	 * @return string
	 */
	private static function requested_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
		$token = isset( $_GET['sbk_token'] ) ? sanitize_text_field( wp_unslash( $_GET['sbk_token'] ) ) : '';

		return preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ? $token : '';
	}

	/**
	 * A short, human-quotable reference for the appointment.
	 *
	 * Derived from the token, so it needs no extra column and cannot be guessed
	 * from the appointment id.
	 *
	 * @param string $token Manage token.
	 * @return string
	 */
	public static function reference( $token ) {
		return strtoupper( substr( $token, 0, 6 ) );
	}

	/**
	 * Render the appointment card.
	 *
	 * @return string
	 */
	public static function render() {
		$token = self::requested_token();

		wp_enqueue_style( 'sbk-booking', SBK_PLUGIN_URL . 'assets/frontend/booking.css', array(), SBK_VERSION );

		if ( '' === $token ) {
			return self::message( __( 'Λείπει ο σύνδεσμος του ραντεβού.', 'simple-booking' ) );
		}

		$appointment = SBK_Appointment_Repository::find_by_token( $token );

		if ( ! $appointment ) {
			return self::message( __( 'Το ραντεβού δεν βρέθηκε. Ο σύνδεσμος μπορεί να έχει λήξει.', 'simple-booking' ) );
		}

		$data = array(
			'appointment' => $appointment,
			'customer'    => SBK_Customer_Repository::find( (int) $appointment->customer_id ),
			'staff'       => SBK_Staff_Repository::find( (int) $appointment->staff_id ),
			'extras'      => SBK_Appointment_Repository::get_extras( (int) $appointment->id ),
			'token'       => $token,
			'reference'   => self::reference( $token ),
			'statuses'    => SBK_Appointment_Repository::statuses(),
			'salon_name'  => SBK_Settings::salon_name(),
			'address'     => SBK_Settings::get( 'salon_address' ),
			'phone'       => SBK_Settings::get( 'salon_phone' ),
			'start'       => SBK_Helpers::utc_to_local( $appointment->start_utc ),
			'end'         => SBK_Helpers::utc_to_local( $appointment->end_utc ),
			'ics_url'     => self::url( $token, array( 'sbk_ics' => 1 ) ),
		);

		ob_start();
		include SBK_PLUGIN_DIR . 'includes/frontend/views/appointment-card.php';

		return ob_get_clean();
	}

	/**
	 * A standalone notice in the same card shell.
	 *
	 * @param string $text Message.
	 * @return string
	 */
	private static function message( $text ) {
		return '<div class="sbk sbk-ticket sbk-ticket--empty"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Serve the appointment as an .ics file when asked for.
	 *
	 * @return void
	 */
	public static function maybe_serve_ics() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
		if ( empty( $_GET['sbk_ics'] ) ) {
			return;
		}

		$token = self::requested_token();

		if ( '' === $token ) {
			return;
		}

		$appointment = SBK_Appointment_Repository::find_by_token( $token );

		if ( ! $appointment ) {
			return;
		}

		$summary  = sprintf(
			/* translators: 1: service name, 2: staff name */
			__( '%1$s με %2$s', 'simple-booking' ),
			$appointment->service_name,
			$appointment->staff_name
		);
		$location = SBK_Settings::get( 'salon_address' );
		$location = '' !== $location ? $location : SBK_Settings::salon_name();

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Simple Booking//EL',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $token . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', strtotime( $appointment->start_utc . ' UTC' ) ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', strtotime( $appointment->end_utc . ' UTC' ) ),
			'SUMMARY:' . self::escape_ics( $summary ),
			'LOCATION:' . self::escape_ics( $location ),
			'DESCRIPTION:' . self::escape_ics( SBK_Settings::salon_name() ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="appointment-' . self::reference( $token ) . '.ics"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar payload, escaped above.
		echo implode( "\r\n", $lines );
		exit;
	}

	/**
	 * Escape a value for an iCalendar field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape_ics( $value ) {
		return str_replace(
			array( '\\', ';', ',', "\n" ),
			array( '\\\\', '\\;', '\\,', '\\n' ),
			(string) $value
		);
	}
}
