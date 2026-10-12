<?php
/**
 * Central registry of the plugin's custom tables.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves prefixed table names so no query hardcodes a prefix.
 */
class SBK_Tables {

	/**
	 * Table key => unprefixed suffix.
	 */
	const TABLES = array(
		'staff'              => 'sbk_staff',
		'services'           => 'sbk_services',
		'staff_services'     => 'sbk_staff_services',
		'extras'             => 'sbk_extras',
		'service_extras'     => 'sbk_service_extras',
		'schedules'          => 'sbk_schedules',
		'exceptions'         => 'sbk_exceptions',
		'customers'          => 'sbk_customers',
		'appointments'       => 'sbk_appointments',
		'appointment_extras' => 'sbk_appointment_extras',
	);

	/**
	 * Full table name for a key.
	 *
	 * @param string $key One of self::TABLES.
	 * @return string
	 */
	public static function get( $key ) {
		global $wpdb;

		if ( ! isset( self::TABLES[ $key ] ) ) {
			_doing_it_wrong( __METHOD__, esc_html( "Unknown table key: {$key}" ), '0.1.0' );
			return '';
		}

		return $wpdb->prefix . self::TABLES[ $key ];
	}

	/**
	 * All full table names, keyed as in self::TABLES.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		$names = array();

		foreach ( array_keys( self::TABLES ) as $key ) {
			$names[ $key ] = self::get( $key );
		}

		return $names;
	}

	/** @return string */
	public static function staff() {
		return self::get( 'staff' );
	}

	/** @return string */
	public static function services() {
		return self::get( 'services' );
	}

	/** @return string */
	public static function staff_services() {
		return self::get( 'staff_services' );
	}

	/** @return string */
	public static function extras() {
		return self::get( 'extras' );
	}

	/** @return string */
	public static function service_extras() {
		return self::get( 'service_extras' );
	}

	/** @return string */
	public static function schedules() {
		return self::get( 'schedules' );
	}

	/** @return string */
	public static function exceptions() {
		return self::get( 'exceptions' );
	}

	/** @return string */
	public static function customers() {
		return self::get( 'customers' );
	}

	/** @return string */
	public static function appointments() {
		return self::get( 'appointments' );
	}

	/** @return string */
	public static function appointment_extras() {
		return self::get( 'appointment_extras' );
	}
}
