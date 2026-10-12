<?php
/**
 * Activation: schema creation and upgrades.
 *
 * Conventions used across every table:
 * - All datetime columns are UTC. Wall-clock columns are `time`/`date` and are
 *   read in SBK_TIMEZONE (Europe/Athens).
 * - All money columns are integer euro cents (`price_cents`), never floats.
 * - All duration columns are whole minutes.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin tables.
 */
class SBK_Activator {

	/**
	 * Fired by register_activation_hook().
	 *
	 * @return void
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Create or upgrade the schema, then record the DB version.
	 *
	 * Safe to call repeatedly: dbDelta only applies the difference.
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = self::charset_collate();

		foreach ( self::get_schema( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}

		self::ensure_appointment_page();

		update_option( 'sbk_db_version', SBK_DB_VERSION, false );
		add_option( 'sbk_installed_at', gmdate( 'Y-m-d H:i:s' ), '', false );
	}

	/**
	 * Charset/collate clause for CREATE TABLE.
	 *
	 * @return string
	 */
	private static function charset_collate() {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * The full schema, one CREATE TABLE statement per table.
	 *
	 * Formatting matters here: dbDelta parses these strings, so keep one column
	 * per line, two spaces after PRIMARY KEY, and KEY (not INDEX).
	 *
	 * @param string $charset_collate Charset clause.
	 * @return string[]
	 */
	private static function get_schema( $charset_collate ) {
		$t = SBK_Tables::all();

		$schema = array();

		/*
		 * Staff: the salon's fixed roster. There is no "any available" option in
		 * the booking flow, so every appointment points at one of these rows.
		 */
		$schema[] = "CREATE TABLE {$t['staff']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			display_name varchar(150) NOT NULL,
			email varchar(100) DEFAULT NULL,
			phone varchar(32) DEFAULT NULL,
			bio text DEFAULT NULL,
			avatar_id bigint(20) unsigned DEFAULT NULL,
			color char(7) DEFAULT NULL,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_active_sort (is_active, sort_order)
		) {$charset_collate};";

		/*
		 * Services: duration is the customer-visible length; the buffers are the
		 * extra minutes blocked around it (cleanup, colour processing, etc.).
		 */
		$schema[] = "CREATE TABLE {$t['services']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			description text DEFAULT NULL,
			category varchar(190) DEFAULT NULL,
			duration_min smallint(5) unsigned NOT NULL DEFAULT 30,
			buffer_before_min smallint(5) unsigned NOT NULL DEFAULT 0,
			buffer_after_min smallint(5) unsigned NOT NULL DEFAULT 0,
			price_cents int(10) unsigned NOT NULL DEFAULT 0,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active_sort (is_active, sort_order)
		) {$charset_collate};";

		/*
		 * Which staff member performs which service, with optional per-staff
		 * overrides. NULL override means "inherit the service value".
		 */
		$schema[] = "CREATE TABLE {$t['staff_services']} (
			staff_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			price_cents int(10) unsigned DEFAULT NULL,
			duration_min smallint(5) unsigned DEFAULT NULL,
			PRIMARY KEY  (staff_id, service_id),
			KEY service_id (service_id)
		) {$charset_collate};";

		/*
		 * Extras: optional add-ons picked at step 3. They add both price and
		 * minutes to the appointment.
		 */
		$schema[] = "CREATE TABLE {$t['extras']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			description text DEFAULT NULL,
			duration_min smallint(5) unsigned NOT NULL DEFAULT 0,
			price_cents int(10) unsigned NOT NULL DEFAULT 0,
			max_quantity smallint(5) unsigned NOT NULL DEFAULT 1,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY is_active_sort (is_active, sort_order)
		) {$charset_collate};";

		/* Which extras are offered for which service. */
		$schema[] = "CREATE TABLE {$t['service_extras']} (
			service_id bigint(20) unsigned NOT NULL,
			extra_id bigint(20) unsigned NOT NULL,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (service_id, extra_id),
			KEY extra_id (extra_id)
		) {$charset_collate};";

		/*
		 * Weekly recurring working hours, in salon local time.
		 * weekday: 0 = Sunday ... 6 = Saturday (matches PHP date('w')).
		 * A day can hold several rows; the gaps between them are the breaks.
		 */
		$schema[] = "CREATE TABLE {$t['schedules']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			weekday tinyint(1) unsigned NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY staff_weekday (staff_id, weekday, is_active)
		) {$charset_collate};";

		/*
		 * Date-specific overrides in salon local time: holidays, sick days,
		 * one-off extended hours. staff_id = 0 means the whole salon.
		 * is_closed = 1 blocks the date (or the given window) entirely;
		 * is_closed = 0 with a window replaces that day's schedule.
		 */
		$schema[] = "CREATE TABLE {$t['exceptions']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL DEFAULT 0,
			exception_date date NOT NULL,
			is_closed tinyint(1) unsigned NOT NULL DEFAULT 1,
			start_time time DEFAULT NULL,
			end_time time DEFAULT NULL,
			note varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY staff_date (staff_id, exception_date)
		) {$charset_collate};";

		/*
		 * Customers. Guest booking is the default: email and phone are required,
		 * user_id is only set when the customer ticked "create an account".
		 */
		$schema[] = "CREATE TABLE {$t['customers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) DEFAULT NULL,
			email varchar(100) NOT NULL,
			phone varchar(32) NOT NULL,
			notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY user_id (user_id),
			KEY phone (phone)
		) {$charset_collate};";

		/*
		 * Appointments. Two time pairs on purpose:
		 * - start_utc/end_utc is what the customer sees and what appears in
		 *   emails and in the "my appointments" list.
		 * - block_start_utc/block_end_utc adds the buffers and is the ONLY pair
		 *   used for overlap detection against other appointments.
		 *
		 * Everything priced is snapshotted here (names, minutes, cents) so a
		 * later price-list change never rewrites past appointments.
		 */
		$schema[] = "CREATE TABLE {$t['appointments']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'confirmed',
			start_utc datetime NOT NULL,
			end_utc datetime NOT NULL,
			block_start_utc datetime NOT NULL,
			block_end_utc datetime NOT NULL,
			staff_name varchar(150) NOT NULL,
			service_name varchar(190) NOT NULL,
			duration_min smallint(5) unsigned NOT NULL,
			buffer_before_min smallint(5) unsigned NOT NULL DEFAULT 0,
			buffer_after_min smallint(5) unsigned NOT NULL DEFAULT 0,
			service_price_cents int(10) unsigned NOT NULL DEFAULT 0,
			extras_price_cents int(10) unsigned NOT NULL DEFAULT 0,
			total_price_cents int(10) unsigned NOT NULL DEFAULT 0,
			customer_note text DEFAULT NULL,
			admin_note text DEFAULT NULL,
			manage_token char(32) NOT NULL,
			created_via varchar(20) NOT NULL DEFAULT 'frontend',
			created_by bigint(20) unsigned DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY manage_token (manage_token),
			KEY staff_block (staff_id, status, block_start_utc, block_end_utc),
			KEY customer_start (customer_id, start_utc),
			KEY service_id (service_id),
			KEY start_utc (start_utc)
		) {$charset_collate};";

		/* Extras attached to an appointment, snapshotted the same way. */
		$schema[] = "CREATE TABLE {$t['appointment_extras']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			appointment_id bigint(20) unsigned NOT NULL,
			extra_id bigint(20) unsigned DEFAULT NULL,
			extra_name varchar(190) NOT NULL,
			quantity smallint(5) unsigned NOT NULL DEFAULT 1,
			duration_min smallint(5) unsigned NOT NULL DEFAULT 0,
			price_cents int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY appointment_id (appointment_id),
			KEY extra_id (extra_id)
		) {$charset_collate};";

		return $schema;
	}

	/**
	 * Make sure the page the confirmation email links to exists.
	 *
	 * Customers land here from their email, so the page has to be there without
	 * anyone configuring anything. If the stored page is deleted, a new one is
	 * created on the next load.
	 *
	 * @return void
	 */
	private static function ensure_appointment_page() {
		$stored = (int) SBK_Settings::get( 'appointment_page_id' );

		if ( $stored && 'publish' === get_post_status( $stored ) ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[' . SBK_Appointment_Page::SHORTCODE . ']',
				'fields'         => 'ids',
			)
		);

		if ( $existing ) {
			SBK_Settings::update( array( 'appointment_page_id' => (int) $existing[0] ) );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Το ραντεβού μου', 'simple-booking' ),
				'post_name'    => 'to-rantevou-mou',
				'post_content' => '[' . SBK_Appointment_Page::SHORTCODE . ']',
				'post_excerpt' => '',
			)
		);

		if ( ! is_wp_error( $page_id ) ) {
			SBK_Settings::update( array( 'appointment_page_id' => (int) $page_id ) );
		}
	}
}
