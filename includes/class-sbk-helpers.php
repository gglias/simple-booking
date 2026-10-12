<?php
/**
 * Shared helpers: money, time and small formatting utilities.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers used by both admin and frontend.
 */
class SBK_Helpers {

	/**
	 * Current UTC time in MySQL format.
	 *
	 * @return string
	 */
	public static function now_utc() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * The salon's timezone object.
	 *
	 * @return DateTimeZone
	 */
	public static function salon_timezone() {
		return new DateTimeZone( SBK_TIMEZONE );
	}

	/**
	 * Parse a human-typed price into integer euro cents.
	 *
	 * Accepts "25", "25.50", "25,50", "1.250,00", "€ 25,50".
	 * Rounding happens once, here, so no float ever reaches the database.
	 *
	 * @param string|int|float $value Raw input.
	 * @return int Cents, never negative.
	 */
	public static function to_cents( $value ) {
		if ( is_int( $value ) ) {
			return max( 0, $value * 100 );
		}

		$raw = trim( (string) $value );
		$raw = preg_replace( '/[^0-9.,\-]/', '', $raw );

		if ( '' === $raw ) {
			return 0;
		}

		$last_dot   = strrpos( $raw, '.' );
		$last_comma = strrpos( $raw, ',' );

		if ( false !== $last_dot && false !== $last_comma ) {
			// Both present: the rightmost one is the decimal separator.
			$decimal_sep = $last_dot > $last_comma ? '.' : ',';
		} elseif ( false !== $last_comma ) {
			$decimal_sep = ',';
		} else {
			$decimal_sep = '.';
		}

		$thousands_sep = ( ',' === $decimal_sep ) ? '.' : ',';
		$raw           = str_replace( $thousands_sep, '', $raw );
		$raw           = str_replace( $decimal_sep, '.', $raw );

		return max( 0, (int) round( (float) $raw * 100 ) );
	}

	/**
	 * Cents to a plain value for a form input ("25.50").
	 *
	 * @param int $cents Cents.
	 * @return string
	 */
	public static function cents_to_input( $cents ) {
		return number_format( (int) $cents / 100, 2, '.', '' );
	}

	/**
	 * Cents formatted for display ("25,50 €").
	 *
	 * @param int $cents Cents.
	 * @return string
	 */
	public static function format_money( $cents ) {
		return number_format_i18n( (int) $cents / 100, 2 ) . ' €';
	}

	/**
	 * Minutes formatted for display ("1 ώ 30 λ").
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function format_duration( $minutes ) {
		$minutes = (int) $minutes;
		$hours   = intdiv( $minutes, 60 );
		$rest    = $minutes % 60;

		if ( $hours && $rest ) {
			/* translators: 1: hours, 2: minutes */
			return sprintf( __( '%1$d ώ %2$d λ', 'simple-booking' ), $hours, $rest );
		}

		if ( $hours ) {
			/* translators: %d: hours */
			return sprintf( __( '%d ώ', 'simple-booking' ), $hours );
		}

		/* translators: %d: minutes */
		return sprintf( __( '%d λ', 'simple-booking' ), $minutes );
	}

	/**
	 * Build a readable slug that keeps Greek characters.
	 *
	 * sanitize_title() percent-encodes non-Latin text ("%ce%b2%ce%b1..."), which
	 * is unreadable in the admin, so we lowercase and strip Unicode-aware instead.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	public static function make_slug( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s\-_]+/u', '', $text );
		$text = preg_replace( '/[\s_\-]+/u', '-', $text );
		$text = trim( $text, '-' );

		if ( function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, 180, 'UTF-8' );
		}

		return $text;
	}

	/**
	 * Weekday labels keyed the way the schedules table stores them.
	 *
	 * 0 = Sunday ... 6 = Saturday, matching PHP date('w').
	 *
	 * @return array<int,string>
	 */
	public static function weekday_labels() {
		return array(
			0 => __( 'Κυριακή', 'simple-booking' ),
			1 => __( 'Δευτέρα', 'simple-booking' ),
			2 => __( 'Τρίτη', 'simple-booking' ),
			3 => __( 'Τετάρτη', 'simple-booking' ),
			4 => __( 'Πέμπτη', 'simple-booking' ),
			5 => __( 'Παρασκευή', 'simple-booking' ),
			6 => __( 'Σάββατο', 'simple-booking' ),
		);
	}

	/**
	 * Weekday keys in display order (Monday first).
	 *
	 * @return int[]
	 */
	public static function weekday_order() {
		return array( 1, 2, 3, 4, 5, 6, 0 );
	}

	/**
	 * Normalise a typed time to MySQL TIME, or null when unusable.
	 *
	 * Accepts "9:00", "09:00" and "09:00:00".
	 *
	 * @param string $value Raw time.
	 * @return string|null
	 */
	public static function sanitize_time( $value ) {
		$value = trim( (string) $value );

		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
			return null;
		}

		$hours   = (int) $m[1];
		$minutes = (int) $m[2];
		$seconds = isset( $m[3] ) ? (int) $m[3] : 0;

		if ( $hours > 23 || $minutes > 59 || $seconds > 59 ) {
			return null;
		}

		return sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
	}

	/**
	 * MySQL TIME to an <input type="time"> value ("09:00").
	 *
	 * @param string $value Stored time.
	 * @return string
	 */
	public static function time_to_input( $value ) {
		return $value ? substr( (string) $value, 0, 5 ) : '';
	}

	/**
	 * Minutes since midnight, for comparing wall-clock times.
	 *
	 * @param string $value MySQL TIME.
	 * @return int
	 */
	public static function time_to_minutes( $value ) {
		$parts = explode( ':', (string) $value );

		return ( (int) $parts[0] ) * 60 + ( isset( $parts[1] ) ? (int) $parts[1] : 0 );
	}

	/**
	 * A date in salon local time, formatted for display.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public static function format_date( $date ) {
		$dt = DateTime::createFromFormat( 'Y-m-d', (string) $date, self::salon_timezone() );

		if ( ! $dt ) {
			return (string) $date;
		}

		$labels = self::weekday_labels();

		return $labels[ (int) $dt->format( 'w' ) ] . ', ' . $dt->format( 'd/m/Y' );
	}

	/**
	 * A salon wall-clock moment as a UTC MySQL datetime.
	 *
	 * @param string $date Y-m-d local.
	 * @param string $time H:i:s local.
	 * @return string
	 */
	public static function local_to_utc( $date, $time = '00:00:00' ) {
		$local = new DateTime( $date . ' ' . $time, self::salon_timezone() );
		$local->setTimezone( new DateTimeZone( 'UTC' ) );

		return $local->format( 'Y-m-d H:i:s' );
	}

	/**
	 * A stored UTC datetime as a salon wall-clock DateTime.
	 *
	 * @param string $utc MySQL datetime in UTC.
	 * @return DateTime
	 */
	public static function utc_to_local( $utc ) {
		$dt = new DateTime( $utc, new DateTimeZone( 'UTC' ) );
		$dt->setTimezone( self::salon_timezone() );

		return $dt;
	}

	/**
	 * Clamp an integer into a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	public static function clamp_int( $value, $min, $max ) {
		return (int) max( $min, min( $max, (int) $value ) );
	}
}
