<?php
/**
 * Data access for extras and their link to services.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads {prefix}sbk_extras and {prefix}sbk_service_extras.
 */
class SBK_Extra_Repository {

	/**
	 * Single extra row.
	 *
	 * @param int $id Extra id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = SBK_Tables::extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * All extras.
	 *
	 * @param bool $only_active Restrict to active extras.
	 * @return array<int,object>
	 */
	public static function get_all( $only_active = false ) {
		global $wpdb;

		$table = SBK_Tables::extras();
		$sql   = "SELECT * FROM {$table}";

		if ( $only_active ) {
			$sql .= ' WHERE is_active = 1';
		}

		$sql .= ' ORDER BY sort_order ASC, name ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $sql );
	}

	/**
	 * Extras offered for a service.
	 *
	 * @param int  $service_id  Service id.
	 * @param bool $only_active Restrict to active extras.
	 * @return array<int,object>
	 */
	public static function get_for_service( $service_id, $only_active = true ) {
		global $wpdb;

		$extras = SBK_Tables::extras();
		$link   = SBK_Tables::service_extras();
		$where  = $only_active ? 'AND e.is_active = 1' : '';

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed fragment.
			"SELECT e.* FROM {$extras} e
			 INNER JOIN {$link} se ON se.extra_id = e.id
			 WHERE se.service_id = %d {$where}
			 ORDER BY se.sort_order ASC, e.sort_order ASC, e.name ASC",
			(int) $service_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $sql );
	}

	/**
	 * Specific extras, but only those actually offered for a service.
	 *
	 * Used to validate a submitted selection: anything not returned here was
	 * either inactive or not linked to the service.
	 *
	 * @param int   $service_id Service id.
	 * @param int[] $ids        Extra ids.
	 * @return array<int,object>
	 */
	public static function find_for_service( $service_id, array $ids ) {
		global $wpdb;

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		if ( ! $ids ) {
			return array();
		}

		$extras       = SBK_Tables::extras();
		$link         = SBK_Tables::service_extras();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
			"SELECT e.* FROM {$extras} e
			 INNER JOIN {$link} se ON se.extra_id = e.id
			 WHERE se.service_id = %d AND e.is_active = 1 AND e.id IN ({$placeholders})",
			array_merge( array( (int) $service_id ), $ids )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $sql );
	}

	/**
	 * Insert or update an extra.
	 *
	 * @param array $data Raw, unsanitised form data.
	 * @param int   $id   Existing id, 0 to insert.
	 * @return int|WP_Error
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		if ( '' === trim( $name ) ) {
			return new WP_Error( 'sbk_extra_name', __( 'Το όνομα του extra είναι υποχρεωτικό.', 'simple-booking' ) );
		}

		$row = array(
			'name'         => $name,
			'description'  => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'duration_min' => SBK_Helpers::clamp_int( isset( $data['duration_min'] ) ? $data['duration_min'] : 0, 0, 1440 ),
			'price_cents'  => SBK_Helpers::to_cents( isset( $data['price_euro'] ) ? $data['price_euro'] : 0 ),
			'is_active'    => empty( $data['is_active'] ) ? 0 : 1,
			'sort_order'   => SBK_Helpers::clamp_int( isset( $data['sort_order'] ) ? $data['sort_order'] : 0, 0, 65535 ),
			'updated_at'   => SBK_Helpers::now_utc(),
		);

		$formats = array( '%s', '%s', '%d', '%d', '%d', '%d', '%s' );
		$table   = SBK_Tables::extras();
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
	 * Delete an extra, unless appointments reference it.
	 *
	 * Appointments snapshot the extra's name and price, so history would survive
	 * a deletion; it is blocked anyway so the rule is the same everywhere in the
	 * admin: things that were used get deactivated, not deleted.
	 *
	 * @param int $id Extra id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id    = (int) $id;
		$lines = SBK_Tables::appointment_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$lines} WHERE extra_id = %d", $id ) );

		if ( $used > 0 ) {
			return new WP_Error(
				'sbk_extra_in_use',
				sprintf(
					/* translators: %d: number of appointments */
					__( 'Δεν γίνεται διαγραφή: χρησιμοποιείται σε %d ραντεβού. Απενεργοποίησέ το αντί να το διαγράψεις.', 'simple-booking' ),
					$used
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::service_extras(), array( 'extra_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::extras(), array( 'id' => $id ), array( '%d' ) );

		return true;
	}

	/**
	 * Service ids this extra is offered for.
	 *
	 * @param int $extra_id Extra id.
	 * @return int[]
	 */
	public static function get_service_ids( $extra_id ) {
		global $wpdb;

		$table = SBK_Tables::service_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT service_id FROM {$table} WHERE extra_id = %d", (int) $extra_id ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Extra ids offered for a service.
	 *
	 * @param int $service_id Service id.
	 * @return int[]
	 */
	public static function get_extra_ids( $service_id ) {
		global $wpdb;

		$table = SBK_Tables::service_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT extra_id FROM {$table} WHERE service_id = %d", (int) $service_id ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Replace the services an extra is offered for.
	 *
	 * @param int   $extra_id    Extra id.
	 * @param int[] $service_ids Service ids.
	 * @return void
	 */
	public static function set_services( $extra_id, array $service_ids ) {
		global $wpdb;

		$extra_id = (int) $extra_id;
		$table    = SBK_Tables::service_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'extra_id' => $extra_id ), array( '%d' ) );

		foreach ( array_unique( array_map( 'intval', $service_ids ) ) as $service_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'service_id' => $service_id,
					'extra_id'   => $extra_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Replace the extras offered for a service.
	 *
	 * @param int   $service_id Service id.
	 * @param int[] $extra_ids  Extra ids.
	 * @return void
	 */
	public static function set_extras( $service_id, array $extra_ids ) {
		global $wpdb;

		$service_id = (int) $service_id;
		$table      = SBK_Tables::service_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'service_id' => $service_id ), array( '%d' ) );

		foreach ( array_unique( array_map( 'intval', $extra_ids ) ) as $extra_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'service_id' => $service_id,
					'extra_id'   => $extra_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * How many services each extra is offered for.
	 *
	 * @return array<int,int> extra_id => count.
	 */
	public static function count_services_per_extra() {
		global $wpdb;

		$table = SBK_Tables::service_extras();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT extra_id, COUNT(*) AS total FROM {$table} GROUP BY extra_id" );

		$counts = array();

		foreach ( $rows as $row ) {
			$counts[ (int) $row->extra_id ] = (int) $row->total;
		}

		return $counts;
	}
}
