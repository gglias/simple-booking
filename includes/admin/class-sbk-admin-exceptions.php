<?php
/**
 * Exceptions screen: closures and one-off hours for specific dates.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles Κρατήσεις → Εξαιρέσεις.
 */
class SBK_Admin_Exceptions {

	const PAGE  = 'sbk-exceptions';
	const NONCE = 'sbk_exception_form';

	/**
	 * Route between list and form views.
	 *
	 * @return void
	 */
	public static function render() {
		SBK_Admin::require_cap();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list';

		if ( 'edit' === $view || 'new' === $view ) {
			self::render_form();
			return;
		}

		self::render_list();
	}

	/**
	 * Staff id => name, including the shop-wide pseudo entry.
	 *
	 * @return array<int,string>
	 */
	private static function staff_labels() {
		$labels = array( SBK_Exception_Repository::ALL_STAFF => __( 'Όλο το κατάστημα', 'simple-booking' ) );

		foreach ( SBK_Staff_Repository::get_all() as $member ) {
			$labels[ (int) $member->id ] = $member->display_name;
		}

		return $labels;
	}

	/**
	 * The exceptions table.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$show_past = isset( $_GET['past'] ) && '1' === $_GET['past'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$staff_filter = isset( $_GET['staff_id'] ) ? (int) $_GET['staff_id'] : -1;

		$today = ( new DateTime( 'now', SBK_Helpers::salon_timezone() ) )->format( 'Y-m-d' );

		$rows = SBK_Exception_Repository::get_all(
			array(
				'staff_id'     => $staff_filter,
				'include_shop' => false,
				'from'         => $show_past ? '' : $today,
			)
		);

		$labels  = self::staff_labels();
		$new_url = add_query_arg(
			array(
				'page' => self::PAGE,
				'view' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Εξαιρέσεις', 'simple-booking' ); ?></h1>
			<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Προσθήκη', 'simple-booking' ); ?></a>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>

			<form method="get" class="sbk-staff-switch">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label for="sbk-exc-staff"><?php esc_html_e( 'Άτομο:', 'simple-booking' ); ?></label>
				<select name="staff_id" id="sbk-exc-staff">
					<option value="-1" <?php selected( $staff_filter, -1 ); ?>><?php esc_html_e( 'Όλα', 'simple-booking' ); ?></option>
					<?php foreach ( $labels as $id => $label ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $staff_filter, $id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="sbk-inline-check">
					<input type="checkbox" name="past" value="1" <?php checked( $show_past ); ?>>
					<?php esc_html_e( 'Εμφάνιση παρελθόντων', 'simple-booking' ); ?>
				</label>
				<?php submit_button( __( 'Φιλτράρισμα', 'simple-booking' ), 'secondary', '', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped sbk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ημερομηνία', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Ισχύει για', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Τι αλλάζει', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Σημείωση', 'simple-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'Καμία εξαίρεση.', 'simple-booking' ); ?></td>
					</tr>
				<?php endif; ?>

				<?php foreach ( $rows as $row ) : ?>
					<?php
					$edit_url = add_query_arg(
						array(
							'page' => self::PAGE,
							'view' => 'edit',
							'id'   => (int) $row->id,
						),
						admin_url( 'admin.php' )
					);

					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'sbk_delete_exception',
								'id'     => (int) $row->id,
							),
							admin_url( 'admin-post.php' )
						),
						'sbk_delete_exception_' . (int) $row->id
					);

					$is_past = $row->exception_date < $today;
					?>
					<tr<?php echo $is_past ? ' class="sbk-past"' : ''; ?>>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( SBK_Helpers::format_date( $row->exception_date ) ); ?></a></strong>
							<?php if ( $is_past ) : ?>
								<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Παρελθόν', 'simple-booking' ); ?></span>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Επεξεργασία', 'simple-booking' ); ?></a> | </span>
								<span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="sbk-delete-exception submitdelete"><?php esc_html_e( 'Διαγραφή', 'simple-booking' ); ?></a></span>
							</div>
						</td>
						<td>
							<?php
							$staff_id = (int) $row->staff_id;
							echo isset( $labels[ $staff_id ] )
								? esc_html( $labels[ $staff_id ] )
								: '<span class="sbk-muted">' . esc_html__( '(διαγραμμένο άτομο)', 'simple-booking' ) . '</span>';
							?>
						</td>
						<td>
							<?php if ( (int) $row->is_closed ) : ?>
								<span class="sbk-badge sbk-badge--warn"><?php esc_html_e( 'Κλειστά', 'simple-booking' ); ?></span>
							<?php else : ?>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: start time, 2: end time */
										__( 'Ειδικό ωράριο %1$s – %2$s', 'simple-booking' ),
										SBK_Helpers::time_to_input( $row->start_time ),
										SBK_Helpers::time_to_input( $row->end_time )
									)
								);
								?>
							<?php endif; ?>
						</td>
						<td><?php echo $row->note ? esc_html( $row->note ) : '<span class="sbk-muted">—</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Add/edit form.
	 *
	 * @return void
	 */
	private static function render_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$row = $id ? SBK_Exception_Repository::find( $id ) : null;

		if ( $id && ! $row ) {
			wp_die( esc_html__( 'Η εξαίρεση δεν βρέθηκε.', 'simple-booking' ), 404 );
		}

		$labels    = self::staff_labels();
		$back_url  = add_query_arg( array( 'page' => self::PAGE ), admin_url( 'admin.php' ) );
		$is_closed = $row ? (int) $row->is_closed : 1;
		$today     = ( new DateTime( 'now', SBK_Helpers::salon_timezone() ) )->format( 'Y-m-d' );
		?>
		<div class="wrap sbk-wrap">
			<h1><?php echo $id ? esc_html__( 'Επεξεργασία εξαίρεσης', 'simple-booking' ) : esc_html__( 'Νέα εξαίρεση', 'simple-booking' ); ?></h1>

			<?php SBK_Admin::print_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbk_save_exception">
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-exc-date"><?php esc_html_e( 'Ημερομηνία', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
						<td><input type="date" id="sbk-exc-date" name="exception_date" required value="<?php echo esc_attr( $row ? $row->exception_date : $today ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-exc-staff-select"><?php esc_html_e( 'Ισχύει για', 'simple-booking' ); ?></label></th>
						<td>
							<select name="staff_id" id="sbk-exc-staff-select">
								<?php foreach ( $labels as $sid => $label ) : ?>
									<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $row ? (int) $row->staff_id : 0, $sid ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Τι αλλάζει', 'simple-booking' ); ?></th>
						<td>
							<fieldset class="sbk-exc-type">
								<label>
									<input type="radio" name="is_closed" value="1" <?php checked( $is_closed, 1 ); ?>>
									<?php esc_html_e( 'Κλειστά όλη την ημέρα', 'simple-booking' ); ?>
								</label><br>
								<label>
									<input type="radio" name="is_closed" value="0" <?php checked( $is_closed, 0 ); ?>>
									<?php esc_html_e( 'Ειδικό ωράριο', 'simple-booking' ); ?>
								</label>
							</fieldset>

							<p class="sbk-exc-window" <?php echo $is_closed ? 'hidden' : ''; ?>>
								<label for="sbk-exc-start"><?php esc_html_e( 'Από', 'simple-booking' ); ?></label>
								<input type="time" step="300" id="sbk-exc-start" name="start_time" value="<?php echo esc_attr( $row ? SBK_Helpers::time_to_input( $row->start_time ) : '' ); ?>">
								<label for="sbk-exc-end"><?php esc_html_e( 'Έως', 'simple-booking' ); ?></label>
								<input type="time" step="300" id="sbk-exc-end" name="end_time" value="<?php echo esc_attr( $row ? SBK_Helpers::time_to_input( $row->end_time ) : '' ); ?>">
							</p>
							<p class="description"><?php esc_html_e( 'Το ειδικό ωράριο αντικαθιστά πλήρως το κανονικό ωράριο της ημέρας.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-exc-note"><?php esc_html_e( 'Σημείωση', 'simple-booking' ); ?></label></th>
						<td>
							<input type="text" id="sbk-exc-note" name="note" class="regular-text" value="<?php echo esc_attr( $row ? (string) $row->note : '' ); ?>">
							<p class="description"><?php esc_html_e( 'Π.χ. «Αργία», «Άδεια». Μόνο για εσωτερική χρήση.', 'simple-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php submit_button( $id ? __( 'Αποθήκευση', 'simple-booking' ) : __( 'Προσθήκη', 'simple-booking' ), 'primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Ακύρωση', 'simple-booking' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Persist the exception form.
	 *
	 * @return void
	 */
	public static function handle_save() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post   = wp_unslash( $_POST ); // Sanitised field by field in the repository.
		$result = SBK_Exception_Repository::save( $post, $id );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
			SBK_Admin::redirect(
				self::PAGE,
				array(
					'view' => $id ? 'edit' : 'new',
					'id'   => $id,
				)
			);
		}

		SBK_Admin::add_notice( __( 'Η εξαίρεση αποθηκεύτηκε.', 'simple-booking' ) );
		SBK_Admin::redirect( self::PAGE );
	}

	/**
	 * Delete an exception.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		SBK_Admin::require_cap();

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'sbk_delete_exception_' . $id );

		SBK_Exception_Repository::delete( $id );
		SBK_Admin::add_notice( __( 'Η εξαίρεση διαγράφηκε.', 'simple-booking' ) );
		SBK_Admin::redirect( self::PAGE );
	}
}
