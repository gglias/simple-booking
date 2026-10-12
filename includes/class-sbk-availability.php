<?php
/**
 * Availability engine: turns schedules, exceptions and existing appointments
 * into bookable start times.
 *
 * The rules, in one place:
 * - Working hours are wall-clock (Europe/Athens). Slots are built in local time
 *   and converted to UTC one by one, so DST transitions cannot shift them.
 * - The customer-visible interval [start, start + duration] must fit entirely
 *   inside a working window.
 * - The block interval [start - buffer_before, start + duration + buffer_after]
 *   is what gets compared against other appointments. Buffers may therefore run
 *   past closing time, but never into someone else's appointment.
 * - Precedence for a date: any "closed" exception wins; then staff-specific
 *   custom hours; then shop-wide custom hours; then the weekly schedule.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Computes bookable slots.
 */
class SBK_Availability {

	/**
	 * Resolve what a booking actually costs in time and money.
	 *
	 * Staff overrides win over the service defaults; extras add on top.
	 *
	 * @param int   $staff_id   Staff id.
	 * @param int   $service_id Service id.
	 * @param int[] $extra_ids  Selected extra ids.
	 * @return array|WP_Error {
	 *     @type int $duration_min      Customer-visible minutes.
	 *     @type int $buffer_before_min Minutes blocked before.
	 *     @type int $buffer_after_min  Minutes blocked after.
	 *     @type int $service_cents     Service price snapshot.
	 *     @type int $extras_cents      Extras price total.
	 *     @type int $total_cents       Sum of both.
	 * }
	 */
	public static function resolve_booking( $staff_id, $service_id, $extra_ids = array() ) {
		$service = SBK_Service_Repository::find( $service_id );

		if ( ! $service || ! $service->is_active ) {
			return new WP_Error( 'sbk_service_missing', __( 'Η υπηρεσία δεν είναι διαθέσιμη.', 'simple-booking' ) );
		}

		$staff = SBK_Staff_Repository::find( $staff_id );

		if ( ! $staff || ! $staff->is_active ) {
			return new WP_Error( 'sbk_staff_missing', __( 'Το άτομο δεν είναι διαθέσιμο.', 'simple-booking' ) );
		}

		$assigned = SBK_Staff_Repository::get_services_map( $staff_id );

		if ( ! isset( $assigned[ (int) $service_id ] ) ) {
			return new WP_Error( 'sbk_not_assigned', __( 'Το άτομο δεν εκτελεί αυτή την υπηρεσία.', 'simple-booking' ) );
		}

		$override = $assigned[ (int) $service_id ];

		$duration = null === $override['duration_min'] ? (int) $service->duration_min : (int) $override['duration_min'];
		$price    = null === $override['price_cents'] ? (int) $service->price_cents : (int) $override['price_cents'];

		$extras = self::resolve_extras( $service_id, $extra_ids );

		if ( is_wp_error( $extras ) ) {
			return $extras;
		}

		return array(
			'duration_min'      => $duration + $extras['duration_min'],
			'buffer_before_min' => (int) $service->buffer_before_min,
			'buffer_after_min'  => (int) $service->buffer_after_min,
			'service_cents'     => $price,
			'extras_cents'      => $extras['price_cents'],
			'total_cents'       => $price + $extras['price_cents'],
			'extras'            => $extras['items'],
		);
	}

	/**
	 * Validate and total the selected extras.
	 *
	 * @param int   $service_id Service id.
	 * @param int[] $extra_ids  Selected extra ids.
	 * @return array|WP_Error
	 */
	private static function resolve_extras( $service_id, $extra_ids ) {

		$extra_ids = array_values( array_unique( array_map( 'intval', (array) $extra_ids ) ) );

		if ( ! $extra_ids ) {
			return array(
				'duration_min' => 0,
				'price_cents'  => 0,
				'items'        => array(),
			);
		}

		$rows = SBK_Extra_Repository::find_for_service( $service_id, $extra_ids );

		if ( count( $rows ) !== count( $extra_ids ) ) {
			return new WP_Error( 'sbk_extra_invalid', __( 'Κάποιο extra δεν είναι διαθέσιμο για αυτή την υπηρεσία.', 'simple-booking' ) );
		}

		$duration = 0;
		$price    = 0;
		$items    = array();

		foreach ( $rows as $row ) {
			$duration += (int) $row->duration_min;
			$price    += (int) $row->price_cents;

			$items[] = array(
				'id'           => (int) $row->id,
				'name'         => $row->name,
				'duration_min' => (int) $row->duration_min,
				'price_cents'  => (int) $row->price_cents,
			);
		}

		return array(
			'duration_min' => $duration,
			'price_cents'  => $price,
			'items'        => $items,
		);
	}

	/**
	 * Working windows for a staff member on a local date.
	 *
	 * @param int    $staff_id Staff id.
	 * @param string $date     Y-m-d in salon local time.
	 * @return array<int,array{start:string,end:string}> Wall-clock windows.
	 */
	public static function get_working_windows( $staff_id, $date ) {
		$staff_id   = (int) $staff_id;
		$exceptions = SBK_Exception_Repository::get_for_range( $staff_id, $date, $date );
		$rows       = isset( $exceptions[ $date ] ) ? $exceptions[ $date ] : array();

		$staff_custom = array();
		$shop_custom  = array();

		foreach ( $rows as $row ) {
			if ( (int) $row->is_closed ) {
				return array();
			}

			$window = array(
				'start' => $row->start_time,
				'end'   => $row->end_time,
			);

			if ( (int) $row->staff_id === $staff_id ) {
				$staff_custom[] = $window;
			} else {
				$shop_custom[] = $window;
			}
		}

		if ( $staff_custom ) {
			return $staff_custom;
		}

		if ( $shop_custom ) {
			return $shop_custom;
		}

		$dt = DateTime::createFromFormat( 'Y-m-d', $date, SBK_Helpers::salon_timezone() );

		if ( ! $dt ) {
			return array();
		}

		$week = SBK_Schedule_Repository::get_week_map( $staff_id );

		return $week[ (int) $dt->format( 'w' ) ];
	}

	/**
	 * Bookable start times for one staff member, service and local date.
	 *
	 * @param int    $staff_id   Staff id.
	 * @param int    $service_id Service id.
	 * @param string $date       Y-m-d in salon local time.
	 * @param int[]  $extra_ids  Selected extra ids.
	 * @return array|WP_Error List of slots.
	 */
	public static function get_slots( $staff_id, $service_id, $date, $extra_ids = array() ) {
		$booking = self::resolve_booking( $staff_id, $service_id, $extra_ids );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$tz  = SBK_Helpers::salon_timezone();
		$day = DateTime::createFromFormat( 'Y-m-d', $date, $tz );

		if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'sbk_bad_date', __( 'Η ημερομηνία δεν είναι έγκυρη.', 'simple-booking' ) );
		}

		$windows = self::get_working_windows( $staff_id, $date );

		if ( ! $windows ) {
			return array();
		}

		$step     = max( 5, SBK_Settings::get( 'slot_step_min' ) );
		$duration = (int) $booking['duration_min'];
		$before   = (int) $booking['buffer_before_min'];
		$after    = (int) $booking['buffer_after_min'];

		// Earliest and latest UTC moments this date can produce, plus buffers.
		$day_start_utc = self::local_to_utc( $date, '00:00:00', -$before );
		$day_end_utc   = self::local_to_utc( $date, '23:59:59', $after + $duration );
		$blocks        = SBK_Appointment_Repository::get_blocks( $staff_id, $day_start_utc, $day_end_utc );

		$earliest = self::earliest_bookable_utc();
		$latest   = self::latest_bookable_utc();
		$slots    = array();

		foreach ( $windows as $window ) {
			$window_start = SBK_Helpers::time_to_minutes( $window['start'] );
			$window_end   = SBK_Helpers::time_to_minutes( $window['end'] );

			for ( $minute = $window_start; $minute + $duration <= $window_end; $minute += $step ) {
				$start_utc = self::local_to_utc( $date, self::minutes_to_time( $minute ) );
				$end_utc   = self::local_to_utc( $date, self::minutes_to_time( $minute ), $duration );

				if ( $start_utc < $earliest || $start_utc > $latest ) {
					continue;
				}

				$block_start = self::local_to_utc( $date, self::minutes_to_time( $minute ), -$before );
				$block_end   = self::local_to_utc( $date, self::minutes_to_time( $minute ), $duration + $after );

				if ( self::overlaps( $blocks, $block_start, $block_end ) ) {
					continue;
				}

				$slots[] = array(
					'time'            => substr( self::minutes_to_time( $minute ), 0, 5 ),
					'start_utc'       => $start_utc,
					'end_utc'         => $end_utc,
					'block_start_utc' => $block_start,
					'block_end_utc'   => $block_end,
				);
			}
		}

		return $slots;
	}

	/**
	 * Which dates in a range have at least one slot.
	 *
	 * Used by the calendar step to grey out full or closed days.
	 *
	 * @param int    $staff_id   Staff id.
	 * @param int    $service_id Service id.
	 * @param string $from       Y-m-d.
	 * @param string $to         Y-m-d.
	 * @param int[]  $extra_ids  Selected extra ids.
	 * @return array<string,int>|WP_Error date => slot count.
	 */
	public static function get_days( $staff_id, $service_id, $from, $to, $extra_ids = array() ) {
		$tz    = SBK_Helpers::salon_timezone();
		$start = DateTime::createFromFormat( 'Y-m-d', $from, $tz );
		$end   = DateTime::createFromFormat( 'Y-m-d', $to, $tz );

		if ( ! $start || ! $end || $end < $start ) {
			return new WP_Error( 'sbk_bad_range', __( 'Το εύρος ημερομηνιών δεν είναι έγκυρο.', 'simple-booking' ) );
		}

		$days   = array();
		$cursor = clone $start;

		while ( $cursor <= $end ) {
			$date  = $cursor->format( 'Y-m-d' );
			$slots = self::get_slots( $staff_id, $service_id, $date, $extra_ids );

			if ( is_wp_error( $slots ) ) {
				return $slots;
			}

			$days[ $date ] = count( $slots );
			$cursor->modify( '+1 day' );
		}

		return $days;
	}

	/**
	 * Re-check a specific slot at submission time.
	 *
	 * The customer may have sat on step 7 while someone else booked, so this
	 * runs again right before the insert.
	 *
	 * @param int    $staff_id   Staff id.
	 * @param int    $service_id Service id.
	 * @param string $date       Y-m-d local.
	 * @param string $time       HH:MM local.
	 * @param int[]  $extra_ids  Selected extra ids.
	 * @return array|WP_Error The matching slot.
	 */
	public static function validate_slot( $staff_id, $service_id, $date, $time, $extra_ids = array() ) {
		$slots = self::get_slots( $staff_id, $service_id, $date, $extra_ids );

		if ( is_wp_error( $slots ) ) {
			return $slots;
		}

		if ( ! $slots ) {
			return new WP_Error( 'sbk_no_slots', __( 'Δεν υπάρχουν διαθέσιμες ώρες αυτή την ημερομηνία.', 'simple-booking' ) );
		}

		$time = substr( (string) $time, 0, 5 );

		foreach ( $slots as $slot ) {
			if ( $slot['time'] === $time ) {
				return $slot;
			}
		}

		return new WP_Error( 'sbk_slot_taken', __( 'Η ώρα δεν είναι πλέον διαθέσιμη. Διάλεξε άλλη.', 'simple-booking' ) );
	}

	/**
	 * Convert a salon wall-clock moment to a UTC MySQL datetime.
	 *
	 * @param string $date        Y-m-d local.
	 * @param string $time        HH:MM:SS local.
	 * @param int    $offset_min  Minutes to add after the conversion point.
	 * @return string
	 */
	private static function local_to_utc( $date, $time, $offset_min = 0 ) {
		$local = new DateTime( $date . ' ' . $time, SBK_Helpers::salon_timezone() );

		if ( $offset_min ) {
			$local->modify( sprintf( '%+d minutes', $offset_min ) );
		}

		$local->setTimezone( new DateTimeZone( 'UTC' ) );

		return $local->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Minutes since midnight to HH:MM:SS.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	private static function minutes_to_time( $minutes ) {
		return sprintf( '%02d:%02d:00', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * Earliest UTC start a customer may book, honouring the lead time.
	 *
	 * @return string
	 */
	private static function earliest_bookable_utc() {
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$now->modify( sprintf( '+%d minutes', SBK_Settings::get( 'lead_time_min' ) ) );

		return $now->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Latest UTC start a customer may book.
	 *
	 * @return string
	 */
	private static function latest_bookable_utc() {
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$now->modify( sprintf( '+%d days', SBK_Settings::get( 'max_advance_days' ) ) );

		return $now->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Whether a proposed block overlaps any existing one.
	 *
	 * @param array  $blocks Existing blocks.
	 * @param string $start  Proposed block start, UTC.
	 * @param string $end    Proposed block end, UTC.
	 * @return bool
	 */
	private static function overlaps( array $blocks, $start, $end ) {
		foreach ( $blocks as $block ) {
			if ( $block['start'] < $end && $block['end'] > $start ) {
				return true;
			}
		}

		return false;
	}
}
