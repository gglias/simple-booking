<?php
/**
 * Data access for services.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}sbk_services.
 */
class SBK_Service_Repository {

	/**
	 * Fetch service rows.
	 *
	 * @param array $args {
	 *     @type bool   $only_active Restrict to active services.
	 *     @type string $search      Match against name or category.
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

		$table = SBK_Tables::services();
		$where = array( '1=1' );
		$vals  = array();

		if ( $args['only_active'] ) {
			$where[] = 'is_active = 1';
		}

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(name LIKE %s OR category LIKE %s)';
			array_push( $vals, $like, $like );
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort_order ASC, name ASC';

		if ( $vals ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
			$sql = $wpdb->prepare( $sql, $vals );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $sql );
	}

	/**
	 * Single service row.
	 *
	 * @param int $id Service id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = SBK_Tables::services();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Insert or update a service.
	 *
	 * @param array $data Raw, unsanitised form data.
	 * @param int   $id   Existing id, 0 to insert.
	 * @return int|WP_Error
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'sbk_service_name', __( 'Το όνομα υπηρεσίας είναι υποχρεωτικό.', 'simple-booking' ) );
		}

		if ( empty( $data['duration_min'] ) || (int) $data['duration_min'] < 1 ) {
			return new WP_Error( 'sbk_service_duration', __( 'Η διάρκεια πρέπει να είναι τουλάχιστον 1 λεπτό.', 'simple-booking' ) );
		}

		$duration = SBK_Helpers::clamp_int( $data['duration_min'], 1, 1440 );

		$id   = (int) $id;
		$slug = isset( $data['slug'] ) && '' !== trim( (string) $data['slug'] ) ? $data['slug'] : $name;
		$slug = self::unique_slug( SBK_Helpers::make_slug( $slug ), $id );

		$row = array(
			'name'              => $name,
			'slug'              => $slug,
			'description'       => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'category'          => ! empty( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
			'duration_min'      => $duration,
			'buffer_before_min' => SBK_Helpers::clamp_int( isset( $data['buffer_before_min'] ) ? $data['buffer_before_min'] : 0, 0, 1440 ),
			'buffer_after_min'  => SBK_Helpers::clamp_int( isset( $data['buffer_after_min'] ) ? $data['buffer_after_min'] : 0, 0, 1440 ),
			'price_cents'       => SBK_Helpers::to_cents( isset( $data['price_euro'] ) ? $data['price_euro'] : 0 ),
			'is_active'         => empty( $data['is_active'] ) ? 0 : 1,
			'sort_order'        => SBK_Helpers::clamp_int( isset( $data['sort_order'] ) ? $data['sort_order'] : 0, 0, 65535 ),
			'updated_at'        => SBK_Helpers::now_utc(),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' );
		$table   = SBK_Tables::services();

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
	 * Make a slug unique against the services table.
	 *
	 * @param string $slug    Base slug.
	 * @param int    $exclude Service id to ignore (itself, on update).
	 * @return string
	 */
	private static function unique_slug( $slug, $exclude = 0 ) {
		global $wpdb;

		$slug  = '' === $slug ? 'service' : $slug;
		$table = SBK_Tables::services();
		$base  = $slug;
		$i     = 2;

		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$found = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id <> %d", $slug, (int) $exclude )
			);

			if ( 0 === $found ) {
				return $slug;
			}

			$slug = $base . '-' . $i;
			++$i;
		}
	}

	/**
	 * Delete a service, unless appointments reference it.
	 *
	 * @param int $id Service id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id           = (int) $id;
		$appointments = SBK_Tables::appointments();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$appointments} WHERE service_id = %d", $id ) );

		if ( $used > 0 ) {
			return new WP_Error(
				'sbk_service_in_use',
				sprintf(
					/* translators: %d: number of appointments */
					__( 'Δεν γίνεται διαγραφή: υπάρχουν %d ραντεβού. Απενεργοποίησε την υπηρεσία αντί να τη διαγράψεις.', 'simple-booking' ),
					$used
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::staff_services(), array( 'service_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::service_extras(), array( 'service_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SBK_Tables::services(), array( 'id' => $id ), array( '%d' ) );

		return true;
	}

	/**
	 * Staff ids assigned to a service.
	 *
	 * @param int $service_id Service id.
	 * @return int[]
	 */
	public static function get_staff_ids( $service_id ) {
		global $wpdb;

		$table = SBK_Tables::staff_services();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT staff_id FROM {$table} WHERE service_id = %d", (int) $service_id ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Replace the staff assigned to a service, preserving existing overrides.
	 *
	 * @param int   $service_id Service id.
	 * @param int[] $staff_ids  Staff ids to keep.
	 * @return void
	 */
	public static function set_staff( $service_id, array $staff_ids ) {
		global $wpdb;

		$service_id = (int) $service_id;
		$table      = SBK_Tables::staff_services();
		$staff_ids  = array_unique( array_map( 'intval', $staff_ids ) );
		$existing   = self::get_staff_ids( $service_id );

		foreach ( array_diff( $existing, $staff_ids ) as $remove ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				$table,
				array(
					'service_id' => $service_id,
					'staff_id'   => $remove,
				),
				array( '%d', '%d' )
			);
		}

		foreach ( array_diff( $staff_ids, $existing ) as $add ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'service_id'   => $service_id,
					'staff_id'     => $add,
					'price_cents'  => null,
					'duration_min' => null,
				),
				array( '%d', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * How many staff offer each service.
	 *
	 * @return array<int,int> service_id => count.
	 */
	public static function count_staff_per_service() {
		global $wpdb;

		$table = SBK_Tables::staff_services();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT service_id, COUNT(*) AS total FROM {$table} GROUP BY service_id" );

		$counts = array();

		foreach ( $rows as $row ) {
			$counts[ (int) $row->service_id ] = (int) $row->total;
		}

		return $counts;
	}
}
