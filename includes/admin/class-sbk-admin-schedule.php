<?php
/**
 * Weekly schedule screen: one staff member's working hours.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles Κρατήσεις → Ωράριο.
 */
class SBK_Admin_Schedule {

	const PAGE  = 'sbk-schedule';
	const NONCE = 'sbk_schedule_form';

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		SBK_Admin::require_cap();

		$staff = SBK_Staff_Repository::get_all();

		if ( ! $staff ) {
			self::render_empty();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
		$staff_id = isset( $_GET['staff_id'] ) ? (int) $_GET['staff_id'] : (int) $staff[0]->id;
		$current  = SBK_Staff_Repository::find( $staff_id );

		if ( ! $current ) {
			$current  = $staff[0];
			$staff_id = (int) $current->id;
		}

		$week    = SBK_Schedule_Repository::get_week_map( $staff_id, false );
		$labels  = SBK_Helpers::weekday_labels();
		$counts  = SBK_Schedule_Repository::count_per_staff();
		$others  = array_filter(
			$staff,
			static function ( $member ) use ( $staff_id ) {
				return (int) $member->id !== $staff_id;
			}
		);
		?>
		<div class="wrap sbk-wrap">
			<h1><?php esc_html_e( 'Ωράριο', 'simple-booking' ); ?></h1>

			<?php SBK_Admin::print_notice(); ?>

			<form method="get" class="sbk-staff-switch">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label for="sbk-staff-switch"><?php esc_html_e( 'Άτομο:', 'simple-booking' ); ?></label>
				<select name="staff_id" id="sbk-staff-switch" onchange="this.form.submit()">
					<?php foreach ( $staff as $member ) : ?>
						<?php $has = isset( $counts[ (int) $member->id ] ) ? (int) $counts[ (int) $member->id ] : 0; ?>
						<option value="<?php echo esc_attr( (int) $member->id ); ?>" <?php selected( (int) $member->id, $staff_id ); ?>>
							<?php
							echo esc_html( $member->display_name );
							echo 0 === $has ? esc_html__( ' — χωρίς ωράριο', 'simple-booking' ) : '';
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<noscript><?php submit_button( __( 'Αλλαγή', 'simple-booking' ), '', '', false ); ?></noscript>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbk-schedule">
				<input type="hidden" name="action" value="sbk_save_schedule">
				<input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff_id ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>

				<p class="description">
					<?php esc_html_e( 'Ώρες καταστήματος (Europe/Athens). Άφησε μια ημέρα κενή για ρεπό· πρόσθεσε δεύτερο διάστημα για διάλειμμα.', 'simple-booking' ); ?>
				</p>

				<table class="wp-list-table widefat striped sbk-table sbk-week">
					<thead>
						<tr>
							<th class="sbk-col-day"><?php esc_html_e( 'Ημέρα', 'simple-booking' ); ?></th>
							<th><?php esc_html_e( 'Διαστήματα εργασίας', 'simple-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( SBK_Helpers::weekday_order() as $weekday ) : ?>
						<?php $intervals = isset( $week[ $weekday ] ) ? $week[ $weekday ] : array(); ?>
						<tr class="sbk-day-row" data-weekday="<?php echo esc_attr( $weekday ); ?>">
							<th scope="row" class="sbk-col-day">
								<?php echo esc_html( $labels[ $weekday ] ); ?>
								<span class="sbk-badge sbk-badge--off sbk-day-closed" <?php echo $intervals ? 'hidden' : ''; ?>><?php esc_html_e( 'Ρεπό', 'simple-booking' ); ?></span>
							</th>
							<td>
								<div class="sbk-day" data-weekday="<?php echo esc_attr( $weekday ); ?>">
									<?php foreach ( $intervals as $interval ) : ?>
										<div class="sbk-interval">
											<input type="time" step="300" name="week[<?php echo esc_attr( $weekday ); ?>][start][]" value="<?php echo esc_attr( SBK_Helpers::time_to_input( $interval['start'] ) ); ?>">
											<span class="sbk-interval__dash">—</span>
											<input type="time" step="300" name="week[<?php echo esc_attr( $weekday ); ?>][end][]" value="<?php echo esc_attr( SBK_Helpers::time_to_input( $interval['end'] ) ); ?>">
											<button type="button" class="button-link sbk-interval__remove" aria-label="<?php esc_attr_e( 'Αφαίρεση διαστήματος', 'simple-booking' ); ?>">✕</button>
										</div>
									<?php endforeach; ?>
								</div>
								<button type="button" class="button button-small sbk-day__add"><?php esc_html_e( '+ Διάστημα', 'simple-booking' ); ?></button>
								<button type="button" class="button-link sbk-day__copy"><?php esc_html_e( 'Αντιγραφή σε όλες τις ημέρες', 'simple-booking' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Αποθήκευση ωραρίου', 'simple-booking' ) ); ?>
			</form>

			<?php if ( $others ) : ?>
				<hr>
				<h2><?php esc_html_e( 'Αντιγραφή ωραρίου', 'simple-booking' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbk-copy-week">
					<input type="hidden" name="action" value="sbk_copy_schedule">
					<input type="hidden" name="from_staff_id" value="<?php echo esc_attr( $staff_id ); ?>">
					<?php wp_nonce_field( self::NONCE ); ?>
					<p>
						<?php
						printf(
							/* translators: %s: staff name */
							esc_html__( 'Αντιγραφή του ωραρίου του/της %s σε:', 'simple-booking' ),
							'<strong>' . esc_html( $current->display_name ) . '</strong>'
						);
						?>
						<select name="to_staff_id">
							<?php foreach ( $others as $member ) : ?>
								<option value="<?php echo esc_attr( (int) $member->id ); ?>"><?php echo esc_html( $member->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Αντιγραφή', 'simple-booking' ), 'secondary', 'submit', false ); ?>
					</p>
					<p class="description"><?php esc_html_e( 'Το υπάρχον ωράριο του παραλήπτη αντικαθίσταται.', 'simple-booking' ); ?></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Shown when no staff exist yet.
	 *
	 * @return void
	 */
	private static function render_empty() {
		$url = add_query_arg(
			array(
				'page' => SBK_Admin_Staff::PAGE,
				'view' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbk-wrap">
			<h1><?php esc_html_e( 'Ωράριο', 'simple-booking' ); ?></h1>
			<?php SBK_Admin::print_notice(); ?>
			<p><?php esc_html_e( 'Πρόσθεσε πρώτα προσωπικό για να ορίσεις ωράρια.', 'simple-booking' ); ?></p>
			<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Προσθήκη ατόμου', 'simple-booking' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Persist the weekly schedule.
	 *
	 * @return void
	 */
	public static function handle_save() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$staff_id = isset( $_POST['staff_id'] ) ? (int) $_POST['staff_id'] : 0;

		if ( ! SBK_Staff_Repository::find( $staff_id ) ) {
			SBK_Admin::add_notice( __( 'Το άτομο δεν βρέθηκε.', 'simple-booking' ), 'error' );
			SBK_Admin::redirect( self::PAGE );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- times sanitised in the repository.
		$raw  = isset( $_POST['week'] ) ? wp_unslash( (array) $_POST['week'] ) : array();
		$week = array();

		foreach ( $raw as $weekday => $fields ) {
			$starts = isset( $fields['start'] ) ? (array) $fields['start'] : array();
			$ends   = isset( $fields['end'] ) ? (array) $fields['end'] : array();

			foreach ( $starts as $i => $start ) {
				$week[ (int) $weekday ][] = array(
					'start' => $start,
					'end'   => isset( $ends[ $i ] ) ? $ends[ $i ] : '',
				);
			}
		}

		$result = SBK_Schedule_Repository::replace_for_staff( $staff_id, $week );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
		} else {
			SBK_Admin::add_notice( __( 'Το ωράριο αποθηκεύτηκε.', 'simple-booking' ) );
		}

		SBK_Admin::redirect( self::PAGE, array( 'staff_id' => $staff_id ) );
	}

	/**
	 * Copy one week onto another staff member.
	 *
	 * @return void
	 */
	public static function handle_copy() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$from = isset( $_POST['from_staff_id'] ) ? (int) $_POST['from_staff_id'] : 0;
		$to   = isset( $_POST['to_staff_id'] ) ? (int) $_POST['to_staff_id'] : 0;

		if ( ! $from || ! $to || $from === $to ) {
			SBK_Admin::add_notice( __( 'Επίλεξε δύο διαφορετικά άτομα.', 'simple-booking' ), 'error' );
			SBK_Admin::redirect( self::PAGE, array( 'staff_id' => $from ) );
		}

		$result = SBK_Schedule_Repository::copy_week( $from, $to );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
		} else {
			SBK_Admin::add_notice( __( 'Το ωράριο αντιγράφηκε.', 'simple-booking' ) );
		}

		SBK_Admin::redirect( self::PAGE, array( 'staff_id' => $to ) );
	}
}
