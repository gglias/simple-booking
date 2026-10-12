<?php
/**
 * Data access for appointments.
 *
 * Conflict detection always uses the block_* pair, which already includes the
 * buffers; start_utc/end_utc is only what the customer is shown.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}sbk_appointments.
 */
class SBK_Appointment_Repository {

	/**
	 * Statuses that occupy the calendar.
	 *
	 * Cancelled appointments free their slot; no-shows are history, not a block
	 * on future bookings.
	 */
	const BLOCKING_STATUSES = array( 'confirmed', 'completed' );

	/**
	 * Blocked intervals for one staff member overlapping a UTC range.
	 *
	 * @param int    $staff_id  Staff id.
	 * @param string $start_utc Range start, MySQL datetime UTC.
	 * @param string $end_utc   Range end, MySQL datetime UTC.
	 * @return array<int,array{start:string,end:string}>
	 */
	public static function get_blocks( $staff_id, $start_utc, $end_utc ) {
		global $wpdb;

		$table        = SBK_Tables::appointments();
		$placeholders = implode( ', ', array_fill( 0, count( self::BLOCKING_STATUSES ), '%s' ) );

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
			"SELECT block_start_utc, block_end_utc FROM {$table}
			 WHERE staff_id = %d
			   AND status IN ({$placeholders})
			   AND block_start_utc < %s
			   AND block_end_utc > %s
			 ORDER BY block_start_utc ASC",
			array_merge( array( (int) $staff_id ), self::BLOCKING_STATUSES, array( $end_utc, $start_utc ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $sql );

		$blocks = array();

		foreach ( $rows as $row ) {
			$blocks[] = array(
				'start' => $row->block_start_utc,
				'end'   => $row->block_end_utc,
			);
		}

		return $blocks;
	}

	/**
	 * Whether a proposed block collides with an existing appointment.
	 *
	 * Half-open comparison: an appointment ending exactly when another starts
	 * is not a conflict.
	 *
	 * @param int    $staff_id        Staff id.
	 * @param string $block_start_utc Proposed block start.
	 * @param string $block_end_utc   Proposed block end.
	 * @param int    $exclude_id      Appointment to ignore (when rescheduling).
	 * @return bool
	 */
	public static function has_conflict( $staff_id, $block_start_utc, $block_end_utc, $exclude_id = 0 ) {
		global $wpdb;

		$table        = SBK_Tables::appointments();
		$placeholders = implode( ', ', array_fill( 0, count( self::BLOCKING_STATUSES ), '%s' ) );

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
			"SELECT COUNT(*) FROM {$table}
			 WHERE staff_id = %d
			   AND id <> %d
			   AND status IN ({$placeholders})
			   AND block_start_utc < %s
			   AND block_end_utc > %s",
			array_merge(
				array( (int) $staff_id, (int) $exclude_id ),
				self::BLOCKING_STATUSES,
				array( $block_end_utc, $block_start_utc )
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $sql ) > 0;
	}

	/**
	 * Single appointment row.
	 *
	 * @param int $id Appointment id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = SBK_Tables::appointments();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Look an appointment up by its manage token.
	 *
	 * @param string $token Manage token.
	 * @return object|null
	 */
	public static function find_by_token( $token ) {
		global $wpdb;

		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );

		if ( 32 !== strlen( $token ) ) {
			return null;
		}

		$table = SBK_Tables::appointments();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE manage_token = %s", $token ) );
	}

	/**
	 * The extras attached to an appointment.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return array<int,object>
	 */
	public static function get_extras( $appointment_id ) {
		global $wpdb;

		$table = SBK_Tables::appointment_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE appointment_id = %d ORDER BY id ASC", (int) $appointment_id ) );
	}

	/**
	 * Insert an appointment row.
	 *
	 * Callers are expected to have validated the slot; SBK_Booking_Service is
	 * the supported entry point.
	 *
	 * @param array $row Column => value.
	 * @return int|WP_Error Appointment id.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( SBK_Tables::appointments(), $row );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'sbk_db_error', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert one snapshotted extra line.
	 *
	 * @param int   $appointment_id Appointment id.
	 * @param array $extra          Extra data.
	 * @return void
	 */
	public static function insert_extra( $appointment_id, array $extra ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			SBK_Tables::appointment_extras(),
			array(
				'appointment_id' => (int) $appointment_id,
				'extra_id'       => (int) $extra['id'],
				'extra_name'     => $extra['name'],
				'quantity'       => 1,
				'duration_min'   => (int) $extra['duration_min'],
				'price_cents'    => (int) $extra['price_cents'],
			),
			array( '%d', '%d', '%s', '%d', '%d', '%d' )
		);
	}

	/**
	 * Generate a manage token that is not already taken.
	 *
	 * @return string
	 */
	public static function generate_token() {
		global $wpdb;

		$table = SBK_Tables::appointments();

		do {
			$token = wp_generate_password( 32, false, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE manage_token = %s", $token ) );
		} while ( $taken > 0 );

		return $token;
	}

	/**
	 * Appointments for one customer, newest first.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $limit       Max rows.
	 * @return array<int,object>
	 */
	public static function get_for_customer( $customer_id, $limit = 50 ) {
		global $wpdb;

		$table = SBK_Tables::appointments();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY start_utc DESC LIMIT %d",
				(int) $customer_id,
				(int) $limit
			)
		);
	}

	/**
	 * Status code => label, in the order they appear in the admin.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'confirmed' => __( 'Επιβεβαιωμένο', 'simple-booking' ),
			'completed' => __( 'Ολοκληρώθηκε', 'simple-booking' ),
			'cancelled' => __( 'Ακυρώθηκε', 'simple-booking' ),
			'no_show'   => __( 'Δεν ήρθε', 'simple-booking' ),
		);
	}

	/**
	 * One appointment with its customer joined in.
	 *
	 * @param int $id Appointment id.
	 * @return object|null
	 */
	public static function find_with_customer( $id ) {
		global $wpdb;

		$appointments = SBK_Tables::appointments();
		$customers    = SBK_Tables::customers();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, c.first_name, c.last_name, c.email, c.phone, c.user_id AS customer_user_id
				 FROM {$appointments} a
				 LEFT JOIN {$customers} c ON c.id = a.customer_id
				 WHERE a.id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Filtered, paginated list for the admin.
	 *
	 * Dates in $args are salon local dates; they are converted to the UTC range
	 * the rows are actually stored in.
	 *
	 * @param array $args {
	 *     @type string $from     Local date, inclusive.
	 *     @type string $to       Local date, inclusive.
	 *     @type int    $staff_id 0 for any.
	 *     @type string $status   Empty for any.
	 *     @type string $search   Name, email or phone.
	 *     @type int    $per_page Rows per page.
	 *     @type int    $page     1-based page number.
	 *     @type string $order    ASC or DESC by start time.
	 * }
	 * @return array{items:array,total:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'from'     => '',
				'to'       => '',
				'staff_id' => 0,
				'status'   => '',
				'search'   => '',
				'per_page' => 30,
				'page'     => 1,
				'order'    => 'ASC',
			)
		);

		$appointments = SBK_Tables::appointments();
		$customers    = SBK_Tables::customers();
		$where        = array( '1=1' );
		$vals         = array();

		if ( '' !== $args['from'] ) {
			$where[] = 'a.start_utc >= %s';
			$vals[]  = SBK_Helpers::local_to_utc( $args['from'], '00:00:00' );
		}

		if ( '' !== $args['to'] ) {
			$where[] = 'a.start_utc <= %s';
			$vals[]  = SBK_Helpers::local_to_utc( $args['to'], '23:59:59' );
		}

		if ( $args['staff_id'] > 0 ) {
			$where[] = 'a.staff_id = %d';
			$vals[]  = (int) $args['staff_id'];
		}

		if ( '' !== $args['status'] ) {
			$where[] = 'a.status = %s';
			$vals[]  = $args['status'];
		}

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
			array_push( $vals, $like, $like, $like, $like );
		}

		$clause   = implode( ' AND ', $where );
		$order    = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$count_sql = "SELECT COUNT(*) FROM {$appointments} a LEFT JOIN {$customers} c ON c.id = a.customer_id WHERE {$clause}";
		$list_sql  = "SELECT a.*, c.first_name, c.last_name, c.email, c.phone
			FROM {$appointments} a
			LEFT JOIN {$customers} c ON c.id = a.customer_id
			WHERE {$clause}
			ORDER BY a.start_utc {$order}
			LIMIT %d OFFSET %d";

		if ( $vals ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
			$count_sql = $wpdb->prepare( $count_sql, $vals );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$list_sql = $wpdb->prepare( $list_sql, array_merge( $vals, array( $per_page, $offset ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $count_sql );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$items = $wpdb->get_results( $list_sql );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * One local day's appointments, grouped by staff member.
	 *
	 * @param string $date Local Y-m-d.
	 * @param bool   $all  Include cancelled and no-shows.
	 * @return array<int,array<int,object>> staff_id => rows.
	 */
	public static function get_for_day( $date, $all = true ) {
		$result = self::query(
			array(
				'from'     => $date,
				'to'       => $date,
				'per_page' => 500,
				'order'    => 'ASC',
			)
		);

		$grouped = array();

		foreach ( $result['items'] as $row ) {
			if ( ! $all && ! in_array( $row->status, self::BLOCKING_STATUSES, true ) ) {
				continue;
			}

			$grouped[ (int) $row->staff_id ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Change an appointment's status.
	 *
	 * Cancelling frees the slot, because cancelled rows are not in
	 * BLOCKING_STATUSES and so stop counting against availability.
	 *
	 * @param int    $id     Appointment id.
	 * @param string $status New status.
	 * @return true|WP_Error
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;

		$statuses = self::statuses();

		if ( ! isset( $statuses[ $status ] ) ) {
			return new WP_Error( 'sbk_bad_status', __( 'Άγνωστη κατάσταση.', 'simple-booking' ) );
		}

		// Keep a record of when it was cancelled; clear it if it is reinstated.
		$row = array(
			'status'       => $status,
			'cancelled_at' => 'cancelled' === $status ? SBK_Helpers::now_utc() : null,
			'updated_at'   => SBK_Helpers::now_utc(),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( SBK_Tables::appointments(), $row, array( 'id' => (int) $id ), array( '%s', '%s', '%s' ), array( '%d' ) );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'sbk_db_error', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Save the internal note on an appointment.
	 *
	 * @param int    $id   Appointment id.
	 * @param string $note Admin note.
	 * @return void
	 */
	public static function update_admin_note( $id, $note ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			SBK_Tables::appointments(),
			array(
				'admin_note' => '' === $note ? null : $note,
				'updated_at' => SBK_Helpers::now_utc(),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Count of upcoming confirmed appointments.
	 *
	 * @return int
	 */
	public static function count_upcoming() {
		global $wpdb;

		$table = SBK_Tables::appointments();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND start_utc >= %s",
				'confirmed',
				SBK_Helpers::now_utc()
			)
		);
	}
}
