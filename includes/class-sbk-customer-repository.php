<?php
/**
 * Data access for customers.
 *
 * Guest booking is the norm: a customer row is created from the booking form,
 * with a WP user attached only when the customer asked for an account.
 * Email is the identity key, so repeat guests land on the same row.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}sbk_customers.
 */
class SBK_Customer_Repository {

	/**
	 * Single customer row.
	 *
	 * @param int $id Customer id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = SBK_Tables::customers();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Look a customer up by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public static function find_by_email( $email ) {
		global $wpdb;

		$table = SBK_Tables::customers();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );
	}

	/**
	 * Validate booking form contact details.
	 *
	 * @param array $data Raw form data.
	 * @return array|WP_Error Clean values.
	 */
	public static function validate( array $data ) {
		$first = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';

		if ( '' === trim( $first ) ) {
			return new WP_Error( 'sbk_customer_name', __( 'Το όνομα είναι υποχρεωτικό.', 'simple-booking' ) );
		}

		$email_raw = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
		$email     = sanitize_email( $email_raw );

		if ( '' === $email_raw || ! is_email( $email ) ) {
			return new WP_Error( 'sbk_customer_email', __( 'Χρειάζεται έγκυρο email.', 'simple-booking' ) );
		}

		$phone  = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
		$digits = preg_replace( '/\D/', '', $phone );

		if ( strlen( $digits ) < 8 ) {
			return new WP_Error( 'sbk_customer_phone', __( 'Χρειάζεται έγκυρο τηλέφωνο.', 'simple-booking' ) );
		}

		return array(
			'first_name' => $first,
			'last_name'  => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : null,
			'email'      => $email,
			'phone'      => $phone,
		);
	}

	/**
	 * Find-or-create a customer from booking form data.
	 *
	 * An existing email wins: the row is updated with the latest name and phone
	 * rather than duplicated, because email is unique.
	 *
	 * @param array $data Raw form data.
	 * @return int|WP_Error Customer id.
	 */
	public static function upsert( array $data ) {
		global $wpdb;

		$clean = self::validate( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$table    = SBK_Tables::customers();
		$now      = SBK_Helpers::now_utc();
		$existing = self::find_by_email( $clean['email'] );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'first_name' => $clean['first_name'],
					'last_name'  => $clean['last_name'],
					'phone'      => $clean['phone'],
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing->id;
		}

		// A WP user may already exist for this email (staff, or a past account).
		$user    = get_user_by( 'email', $clean['email'] );
		$user_id = $user ? (int) $user->ID : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'first_name' => $clean['first_name'],
				'last_name'  => $clean['last_name'],
				'email'      => $clean['email'],
				'phone'      => $clean['phone'],
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $wpdb->last_error ) {
			return new WP_Error( 'sbk_db_error', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Create a WP user for a customer who ticked "create an account".
	 *
	 * The password is never handled here: WordPress emails the customer a
	 * set-password link, the same way it does for any new account.
	 *
	 * @param int $customer_id Customer id.
	 * @return int|WP_Error User id.
	 */
	public static function create_account( $customer_id ) {
		global $wpdb;

		$customer = self::find( $customer_id );

		if ( ! $customer ) {
			return new WP_Error( 'sbk_customer_missing', __( 'Ο πελάτης δεν βρέθηκε.', 'simple-booking' ) );
		}

		if ( $customer->user_id ) {
			return (int) $customer->user_id;
		}

		$existing = get_user_by( 'email', $customer->email );

		if ( $existing ) {
			self::link_user( $customer_id, (int) $existing->ID );

			return (int) $existing->ID;
		}

		$username = self::unique_username( $customer->email );
		$user_id  = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $customer->email,
				'user_pass'  => wp_generate_password( 24, true, true ),
				'first_name' => $customer->first_name,
				'last_name'  => (string) $customer->last_name,
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		self::link_user( $customer_id, (int) $user_id );

		// Sends the "set your password" email; no password ever reaches us.
		wp_new_user_notification( $user_id, null, 'user' );

		return (int) $user_id;
	}

	/**
	 * Attach a WP user to a customer row.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $user_id     WP user id.
	 * @return void
	 */
	public static function link_user( $customer_id, $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			SBK_Tables::customers(),
			array(
				'user_id'    => (int) $user_id,
				'updated_at' => SBK_Helpers::now_utc(),
			),
			array( 'id' => (int) $customer_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Build a free username from an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function unique_username( $email ) {
		$base = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
		$base = '' === $base ? 'customer' : $base;

		if ( ! username_exists( $base ) ) {
			return $base;
		}

		$i = 2;

		while ( username_exists( $base . $i ) ) {
			++$i;
		}

		return $base . $i;
	}
}
