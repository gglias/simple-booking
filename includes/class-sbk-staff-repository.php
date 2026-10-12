<?php
/**
 * Data access for staff and their service assignments.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}sbk_staff and {prefix}sbk_staff_services.
 */
class SBK_Staff_Repository {

	/**
	 * Fetch staff rows.
	 *
	 * @param array $args {
	 *     @type bool   $only_active Restrict to active staff.
	 *     @type string $search      Match against name, email or phone.
	 * }
	 * @return array<int,object>
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'only_active' => false,
				'search'      => '',
			)
		);

		$table = SBK_Tables::staff();
		$where = array( '1=1' );
		$vals  = array();

		if ( $args['only_active'] ) {
			$where[] = 'is_active = 1';
		}

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(display_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort_order ASC, display_name ASC';

		if ( $vals ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
			$sql = $wpdb->prepare( $sql, $vals );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $sql );
	}

	/**
	 * Single staff row.
	 *
	 * @param int $id Staff id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = SBK_Tables::staff();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Insert or update a staff member.
	 *
	 * @param array $data Raw, unsanitised form data.
	 * @param int   $id   Existing id, 0 to insert.
	 * @return int|WP_Error New/updated id.
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$name = isset( $data['display_name'] ) ? sanitize_text_field( $data['display_name'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'sbk_staff_name', __( 'Το όνομα είναι υποχρεωτικό.', 'simple-booking' ) );
		}

		// Validate the raw input: sanitize_email() silently empties bad addresses,
		// which would turn a typo into "no email at all".
		$email_raw = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
		$email     = '' === $email_raw ? '' : sanitize_email( $email_raw );

		if ( '' !== $email_raw && ! is_email( $email ) ) {
			return new WP_Error( 'sbk_staff_email', __( 'Το email δεν είναι έγκυρο.', 'simple-booking' ) );
		}

		$row = array(
			'display_name' => $name,
			'email'        => '' !== $email ? $email : null,
			'phone'        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
			'bio'          => isset( $data['bio'] ) ? wp_kses_post( $data['bio'] ) : null,
			'user_id'      => ! empty( $data['user_id'] ) ? (int) $data['user_id'] : null,
			'avatar_id'    => ! empty( $data['avatar_id'] ) ? (int) $data['avatar_id'] : null,
			'color'        => ! empty( $data['color'] ) ? sanitize_hex_color( $data['color'] ) : null,
			'is_active'    => empty( $data['is_active'] ) ? 0 : 1,
			'sort_order'   => SBK_Helpers::clamp_int( isset( $data['sort_order'] ) ? $data['sort_order'] : 0, 0, 65535 ),
			'updated_at'   => SBK_Helpers::now_utc(),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s' );
		$table   = SBK_Tables::staff();
		$id      = (int) $id;

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
	 * Delete a staff member, unless appointments reference them.
	 *
	 * Appointments snapshot the staff name, but keeping the row lets us show
	 * history and re-activate later, so deletion is blocked on purpose.
	 *
	 * @param int $id Staff id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id           = (int) $id;
		$appointments = SBK_Tables::appointments();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$appointments} WHERE staff_id = %d", $id ) );

		if ( $used > 0 ) {
			return new WP_Error(
				'sbk_staff_in_use',
				sprintf(
					/* translators: %d: number of appointments */
					__( 'Δεν γίνεται διαγραφή: υπάρχουν %d ραντεβού. Απενεργοποίησέ τον αντί να τον διαγράψεις.', 'simple-booking' ),
					$used
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::staff_services(), array( 'staff_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::schedules(), array( 'staff_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::exceptions(), array( 'staff_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::staff(), array( 'id' => $id ), array( '%d' ) );

		return true;
	}

	/**
	 * Service assignments for one staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return array<int,array{price_cents:?int,duration_min:?int}> Keyed by service id.
	 */
	public static function get_services_map( $staff_id ) {
		global $wpdb;

		$table = SBK_Tables::staff_services();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE staff_id = %d", (int) $staff_id ) );

		$map = array();

		foreach ( $rows as $row ) {
			$map[ (int) $row->service_id ] = array(
				'price_cents'  => null === $row->price_cents ? null : (int) $row->price_cents,
				'duration_min' => null === $row->duration_min ? null : (int) $row->duration_min,
			);
		}

		return $map;
	}

	/**
	 * Replace the service assignments of a staff member.
	 *
	 * @param int   $staff_id Staff id.
	 * @param array $map      service_id => array( price_cents, duration_min ), null = inherit.
	 * @return void
	 */
	public static function set_services( $staff_id, array $map ) {
		global $wpdb;

		$staff_id = (int) $staff_id;
		$table    = SBK_Tables::staff_services();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'staff_id' => $staff_id ), array( '%d' ) );

		foreach ( $map as $service_id => $overrides ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'staff_id'     => $staff_id,
					'service_id'   => (int) $service_id,
					'price_cents'  => isset( $overrides['price_cents'] ) ? $overrides['price_cents'] : null,
					'duration_min' => isset( $overrides['duration_min'] ) ? $overrides['duration_min'] : null,
				),
				array( '%d', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * How many services each staff member offers.
	 *
	 * @return array<int,int> staff_id => count.
	 */
	public static function count_services_per_staff() {
		global $wpdb;

		$table = SBK_Tables::staff_services();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT staff_id, COUNT(*) AS total FROM {$table} GROUP BY staff_id" );

		$counts = array();

		foreach ( $rows as $row ) {
			$counts[ (int) $row->staff_id ] = (int) $row->total;
		}

		return $counts;
	}
}
