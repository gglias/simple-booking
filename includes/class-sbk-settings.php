<?php
/**
 * Booking settings with defaults.
 *
 * Stored as one option array so a settings screen can edit them later without
 * touching any of the code that reads them.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the plugin settings.
 */
class SBK_Settings {

	const OPTION = 'sbk_settings';

	/**
	 * Setting key => type, used for casting on read and sanitising on write.
	 */
	const TYPES = array(
		'slot_step_min'          => 'int',
		'lead_time_min'          => 'int',
		'max_advance_days'       => 'int',
		'email_customer_enabled' => 'bool',
		'email_staff_enabled'    => 'bool',
		'email_admin_address'    => 'email',
		'salon_name'             => 'text',
		'salon_address'          => 'text',
		'salon_phone'            => 'text',
		'appointment_page_id'    => 'int',
	);

	/**
	 * Defaults for every setting.
	 *
	 * The salon fields fall back to the site's own values when left empty,
	 * which is handled where they are read rather than here.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			// Granularity of the offered start times, in minutes.
			'slot_step_min'          => 15,
			// How far ahead of now the earliest bookable slot is, in minutes.
			'lead_time_min'          => 120,
			// How many days into the future the calendar opens.
			'max_advance_days'       => 60,
			// Confirmation email to the customer.
			'email_customer_enabled' => true,
			// Notification email to the staff member (and the salon).
			'email_staff_enabled'    => true,
			// Extra recipient for notifications; empty falls back to admin_email.
			'email_admin_address'    => '',
			// Shown in emails; empty falls back to the site name.
			'salon_name'             => '',
			'salon_address'          => '',
			'salon_phone'            => '',
			// Page holding the [simple_booking_appointment] shortcode.
			'appointment_page_id'    => 0,
		);
	}

	/**
	 * All settings, defaults filled in and values cast to their type.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$merged = array_merge( self::defaults(), $stored );
		$out    = array();

		foreach ( $merged as $key => $value ) {
			$out[ $key ] = self::cast( $key, $value );
		}

		return $out;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Update a subset of settings.
	 *
	 * @param array $values Key => value.
	 * @return void
	 */
	public static function update( array $values ) {
		$clean = array();

		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( array_key_exists( $key, $values ) ) {
				$clean[ $key ] = self::cast( $key, $values[ $key ] );
			}
		}

		update_option( self::OPTION, array_merge( self::all(), $clean ), false );
	}

	/**
	 * Cast and sanitise one value according to its declared type.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private static function cast( $key, $value ) {
		$type = isset( self::TYPES[ $key ] ) ? self::TYPES[ $key ] : 'text';

		switch ( $type ) {
			case 'int':
				return max( 0, (int) $value );

			case 'bool':
				return (bool) $value;

			case 'email':
				$email = sanitize_email( (string) $value );

				return is_email( $email ) ? $email : '';

			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Salon name for customer-facing copy, falling back to the site name.
	 *
	 * @return string
	 */
	public static function salon_name() {
		$name = self::get( 'salon_name' );

		return '' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * Where notification emails go.
	 *
	 * @return string
	 */
	public static function admin_address() {
		$address = self::get( 'email_admin_address' );

		return '' !== $address ? $address : get_option( 'admin_email' );
	}
}
