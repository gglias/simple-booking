<?php
/**
 * Extras admin screen: list, form, save, delete.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles Κρατήσεις → Extras.
 */
class SBK_Admin_Extras {

	const PAGE  = 'sbk-extras';
	const NONCE = 'sbk_extra_form';

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
	 * The extras table.
	 *
	 * @return void
	 */
	private static function render_list() {
		$extras  = SBK_Extra_Repository::get_all();
		$counts  = SBK_Extra_Repository::count_services_per_extra();
		$new_url = add_query_arg(
			array(
				'page' => self::PAGE,
				'view' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Extras', 'simple-booking' ); ?></h1>
			<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Προσθήκη', 'simple-booking' ); ?></a>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>

			<p class="description">
				<?php esc_html_e( 'Προαιρετικές προσθήκες που ο πελάτης διαλέγει στο βήμα 3. Προσθέτουν λεπτά και ευρώ στο ραντεβού.', 'simple-booking' ); ?>
			</p>

			<table class="wp-list-table widefat fixed striped sbk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Extra', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Διάρκεια', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Τιμή', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Υπηρεσίες', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
						<th class="sbk-col-order"><?php esc_html_e( 'Σειρά', 'simple-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $extras ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Δεν υπάρχουν extras ακόμη.', 'simple-booking' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $extras as $extra ) : ?>
					<?php
					$id       = (int) $extra->id;
					$edit_url = add_query_arg(
						array(
							'page' => self::PAGE,
							'view' => 'edit',
							'id'   => $id,
						),
						admin_url( 'admin.php' )
					);

					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'sbk_delete_extra',
								'id'     => $id,
							),
							admin_url( 'admin-post.php' )
						),
						'sbk_delete_extra_' . $id
					);

					$linked = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $extra->name ); ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Επεξεργασία', 'simple-booking' ); ?></a> | </span>
								<span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="sbk-delete-extra submitdelete"><?php esc_html_e( 'Διαγραφή', 'simple-booking' ); ?></a></span>
							</div>
						</td>
						<td>
							<?php
							echo (int) $extra->duration_min > 0
								? esc_html( '+' . SBK_Helpers::format_duration( (int) $extra->duration_min ) )
								: '<span class="sbk-muted">—</span>';
							?>
						</td>
						<td><?php echo esc_html( SBK_Helpers::format_money( (int) $extra->price_cents ) ); ?></td>
						<td>
							<?php if ( 0 === $linked ) : ?>
								<span class="sbk-badge sbk-badge--warn"><?php esc_html_e( 'Καμία', 'simple-booking' ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $linked ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $extra->is_active ) : ?>
								<span class="sbk-badge sbk-badge--on"><?php esc_html_e( 'Ενεργό', 'simple-booking' ); ?></span>
							<?php else : ?>
								<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργό', 'simple-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="sbk-col-order"><?php echo (int) $extra->sort_order; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Add/edit form, including which services offer this extra.
	 *
	 * @return void
	 */
	private static function render_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$extra = $id ? SBK_Extra_Repository::find( $id ) : null;

		if ( $id && ! $extra ) {
			wp_die( esc_html__( 'Το extra δεν βρέθηκε.', 'simple-booking' ), 404 );
		}

		$assigned = $id ? SBK_Extra_Repository::get_service_ids( $id ) : array();
		$services = SBK_Service_Repository::get_all();
		$back_url = add_query_arg( array( 'page' => self::PAGE ), admin_url( 'admin.php' ) );

		$value = static function ( $field, $default = '' ) use ( $extra ) {
			return $extra && null !== $extra->$field ? $extra->$field : $default;
		};
		?>
		<div class="wrap sbk-wrap">
			<h1><?php echo $id ? esc_html__( 'Επεξεργασία extra', 'simple-booking' ) : esc_html__( 'Νέο extra', 'simple-booking' ); ?></h1>

			<?php SBK_Admin::print_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbk_save_extra">
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-extra-name"><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
						<td><input name="name" id="sbk-extra-name" type="text" class="regular-text" required value="<?php echo esc_attr( $value( 'name' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-extra-desc"><?php esc_html_e( 'Περιγραφή', 'simple-booking' ); ?></label></th>
						<td><textarea name="description" id="sbk-extra-desc" rows="3" class="large-text"><?php echo esc_textarea( $value( 'description' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-extra-duration"><?php esc_html_e( 'Επιπλέον χρόνος', 'simple-booking' ); ?></label></th>
						<td>
							<input name="duration_min" id="sbk-extra-duration" type="number" min="0" max="1440" step="1" class="small-text" value="<?php echo esc_attr( $value( 'duration_min', 0 ) ); ?>">
							<?php esc_html_e( 'λεπτά', 'simple-booking' ); ?>
							<p class="description"><?php esc_html_e( 'Προστίθεται στη διάρκεια της υπηρεσίας. Το 0 σημαίνει ότι δεν αλλάζει την ώρα.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-extra-price"><?php esc_html_e( 'Επιπλέον χρέωση (€)', 'simple-booking' ); ?></label></th>
						<td><input name="price_euro" id="sbk-extra-price" type="text" class="small-text" value="<?php echo esc_attr( SBK_Helpers::cents_to_input( $value( 'price_cents', 0 ) ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-extra-sort"><?php esc_html_e( 'Σειρά εμφάνισης', 'simple-booking' ); ?></label></th>
						<td><input name="sort_order" id="sbk-extra-sort" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr( $value( 'sort_order', 0 ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( $extra ? (int) $extra->is_active : 1, 1 ); ?>>
								<?php esc_html_e( 'Ενεργό — εμφανίζεται στη φόρμα κράτησης', 'simple-booking' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Σε ποιες υπηρεσίες προσφέρεται', 'simple-booking' ); ?></h2>

				<?php if ( ! $services ) : ?>
					<p><?php esc_html_e( 'Δεν υπάρχουν υπηρεσίες ακόμη.', 'simple-booking' ); ?></p>
				<?php else : ?>
					<ul class="sbk-checklist">
						<?php foreach ( $services as $service ) : ?>
							<li>
								<label>
									<input type="checkbox" name="services[]" value="<?php echo esc_attr( (int) $service->id ); ?>" <?php checked( in_array( (int) $service->id, $assigned, true ) ); ?>>
									<?php echo esc_html( $service->name ); ?>
									<?php if ( ! $service->is_active ) : ?>
										<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργή', 'simple-booking' ); ?></span>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="description"><?php esc_html_e( 'Χωρίς υπηρεσία, το extra δεν εμφανίζεται πουθενά.', 'simple-booking' ); ?></p>
				<?php endif; ?>

				<p class="submit">
					<?php submit_button( $id ? __( 'Αποθήκευση', 'simple-booking' ) : __( 'Προσθήκη', 'simple-booking' ), 'primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Ακύρωση', 'simple-booking' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Persist the extra form.
	 *
	 * @return void
	 */
	public static function handle_save() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post = wp_unslash( $_POST ); // Sanitised field by field in the repository.

		$result = SBK_Extra_Repository::save( $post, $id );

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

		$service_ids = isset( $post['services'] ) ? array_map( 'absint', (array) $post['services'] ) : array();
		SBK_Extra_Repository::set_services( $result, $service_ids );

		SBK_Admin::add_notice( __( 'Το extra αποθηκεύτηκε.', 'simple-booking' ) );
		SBK_Admin::redirect(
			self::PAGE,
			array(
				'view' => 'edit',
				'id'   => $result,
			)
		);
	}

	/**
	 * Delete an extra.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		SBK_Admin::require_cap();

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'sbk_delete_extra_' . $id );

		$result = SBK_Extra_Repository::delete( $id );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
		} else {
			SBK_Admin::add_notice( __( 'Το extra διαγράφηκε.', 'simple-booking' ) );
		}

		SBK_Admin::redirect( self::PAGE );
	}
}
