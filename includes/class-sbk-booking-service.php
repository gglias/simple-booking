<?php
/**
 * Creating an appointment: the one supported path from form data to a row.
 *
 * Everything priced or named is snapshotted onto the appointment, so later
 * edits to the price list or to a staff member's name never rewrite history.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates booking creation.
 */
class SBK_Booking_Service {

	/**
	 * Seconds to wait for the per-staff lock before giving up.
	 */
	const LOCK_TIMEOUT = 5;

	/**
	 * Create a confirmed appointment.
	 *
	 * @param array $input {
	 *     @type int    $staff_id       Required.
	 *     @type int    $service_id     Required.
	 *     @type string $date           Y-m-d in salon local time.
	 *     @type string $time           HH:MM in salon local time.
	 *     @type int[]  $extras         Selected extra ids.
	 *     @type array  $customer       first_name, last_name, email, phone.
	 *     @type string $note           Customer note.
	 *     @type bool   $create_account Whether to create a WP user.
	 *     @type string $created_via    'frontend' or 'admin'.
	 * }
	 * @return array|WP_Error Appointment, customer and extras on success.
	 */
	public static function create( array $input ) {
		global $wpdb;

		$staff_id   = isset( $input['staff_id'] ) ? (int) $input['staff_id'] : 0;
		$service_id = isset( $input['service_id'] ) ? (int) $input['service_id'] : 0;
		$date       = isset( $input['date'] ) ? sanitize_text_field( $input['date'] ) : '';
		$time       = isset( $input['time'] ) ? sanitize_text_field( $input['time'] ) : '';
		$extra_ids  = isset( $input['extras'] ) ? array_map( 'intval', (array) $input['extras'] ) : array();

		// Resolve duration, buffers and prices, honouring per-staff overrides.
		$booking = SBK_Availability::resolve_booking( $staff_id, $service_id, $extra_ids );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		// Contact details are validated before anything is written.
		$contact = SBK_Customer_Repository::validate( isset( $input['customer'] ) ? (array) $input['customer'] : array() );

		if ( is_wp_error( $contact ) ) {
			return $contact;
		}

		$slot = SBK_Availability::validate_slot( $staff_id, $service_id, $date, $time, $extra_ids );

		if ( is_wp_error( $slot ) ) {
			return $slot;
		}

		$customer_id = SBK_Customer_Repository::upsert( $contact );

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$staff   = SBK_Staff_Repository::find( $staff_id );
		$service = SBK_Service_Repository::find( $service_id );

		// Serialise bookings per staff member: two customers submitting the same
		// slot at the same moment must not both pass the conflict check.
		$lock = self::lock_name( $staff_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_TIMEOUT ) );

		if ( 1 !== $locked ) {
			return new WP_Error( 'sbk_busy', __( 'Το σύστημα είναι απασχολημένο. Δοκίμασε ξανά.', 'simple-booking' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'START TRANSACTION' );

		// Re-check inside the lock: the slot may have gone in the meantime.
		if ( SBK_Appointment_Repository::has_conflict( $staff_id, $slot['block_start_utc'], $slot['block_end_utc'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			self::release( $lock );

			return new WP_Error( 'sbk_slot_taken', __( 'Η ώρα μόλις κλείστηκε. Διάλεξε άλλη.', 'simple-booking' ) );
		}

		$now = SBK_Helpers::now_utc();

		$appointment_id = SBK_Appointment_Repository::insert(
			array(
				'staff_id'            => $staff_id,
				'service_id'          => $service_id,
				'customer_id'         => (int) $customer_id,
				'status'              => 'confirmed',
				'start_utc'           => $slot['start_utc'],
				'end_utc'             => $slot['end_utc'],
				'block_start_utc'     => $slot['block_start_utc'],
				'block_end_utc'       => $slot['block_end_utc'],
				'staff_name'          => $staff->display_name,
				'service_name'        => $service->name,
				'duration_min'        => (int) $booking['duration_min'],
				'buffer_before_min'   => (int) $booking['buffer_before_min'],
				'buffer_after_min'    => (int) $booking['buffer_after_min'],
				'service_price_cents' => (int) $booking['service_cents'],
				'extras_price_cents'  => (int) $booking['extras_cents'],
				'total_price_cents'   => (int) $booking['total_cents'],
				'customer_note'       => isset( $input['note'] ) ? sanitize_textarea_field( $input['note'] ) : null,
				'manage_token'        => SBK_Appointment_Repository::generate_token(),
				'created_via'         => isset( $input['created_via'] ) && 'admin' === $input['created_via'] ? 'admin' : 'frontend',
				'created_by'          => get_current_user_id() ? get_current_user_id() : null,
				'created_at'          => $now,
				'updated_at'          => $now,
			)
		);

		if ( is_wp_error( $appointment_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			self::release( $lock );

			return $appointment_id;
		}

		foreach ( $booking['extras'] as $extra ) {
			SBK_Appointment_Repository::insert_extra( $appointment_id, $extra );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'COMMIT' );
		self::release( $lock );

		// Account creation happens after the booking is safe: a failure here
		// must not cost the customer their appointment.
		$account = null;

		if ( ! empty( $input['create_account'] ) ) {
			$account = SBK_Customer_Repository::create_account( $customer_id );
		}

		$appointment = SBK_Appointment_Repository::find( $appointment_id );

		/**
		 * Fires once an appointment is confirmed and stored.
		 *
		 * @param object $appointment The stored row.
		 * @param array  $context     Customer id, extras and account result.
		 */
		do_action(
			'sbk_appointment_created',
			$appointment,
			array(
				'customer_id' => (int) $customer_id,
				'extras'      => $booking['extras'],
				'account'     => $account,
			)
		);

		return array(
			'appointment'  => $appointment,
			'customer'     => SBK_Customer_Repository::find( $customer_id ),
			'extras'       => SBK_Appointment_Repository::get_extras( $appointment_id ),
			'account_user' => is_wp_error( $account ) ? null : $account,
			'account_error' => is_wp_error( $account ) ? $account->get_error_message() : null,
		);
	}

	/**
	 * Name of the per-staff advisory lock.
	 *
	 * Scoped with the table prefix so two sites on one MySQL server never share
	 * a lock.
	 *
	 * @param int $staff_id Staff id.
	 * @return string
	 */
	private static function lock_name( $staff_id ) {
		global $wpdb;

		return 'sbk_' . $wpdb->prefix . 'staff_' . (int) $staff_id;
	}

	/**
	 * Release an advisory lock.
	 *
	 * @param string $lock Lock name.
	 * @return void
	 */
	private static function release( $lock ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}
