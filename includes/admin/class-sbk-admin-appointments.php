<?php
/**
 * Appointments admin: the day the salon is actually working through.
 *
 * Four views, all under one page slug:
 * - day    the schedule for one date, one column per staff member
 * - list   filtered history and upcoming
 * - single one appointment, with status and internal note
 * - new    a phone booking, taken by the receptionist
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles Κρατήσεις → Ραντεβού.
 */
class SBK_Admin_Appointments {

	const PAGE         = 'sbk-appointments';
	const NONCE_UPDATE = 'sbk_appointment_update';
	const NONCE_CREATE = 'sbk_appointment_create';

	/**
	 * Route between views.
	 *
	 * @return void
	 */
	public static function render() {
		SBK_Admin::require_cap();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'day';

		switch ( $view ) {
			case 'list':
				self::render_list();
				break;

			case 'single':
				self::render_single();
				break;

			case 'new':
				self::render_new();
				break;

			default:
				self::render_day();
		}
	}

	/**
	 * Today in salon local time.
	 *
	 * @return string
	 */
	private static function today() {
		return ( new DateTime( 'now', SBK_Helpers::salon_timezone() ) )->format( 'Y-m-d' );
	}

	/**
	 * URL for one of our views.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Shift a date by days.
	 *
	 * @param string $date  Y-m-d.
	 * @param int    $delta Days.
	 * @return string
	 */
	private static function shift_date( $date, $delta ) {
		$dt = new DateTime( $date, SBK_Helpers::salon_timezone() );
		$dt->modify( sprintf( '%+d days', $delta ) );

		return $dt->format( 'Y-m-d' );
	}

	/**
	 * The tab bar shared by the day and list views.
	 *
	 * @param string $current Active view.
	 * @return void
	 */
	private static function tabs( $current ) {
		$tabs = array(
			'day'  => __( 'Ημέρα', 'simple-booking' ),
			'list' => __( 'Όλα τα ραντεβού', 'simple-booking' ),
		);
		?>
		<nav class="nav-tab-wrapper sbk-tabs">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( self::url( array( 'view' => $key ) ) ); ?>"
					class="nav-tab <?php echo $current === $key ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Status pill.
	 *
	 * @param string $status Status code.
	 * @return void
	 */
	private static function status_badge( $status ) {
		$statuses = SBK_Appointment_Repository::statuses();
		$label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;

		printf(
			'<span class="sbk-badge sbk-status sbk-status--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	// -------------------------------------------------------------------------
	// Day view
	// -------------------------------------------------------------------------

	/**
	 * One day, one column per staff member.
	 *
	 * @return void
	 */
	private static function render_day() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : self::today();

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = self::today();
		}

		$staff   = SBK_Staff_Repository::get_all( array( 'only_active' => true ) );
		$by_staff = SBK_Appointment_Repository::get_for_day( $date );
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ραντεβού', 'simple-booking' ); ?></h1>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'new', 'date' => $date ) ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Νέο ραντεβού', 'simple-booking' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>
			<?php self::tabs( 'day' ); ?>

			<div class="sbk-daynav">
				<a class="button" href="<?php echo esc_url( self::url( array( 'date' => self::shift_date( $date, -1 ) ) ) ); ?>">‹ <?php esc_html_e( 'Χθες', 'simple-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::url( array( 'date' => self::today() ) ) ); ?>"><?php esc_html_e( 'Σήμερα', 'simple-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::url( array( 'date' => self::shift_date( $date, 1 ) ) ) ); ?>"><?php esc_html_e( 'Αύριο', 'simple-booking' ); ?> ›</a>

				<form method="get" class="sbk-daynav__form">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
					<input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()">
					<noscript><?php submit_button( __( 'Μετάβαση', 'simple-booking' ), 'secondary', '', false ); ?></noscript>
				</form>

				<strong class="sbk-daynav__label"><?php echo esc_html( SBK_Helpers::format_date( $date ) ); ?></strong>
			</div>

			<?php if ( ! $staff ) : ?>
				<p><?php esc_html_e( 'Δεν υπάρχει ενεργό προσωπικό.', 'simple-booking' ); ?></p>
			<?php else : ?>
				<div class="sbk-day-board">
					<?php foreach ( $staff as $member ) : ?>
						<?php
						$rows    = isset( $by_staff[ (int) $member->id ] ) ? $by_staff[ (int) $member->id ] : array();
						$windows = SBK_Availability::get_working_windows( (int) $member->id, $date );
						$booked  = 0;

						foreach ( $rows as $row ) {
							if ( in_array( $row->status, SBK_Appointment_Repository::BLOCKING_STATUSES, true ) ) {
								$booked += (int) $row->duration_min;
							}
						}
						?>
						<section class="sbk-col">
							<header class="sbk-col__head" style="border-top-color:<?php echo esc_attr( $member->color ? $member->color : '#c3c4c7' ); ?>">
								<strong><?php echo esc_html( $member->display_name ); ?></strong>
								<span class="sbk-col__hours">
									<?php if ( $windows ) : ?>
										<?php
										$parts = array();

										foreach ( $windows as $window ) {
											$parts[] = SBK_Helpers::time_to_input( $window['start'] ) . '–' . SBK_Helpers::time_to_input( $window['end'] );
										}

										echo esc_html( implode( ', ', $parts ) );
										?>
									<?php else : ?>
										<em><?php esc_html_e( 'Ρεπό', 'simple-booking' ); ?></em>
									<?php endif; ?>
								</span>
								<span class="sbk-col__load">
									<?php
									printf(
										/* translators: 1: number of appointments, 2: booked duration */
										esc_html__( '%1$d ραντεβού · %2$s', 'simple-booking' ),
										count( $rows ),
										esc_html( SBK_Helpers::format_duration( $booked ) )
									);
									?>
								</span>
							</header>

							<?php if ( ! $rows ) : ?>
								<p class="sbk-col__empty"><?php esc_html_e( 'Καμία κράτηση.', 'simple-booking' ); ?></p>
							<?php endif; ?>

							<?php foreach ( $rows as $row ) : ?>
								<?php
								$start = SBK_Helpers::utc_to_local( $row->start_utc );
								$end   = SBK_Helpers::utc_to_local( $row->end_utc );
								$name  = trim( $row->first_name . ' ' . (string) $row->last_name );
								?>
								<a class="sbk-appt sbk-appt--<?php echo esc_attr( $row->status ); ?>"
									href="<?php echo esc_url( self::url( array( 'view' => 'single', 'id' => (int) $row->id ) ) ); ?>">
									<span class="sbk-appt__time">
										<?php echo esc_html( $start->format( 'H:i' ) . ' – ' . $end->format( 'H:i' ) ); ?>
									</span>
									<span class="sbk-appt__who"><?php echo esc_html( $name ); ?></span>
									<span class="sbk-appt__what"><?php echo esc_html( $row->service_name ); ?></span>
									<span class="sbk-appt__meta">
										<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', (string) $row->phone ) ); ?>"><?php echo esc_html( $row->phone ); ?></a>
										· <?php echo esc_html( SBK_Helpers::format_money( (int) $row->total_price_cents ) ); ?>
									</span>
									<?php if ( 'confirmed' !== $row->status ) : ?>
										<span class="sbk-appt__status"><?php self::status_badge( $row->status ); ?></span>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</section>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// List view
	// -------------------------------------------------------------------------

	/**
	 * Filterable list of appointments.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$from     = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : self::today();
		$to       = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$staff_id = isset( $_GET['staff_id'] ) ? (int) $_GET['staff_id'] : 0;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = 30;
		$result   = SBK_Appointment_Repository::query(
			array(
				'from'     => $from,
				'to'       => $to,
				'staff_id' => $staff_id,
				'status'   => $status,
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => $page,
				'order'    => 'ASC',
			)
		);

		$staff    = SBK_Staff_Repository::get_all();
		$statuses = SBK_Appointment_Repository::statuses();
		$pages    = (int) ceil( $result['total'] / $per_page );
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ραντεβού', 'simple-booking' ); ?></h1>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'new' ) ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Νέο ραντεβού', 'simple-booking' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>
			<?php self::tabs( 'list' ); ?>

			<form method="get" class="sbk-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<input type="hidden" name="view" value="list">

				<label><?php esc_html_e( 'Από', 'simple-booking' ); ?>
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
				</label>
				<label><?php esc_html_e( 'Έως', 'simple-booking' ); ?>
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				</label>
				<label><?php esc_html_e( 'Άτομο', 'simple-booking' ); ?>
					<select name="staff_id">
						<option value="0"><?php esc_html_e( 'Όλοι', 'simple-booking' ); ?></option>
						<?php foreach ( $staff as $member ) : ?>
							<option value="<?php echo esc_attr( (int) $member->id ); ?>" <?php selected( $staff_id, (int) $member->id ); ?>>
								<?php echo esc_html( $member->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?>
					<select name="status">
						<option value=""><?php esc_html_e( 'Όλες', 'simple-booking' ); ?></option>
						<?php foreach ( $statuses as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $status, $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Αναζήτηση', 'simple-booking' ); ?>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'όνομα, email, τηλέφωνο', 'simple-booking' ); ?>">
				</label>

				<?php submit_button( __( 'Φιλτράρισμα', 'simple-booking' ), 'secondary', '', false ); ?>
				<a class="button-link" href="<?php echo esc_url( self::url( array( 'view' => 'list', 'from' => '' ) ) ); ?>"><?php esc_html_e( 'Καθαρισμός', 'simple-booking' ); ?></a>
			</form>

			<p class="sbk-count">
				<?php
				printf(
					/* translators: %d: number of appointments */
					esc_html( _n( '%d ραντεβού', '%d ραντεβού', $result['total'], 'simple-booking' ) ),
					(int) $result['total']
				);
				?>
			</p>

			<table class="wp-list-table widefat fixed striped sbk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Πότε', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Πελάτης', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Άτομο', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $result['items'] ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Κανένα ραντεβού με αυτά τα φίλτρα.', 'simple-booking' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $result['items'] as $row ) : ?>
					<?php
					$start = SBK_Helpers::utc_to_local( $row->start_utc );
					$url   = self::url( array( 'view' => 'single', 'id' => (int) $row->id ) );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $start->format( 'd/m/Y' ) ); ?></a></strong>
							<?php echo esc_html( ' · ' . $start->format( 'H:i' ) ); ?>
						</td>
						<td>
							<?php echo esc_html( trim( $row->first_name . ' ' . (string) $row->last_name ) ); ?><br>
							<span class="sbk-muted"><?php echo esc_html( (string) $row->phone ); ?></span>
						</td>
						<td><?php echo esc_html( $row->service_name ); ?></td>
						<td><?php echo esc_html( $row->staff_name ); ?></td>
						<td><?php echo esc_html( SBK_Helpers::format_money( (int) $row->total_price_cents ) ); ?></td>
						<td><?php self::status_badge( $row->status ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $pages,
								'prev_text' => '‹',
								'next_text' => '›',
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Single view
	// -------------------------------------------------------------------------

	/**
	 * One appointment, with status and internal note.
	 *
	 * @return void
	 */
	private static function render_single() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$row = SBK_Appointment_Repository::find_with_customer( $id );

		if ( ! $row ) {
			wp_die( esc_html__( 'Το ραντεβού δεν βρέθηκε.', 'simple-booking' ), 404 );
		}

		$start    = SBK_Helpers::utc_to_local( $row->start_utc );
		$end      = SBK_Helpers::utc_to_local( $row->end_utc );
		$extras   = SBK_Appointment_Repository::get_extras( $id );
		$statuses = SBK_Appointment_Repository::statuses();
		$back     = self::url( array( 'date' => $start->format( 'Y-m-d' ) ) );
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %d: appointment id */
					esc_html__( 'Ραντεβού #%d', 'simple-booking' ),
					(int) $row->id
				);
				?>
			</h1>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>

			<p><a href="<?php echo esc_url( $back ); ?>">‹ <?php esc_html_e( 'Πίσω στην ημέρα', 'simple-booking' ); ?></a></p>

			<div class="sbk-single">
				<div class="sbk-single__main">
					<table class="widefat sbk-table">
						<tbody>
							<tr>
								<th><?php esc_html_e( 'Πότε', 'simple-booking' ); ?></th>
								<td>
									<strong><?php echo esc_html( SBK_Helpers::format_date( $start->format( 'Y-m-d' ) ) ); ?></strong>
									<?php echo esc_html( ' · ' . $start->format( 'H:i' ) . ' – ' . $end->format( 'H:i' ) ); ?>
									<span class="sbk-muted">(<?php echo esc_html( SBK_Helpers::format_duration( (int) $row->duration_min ) ); ?>)</span>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Δέσμευση', 'simple-booking' ); ?></th>
								<td class="sbk-muted">
									<?php
									$block_start = SBK_Helpers::utc_to_local( $row->block_start_utc );
									$block_end   = SBK_Helpers::utc_to_local( $row->block_end_utc );

									echo esc_html( $block_start->format( 'H:i' ) . ' – ' . $block_end->format( 'H:i' ) );
									printf(
										/* translators: 1: buffer before, 2: buffer after */
										esc_html__( ' (buffers: %1$d′ πριν, %2$d′ μετά)', 'simple-booking' ),
										(int) $row->buffer_before_min,
										(int) $row->buffer_after_min
									);
									?>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Άτομο', 'simple-booking' ); ?></th>
								<td><?php echo esc_html( $row->staff_name ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></th>
								<td>
									<?php echo esc_html( $row->service_name ); ?>
									<span class="sbk-muted"><?php echo esc_html( ' · ' . SBK_Helpers::format_money( (int) $row->service_price_cents ) ); ?></span>
								</td>
							</tr>
							<?php foreach ( $extras as $extra ) : ?>
								<tr>
									<th><?php esc_html_e( 'Extra', 'simple-booking' ); ?></th>
									<td>
										<?php echo esc_html( $extra->extra_name ); ?>
										<span class="sbk-muted"><?php echo esc_html( ' · ' . SBK_Helpers::format_money( (int) $extra->price_cents ) ); ?></span>
									</td>
								</tr>
							<?php endforeach; ?>
							<tr>
								<th><?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?></th>
								<td>
									<strong><?php echo esc_html( SBK_Helpers::format_money( (int) $row->total_price_cents ) ); ?></strong>
									<span class="sbk-muted"><?php esc_html_e( '— πληρωμή στο κατάστημα', 'simple-booking' ); ?></span>
								</td>
							</tr>
							<?php if ( $row->customer_note ) : ?>
								<tr>
									<th><?php esc_html_e( 'Σημείωση πελάτη', 'simple-booking' ); ?></th>
									<td><?php echo esc_html( $row->customer_note ); ?></td>
								</tr>
							<?php endif; ?>
							<tr>
								<th><?php esc_html_e( 'Καταχωρήθηκε', 'simple-booking' ); ?></th>
								<td class="sbk-muted">
									<?php
									$created = SBK_Helpers::utc_to_local( $row->created_at );

									echo esc_html( $created->format( 'd/m/Y H:i' ) );
									echo esc_html( 'admin' === $row->created_via ? __( ' — από το admin', 'simple-booking' ) : __( ' — από τη φόρμα', 'simple-booking' ) );
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="sbk-single__side">
					<div class="sbk-box">
						<h2><?php esc_html_e( 'Πελάτης', 'simple-booking' ); ?></h2>
						<p>
							<strong><?php echo esc_html( trim( $row->first_name . ' ' . (string) $row->last_name ) ); ?></strong><br>
							<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', (string) $row->phone ) ); ?>"><?php echo esc_html( (string) $row->phone ); ?></a><br>
							<a href="mailto:<?php echo esc_attr( (string) $row->email ); ?>"><?php echo esc_html( (string) $row->email ); ?></a>
						</p>
						<p class="sbk-muted">
							<?php echo $row->customer_user_id ? esc_html__( 'Έχει λογαριασμό', 'simple-booking' ) : esc_html__( 'Guest κράτηση', 'simple-booking' ); ?>
						</p>
					</div>

					<form class="sbk-box" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sbk_update_appointment">
						<input type="hidden" name="id" value="<?php echo esc_attr( (int) $row->id ); ?>">
						<?php wp_nonce_field( self::NONCE_UPDATE ); ?>

						<h2><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></h2>
						<select name="status">
							<?php foreach ( $statuses as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $row->status, $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Η ακύρωση ελευθερώνει αμέσως την ώρα.', 'simple-booking' ); ?></p>

						<h2><?php esc_html_e( 'Εσωτερική σημείωση', 'simple-booking' ); ?></h2>
						<textarea name="admin_note" rows="4" class="large-text"><?php echo esc_textarea( (string) $row->admin_note ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Δεν τη βλέπει ο πελάτης.', 'simple-booking' ); ?></p>

						<?php submit_button( __( 'Αποθήκευση', 'simple-booking' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// New (phone booking)
	// -------------------------------------------------------------------------

	/**
	 * Take a booking over the phone.
	 *
	 * Staff, service and extras reload the page (they change what is on offer);
	 * the date and time use the same calendar the customer sees, driven by the
	 * same REST endpoints, so the two can never disagree about availability.
	 *
	 * @return void
	 */
	private static function render_new() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only form state.
		$staff_id   = isset( $_GET['staff_id'] ) ? (int) $_GET['staff_id'] : 0;
		$service_id = isset( $_GET['service_id'] ) ? (int) $_GET['service_id'] : 0;
		$date       = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : self::today();
		$extra_ids  = isset( $_GET['extras'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['extras'] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = self::today();
		}

		$staff    = SBK_Staff_Repository::get_all( array( 'only_active' => true ) );
		$services = $staff_id ? self::services_for( $staff_id ) : array();
		$extras   = $service_id ? SBK_Extra_Repository::get_for_service( $service_id ) : array();
		?>
		<div class="wrap sbk-wrap">
			<h1><?php esc_html_e( 'Νέο ραντεβού', 'simple-booking' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Για κρατήσεις που κλείνονται τηλεφωνικά ή στο κατάστημα.', 'simple-booking' ); ?></p>

			<?php SBK_Admin::print_notice(); ?>

			<form method="get" class="sbk-box">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<input type="hidden" name="view" value="new">
				<input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-new-staff"><?php esc_html_e( 'Άτομο', 'simple-booking' ); ?></label></th>
						<td>
							<select name="staff_id" id="sbk-new-staff" onchange="this.form.submit()">
								<option value="0"><?php esc_html_e( '— Επίλεξε —', 'simple-booking' ); ?></option>
								<?php foreach ( $staff as $member ) : ?>
									<option value="<?php echo esc_attr( (int) $member->id ); ?>" <?php selected( $staff_id, (int) $member->id ); ?>>
										<?php echo esc_html( $member->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<?php if ( $services ) : ?>
						<tr>
							<th scope="row"><label for="sbk-new-service"><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></label></th>
							<td>
								<select name="service_id" id="sbk-new-service" onchange="this.form.submit()">
									<option value="0"><?php esc_html_e( '— Επίλεξε —', 'simple-booking' ); ?></option>
									<?php foreach ( $services as $service ) : ?>
										<option value="<?php echo esc_attr( (int) $service['id'] ); ?>" <?php selected( $service_id, (int) $service['id'] ); ?>>
											<?php echo esc_html( $service['name'] . ' · ' . SBK_Helpers::format_duration( $service['duration_min'] ) . ' · ' . SBK_Helpers::format_money( $service['price_cents'] ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endif; ?>

					<?php if ( $extras ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Extras', 'simple-booking' ); ?></th>
							<td>
								<?php foreach ( $extras as $extra ) : ?>
									<label class="sbk-inline-check">
										<input type="checkbox" name="extras[]" value="<?php echo esc_attr( (int) $extra->id ); ?>"
											<?php checked( in_array( (int) $extra->id, $extra_ids, true ) ); ?> onchange="this.form.submit()">
										<?php echo esc_html( $extra->name . ' (+' . SBK_Helpers::format_duration( (int) $extra->duration_min ) . ', +' . SBK_Helpers::format_money( (int) $extra->price_cents ) . ')' ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<noscript><?php submit_button( __( 'Ενημέρωση', 'simple-booking' ), 'secondary' ); ?></noscript>
			</form>

			<?php if ( $staff_id && $service_id ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbk-box">
					<input type="hidden" name="action" value="sbk_create_appointment">
					<input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff_id ); ?>">
					<input type="hidden" name="service_id" value="<?php echo esc_attr( $service_id ); ?>">
					<?php foreach ( $extra_ids as $extra_id ) : ?>
						<input type="hidden" name="extras[]" value="<?php echo esc_attr( $extra_id ); ?>">
					<?php endforeach; ?>
					<?php wp_nonce_field( self::NONCE_CREATE ); ?>

					<div class="sbk sbk--admin" x-data="sbkAdminCalendar()" x-cloak>
						<h2><?php esc_html_e( 'Ημερομηνία και ώρα', 'simple-booking' ); ?></h2>

						<div class="sbk-error" x-show="error" x-transition role="alert"><span x-text="error"></span></div>

						<div class="sbk-admin-picker">
							<div class="sbk-calendar">
								<div class="sbk-calendar__head">
									<button type="button" class="sbk-nav" @click="shift(-1)" :disabled="!canGoPrev()"
										aria-label="<?php esc_attr_e( 'Προηγούμενος μήνας', 'simple-booking' ); ?>">‹</button>
									<span class="sbk-calendar__title" x-text="monthTitle()" aria-live="polite"></span>
									<button type="button" class="sbk-nav" @click="shift(1)" :disabled="!canGoNext()"
										aria-label="<?php esc_attr_e( 'Επόμενος μήνας', 'simple-booking' ); ?>">›</button>
								</div>

								<div class="sbk-calendar__grid sbk-calendar__grid--head" aria-hidden="true">
									<template x-for="wd in weekdaysShort" :key="wd">
										<span class="sbk-calendar__wd" x-text="wd"></span>
									</template>
								</div>

								<div class="sbk-calendar__grid">
									<template x-for="cell in cells()" :key="cell.key">
										<template x-if="cell.date">
											<button type="button" class="sbk-day"
												:class="{ 'is-available': cell.available, 'is-selected': date === cell.date }"
												:disabled="!cell.available"
												:aria-label="cell.date"
												@click="pickDate(cell.date)">
												<span x-text="cell.day"></span>
											</button>
										</template>
										<template x-if="!cell.date">
											<span class="sbk-day sbk-day--empty"></span>
										</template>
									</template>
								</div>
							</div>

							<div class="sbk-slots">
								<h3 class="sbk-h3" x-show="date" x-text="dateLabel()"></h3>

								<p class="sbk-empty" x-show="!date">
									<?php esc_html_e( 'Διάλεξε ημέρα για να δεις τις ελεύθερες ώρες.', 'simple-booking' ); ?>
								</p>
								<p class="sbk-empty" x-show="date && !loading && slots.length === 0">
									<?php esc_html_e( 'Καμία ελεύθερη ώρα αυτή την ημέρα.', 'simple-booking' ); ?>
								</p>

								<div class="sbk-slots__grid">
									<template x-for="slot in slots" :key="slot.time">
										<button type="button" class="sbk-slot"
											:class="{ 'is-selected': time === slot.time }"
											:aria-pressed="time === slot.time"
											@click="pickTime(slot)" x-text="slot.time"></button>
									</template>
								</div>
							</div>
						</div>

						<input type="hidden" name="date" :value="date">
						<input type="hidden" name="time" :value="time">

						<p class="sbk-admin-chosen">
							<strong x-text="summary()"></strong>
						</p>
					</div>

					<noscript>
						<p class="sbk-inline-error"><?php esc_html_e( 'Χρειάζεται JavaScript για την επιλογή ώρας.', 'simple-booking' ); ?></p>
					</noscript>

					<h2><?php esc_html_e( 'Στοιχεία πελάτη', 'simple-booking' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="sbk-new-first"><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
							<td><input type="text" name="first_name" id="sbk-new-first" class="regular-text" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbk-new-last"><?php esc_html_e( 'Επώνυμο', 'simple-booking' ); ?></label></th>
							<td><input type="text" name="last_name" id="sbk-new-last" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbk-new-phone"><?php esc_html_e( 'Τηλέφωνο', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
							<td><input type="tel" name="phone" id="sbk-new-phone" class="regular-text" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="sbk-new-email"><?php esc_html_e( 'Email', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
							<td>
								<input type="email" name="email" id="sbk-new-email" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Χρειάζεται για το email επιβεβαίωσης και για να βρεθεί ο πελάτης την επόμενη φορά.', 'simple-booking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sbk-new-note"><?php esc_html_e( 'Σημείωση', 'simple-booking' ); ?></label></th>
							<td><textarea name="note" id="sbk-new-note" rows="3" class="large-text"></textarea></td>
						</tr>
					</table>

					<?php submit_button( __( 'Καταχώρηση ραντεβού', 'simple-booking' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
	/**
	 * Services one staff member performs, with their own price and duration.
	 *
	 * @param int $staff_id Staff id.
	 * @return array<int,array>
	 */
	private static function services_for( $staff_id ) {
		$map = SBK_Staff_Repository::get_services_map( $staff_id );
		$out = array();

		foreach ( SBK_Service_Repository::get_all( array( 'only_active' => true ) ) as $service ) {
			$id = (int) $service->id;

			if ( ! isset( $map[ $id ] ) ) {
				continue;
			}

			$out[] = array(
				'id'           => $id,
				'name'         => $service->name,
				'duration_min' => null === $map[ $id ]['duration_min'] ? (int) $service->duration_min : (int) $map[ $id ]['duration_min'],
				'price_cents'  => null === $map[ $id ]['price_cents'] ? (int) $service->price_cents : (int) $map[ $id ]['price_cents'],
			);
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Save status and internal note.
	 *
	 * @return void
	 */
	public static function handle_update() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE_UPDATE );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$note   = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';

		$result = SBK_Appointment_Repository::update_status( $id, $status );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
		} else {
			SBK_Appointment_Repository::update_admin_note( $id, $note );
			SBK_Admin::add_notice( __( 'Το ραντεβού ενημερώθηκε.', 'simple-booking' ) );
		}

		SBK_Admin::redirect(
			self::PAGE,
			array(
				'view' => 'single',
				'id'   => $id,
			)
		);
	}

	/**
	 * Create a booking taken by phone.
	 *
	 * Goes through the same service as the frontend, so the conflict check,
	 * the price snapshot and the emails all behave identically.
	 *
	 * @return void
	 */
	public static function handle_create() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE_CREATE );

		$post = wp_unslash( $_POST ); // Sanitised downstream.

		$result = SBK_Booking_Service::create(
			array(
				'staff_id'    => isset( $post['staff_id'] ) ? (int) $post['staff_id'] : 0,
				'service_id'  => isset( $post['service_id'] ) ? (int) $post['service_id'] : 0,
				'date'        => isset( $post['date'] ) ? $post['date'] : '',
				'time'        => isset( $post['time'] ) ? $post['time'] : '',
				'extras'      => isset( $post['extras'] ) ? array_map( 'absint', (array) $post['extras'] ) : array(),
				'customer'    => array(
					'first_name' => isset( $post['first_name'] ) ? $post['first_name'] : '',
					'last_name'  => isset( $post['last_name'] ) ? $post['last_name'] : '',
					'email'      => isset( $post['email'] ) ? $post['email'] : '',
					'phone'      => isset( $post['phone'] ) ? $post['phone'] : '',
				),
				'note'        => isset( $post['note'] ) ? $post['note'] : '',
				'created_via' => 'admin',
			)
		);

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
			SBK_Admin::redirect(
				self::PAGE,
				array(
					'view'       => 'new',
					'staff_id'   => isset( $post['staff_id'] ) ? (int) $post['staff_id'] : 0,
					'service_id' => isset( $post['service_id'] ) ? (int) $post['service_id'] : 0,
					'date'       => isset( $post['date'] ) ? $post['date'] : '',
				)
			);
		}

		SBK_Admin::add_notice( __( 'Το ραντεβού καταχωρήθηκε.', 'simple-booking' ) );
		SBK_Admin::redirect(
			self::PAGE,
			array(
				'view' => 'single',
				'id'   => (int) $result['appointment']->id,
			)
		);
	}
}
