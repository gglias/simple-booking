<?php
/**
 * Data access for weekly working hours.
 *
 * Every time here is salon wall-clock (Europe/Athens), never UTC: a schedule
 * says "Maria works 09:00-17:00 on Mondays", which stays true across DST.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}sbk_schedules.
 */
class SBK_Schedule_Repository {

	/**
	 * All schedule rows for one staff member.
	 *
	 * @param int  $staff_id    Staff id.
	 * @param bool $only_active Skip disabled rows.
	 * @return array<int,object>
	 */
	public static function get_for_staff( $staff_id, $only_active = false ) {
		global $wpdb;

		$table = SBK_Tables::schedules();
		$sql   = "SELECT * FROM {$table} WHERE staff_id = %d";

		if ( $only_active ) {
			$sql .= ' AND is_active = 1';
		}

		$sql .= ' ORDER BY weekday ASC, start_time ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( $sql, (int) $staff_id ) );
	}

	/**
	 * Working intervals grouped by weekday.
	 *
	 * This is the shape the availability engine will consume.
	 *
	 * @param int  $staff_id    Staff id.
	 * @param bool $only_active Skip disabled rows.
	 * @return array<int,array<int,array{start:string,end:string}>> weekday => intervals.
	 */
	public static function get_week_map( $staff_id, $only_active = true ) {
		$map = array_fill_keys( array_keys( SBK_Helpers::weekday_labels() ), array() );

		foreach ( self::get_for_staff( $staff_id, $only_active ) as $row ) {
			$map[ (int) $row->weekday ][] = array(
				'start' => $row->start_time,
				'end'   => $row->end_time,
			);
		}

		return $map;
	}

	/**
	 * Replace a staff member's whole week.
	 *
	 * Validates everything first, so a bad interval never leaves the schedule
	 * half-written.
	 *
	 * @param int   $staff_id Staff id.
	 * @param array $week     weekday => array of array{start:string,end:string}.
	 * @return true|WP_Error
	 */
	public static function replace_for_staff( $staff_id, array $week ) {
		global $wpdb;

		$staff_id = (int) $staff_id;
		$labels   = SBK_Helpers::weekday_labels();
		$clean    = array();

		foreach ( $week as $weekday => $intervals ) {
			$weekday = (int) $weekday;

			if ( ! isset( $labels[ $weekday ] ) ) {
				continue;
			}

			$day = array();

			foreach ( (array) $intervals as $interval ) {
				$raw_start = isset( $interval['start'] ) ? trim( (string) $interval['start'] ) : '';
				$raw_end   = isset( $interval['end'] ) ? trim( (string) $interval['end'] ) : '';

				// A genuinely empty row just means "the user left it blank".
				if ( '' === $raw_start && '' === $raw_end ) {
					continue;
				}

				$start = SBK_Helpers::sanitize_time( $raw_start );
				$end   = SBK_Helpers::sanitize_time( $raw_end );

				// Half-filled or unparseable ("25:00") must not be dropped silently.
				if ( null === $start || null === $end ) {
					return new WP_Error(
						'sbk_schedule_incomplete',
						sprintf(
							/* translators: %s: weekday name */
							__( '%s: συμπλήρωσε έγκυρη ώρα έναρξης και ώρα λήξης.', 'simple-booking' ),
							$labels[ $weekday ]
						)
					);
				}

				if ( SBK_Helpers::time_to_minutes( $end ) <= SBK_Helpers::time_to_minutes( $start ) ) {
					return new WP_Error(
						'sbk_schedule_order',
						sprintf(
							/* translators: %s: weekday name */
							__( '%s: η ώρα λήξης πρέπει να είναι μετά την ώρα έναρξης.', 'simple-booking' ),
							$labels[ $weekday ]
						)
					);
				}

				$day[] = array(
					'start' => $start,
					'end'   => $end,
				);
			}

			usort(
				$day,
				static function ( $a, $b ) {
					return SBK_Helpers::time_to_minutes( $a['start'] ) <=> SBK_Helpers::time_to_minutes( $b['start'] );
				}
			);

			// Overlapping intervals would double-count availability.
			$count = count( $day );

			for ( $i = 1; $i < $count; $i++ ) {
				if ( SBK_Helpers::time_to_minutes( $day[ $i ]['start'] ) < SBK_Helpers::time_to_minutes( $day[ $i - 1 ]['end'] ) ) {
					return new WP_Error(
						'sbk_schedule_overlap',
						sprintf(
							/* translators: %s: weekday name */
							__( '%s: τα διαστήματα επικαλύπτονται.', 'simple-booking' ),
							$labels[ $weekday ]
						)
					);
				}
			}

			$clean[ $weekday ] = $day;
		}

		$table = SBK_Tables::schedules();
		$now   = SBK_Helpers::now_utc();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'staff_id' => $staff_id ), array( '%d' ) );

		foreach ( $clean as $weekday => $intervals ) {
			foreach ( $intervals as $interval ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$table,
					array(
						'staff_id'   => $staff_id,
						'weekday'    => $weekday,
						'start_time' => $interval['start'],
						'end_time'   => $interval['end'],
						'is_active'  => 1,
						'created_at' => $now,
						'updated_at' => $now,
					),
					array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
				);
			}
		}

		if ( $wpdb->last_error ) {
			return new WP_Error( 'sbk_db_error', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Copy one staff member's week onto another.
	 *
	 * @param int $from_staff_id Source staff id.
	 * @param int $to_staff_id   Target staff id.
	 * @return true|WP_Error
	 */
	public static function copy_week( $from_staff_id, $to_staff_id ) {
		$week  = self::get_week_map( $from_staff_id, false );
		$clean = array();

		foreach ( $week as $weekday => $intervals ) {
			$clean[ $weekday ] = array_map(
				static function ( $interval ) {
					return array(
						'start' => $interval['start'],
						'end'   => $interval['end'],
					);
				},
				$intervals
			);
		}

		return self::replace_for_staff( $to_staff_id, $clean );
	}

	/**
	 * How many working intervals each staff member has.
	 *
	 * @return array<int,int> staff_id => count.
	 */
	public static function count_per_staff() {
		global $wpdb;

		$table = SBK_Tables::schedules();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT staff_id, COUNT(*) AS total FROM {$table} WHERE is_active = 1 GROUP BY staff_id" );

		$counts = array();

		foreach ( $rows as $row ) {
			$counts[ (int) $row->staff_id ] = (int) $row->total;
		}

		return $counts;
	}
}
