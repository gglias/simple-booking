<?php
/**
 * Data access for date-specific schedule overrides.
 *
 * staff_id = 0 means the whole salon. Dates and times are salon wall-clock.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}sbk_exceptions.
 */
class SBK_Exception_Repository {

	/**
	 * Shop-wide exceptions use this staff id.
	 */
	const ALL_STAFF = 0;

	/**
	 * Fetch exceptions.
	 *
	 * @param array $args {
	 *     @type int    $staff_id      Filter by staff; -1 for any.
	 *     @type bool   $include_shop  When filtering by staff, also return shop-wide rows.
	 *     @type string $from          Only dates on/after this Y-m-d.
	 *     @type string $to            Only dates on/before this Y-m-d.
	 * }
	 * @return array<int,object>
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'staff_id'     => -1,
				'include_shop' => true,
				'from'         => '',
				'to'           => '',
			)
		);

		$table = SBK_Tables::exceptions();
		$where = array( '1=1' );
		$vals  = array();

		if ( $args['staff_id'] >= 0 ) {
			if ( $args['include_shop'] && self::ALL_STAFF !== (int) $args['staff_id'] ) {
				$where[] = '(staff_id = %d OR staff_id = 0)';
			} else {
				$where[] = 'staff_id = %d';
			}

			$vals[] = (int) $args['staff_id'];
		}

		if ( '' !== $args['from'] ) {
			$where[] = 'exception_date >= %s';
			$vals[]  = $args['from'];
		}

		if ( '' !== $args['to'] ) {
			$where[] = 'exception_date <= %s';
			$vals[]  = $args['to'];
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY exception_date ASC, start_time ASC';

		if ( $vals ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
			$sql = $wpdb->prepare( $sql, $vals );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $sql );
	}

	/**
	 * Single exception row.
	 *
	 * @param int $id Exception id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = SBK_Tables::exceptions();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Exceptions that apply to one staff member on a date range.
	 *
	 * Shop-wide rows are always included; the availability engine applies
	 * "closed wins" over any custom window.
	 *
	 * @param int    $staff_id Staff id.
	 * @param string $from     Y-m-d.
	 * @param string $to       Y-m-d.
	 * @return array<string,array<int,object>> date => rows.
	 */
	public static function get_for_range( $staff_id, $from, $to ) {
		$rows = self::get_all(
			array(
				'staff_id'     => (int) $staff_id,
				'include_shop' => true,
				'from'         => $from,
				'to'           => $to,
			)
		);

		$by_date = array();

		foreach ( $rows as $row ) {
			$by_date[ $row->exception_date ][] = $row;
		}

		return $by_date;
	}

	/**
	 * Insert or update an exception.
	 *
	 * @param array $data Raw form data.
	 * @param int   $id   Existing id, 0 to insert.
	 * @return int|WP_Error
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$id   = (int) $id;
		$date = isset( $data['exception_date'] ) ? trim( (string) $data['exception_date'] ) : '';
		$dt   = DateTime::createFromFormat( 'Y-m-d', $date, SBK_Helpers::salon_timezone() );

		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'sbk_exception_date', __( 'Η ημερομηνία δεν είναι έγκυρη.', 'simple-booking' ) );
		}

		$staff_id  = isset( $data['staff_id'] ) ? (int) $data['staff_id'] : self::ALL_STAFF;
		$is_closed = empty( $data['is_closed'] ) ? 0 : 1;
		$start     = null;
		$end       = null;

		if ( ! $is_closed ) {
			$start = SBK_Helpers::sanitize_time( isset( $data['start_time'] ) ? $data['start_time'] : '' );
			$end   = SBK_Helpers::sanitize_time( isset( $data['end_time'] ) ? $data['end_time'] : '' );

			if ( null === $start || null === $end ) {
				return new WP_Error( 'sbk_exception_times', __( 'Για ειδικό ωράριο χρειάζονται ώρα έναρξης και λήξης.', 'simple-booking' ) );
			}

			if ( SBK_Helpers::time_to_minutes( $end ) <= SBK_Helpers::time_to_minutes( $start ) ) {
				return new WP_Error( 'sbk_exception_order', __( 'Η ώρα λήξης πρέπει να είναι μετά την ώρα έναρξης.', 'simple-booking' ) );
			}
		}

		$conflict = self::find_conflict( $staff_id, $date, $is_closed, $id );

		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		$row = array(
			'staff_id'       => $staff_id,
			'exception_date' => $date,
			'is_closed'      => $is_closed,
			'start_time'     => $start,
			'end_time'       => $end,
			'note'           => isset( $data['note'] ) ? sanitize_text_field( $data['note'] ) : null,
			'updated_at'     => SBK_Helpers::now_utc(),
		);

		$formats = array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' );
		$table   = SBK_Tables::exceptions();

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$row['created_at'] = SBK_Helpers::now_utc();
			$formats[]         = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $row, $formats );
			$id = (int) $wpdb->insert_id;
		}

		if ( $wpdb->last_error ) {
			return new WP_Error( 'sbk_db_error', $wpdb->last_error );
		}

		return $id;
	}

	/**
	 * Reject combinations that contradict each other on the same date.
	 *
	 * A "closed" row and a custom window for the same staff and date would need
	 * a precedence rule the admin cannot see, so we refuse instead of guessing.
	 *
	 * @param int    $staff_id  Staff id.
	 * @param string $date      Y-m-d.
	 * @param int    $is_closed Whether the new row closes the day.
	 * @param int    $exclude   Row id to ignore (itself, on update).
	 * @return true|WP_Error
	 */
	private static function find_conflict( $staff_id, $date, $is_closed, $exclude = 0 ) {
		global $wpdb;

		$table = SBK_Tables::exceptions();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, is_closed FROM {$table} WHERE staff_id = %d AND exception_date = %s AND id <> %d",
				(int) $staff_id,
				$date,
				(int) $exclude
			)
		);

		foreach ( $rows as $row ) {
			if ( $is_closed || (int) $row->is_closed === 1 ) {
				return new WP_Error(
					'sbk_exception_conflict',
					__( 'Υπάρχει ήδη καταχώρηση για αυτή την ημερομηνία. Διέγραψέ την πρώτα.', 'simple-booking' )
				);
			}
		}

		return true;
	}

	/**
	 * Delete an exception.
	 *
	 * @param int $id Exception id.
	 * @return true
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::exceptions(), array( 'id' => (int) $id ), array( '%d' ) );

		return true;
	}
}
