<?php
/**
 * Services admin screen: list, form, save, delete.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles everything under Κρατήσεις → Υπηρεσίες.
 */
class SBK_Admin_Services {

	const PAGE  = 'sbk-services';
	const NONCE = 'sbk_service_form';

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
	 * The services table.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$services = SBK_Service_Repository::get_all( array( 'search' => $search ) );
		$counts   = SBK_Service_Repository::count_staff_per_service();
		$new_url  = add_query_arg(
			array(
				'page' => self::PAGE,
				'view' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Υπηρεσίες', 'simple-booking' ); ?></h1>
			<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Προσθήκη', 'simple-booking' ); ?></a>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>

			<form method="get" class="sbk-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<p class="search-box">
					<label class="screen-reader-text" for="sbk-service-search"><?php esc_html_e( 'Αναζήτηση υπηρεσιών', 'simple-booking' ); ?></label>
					<input type="search" id="sbk-service-search" name="s" value="<?php echo esc_attr( $search ); ?>">
					<?php submit_button( __( 'Αναζήτηση', 'simple-booking' ), '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped sbk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Κατηγορία', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Διάρκεια', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Buffers', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Τιμή', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Προσωπικό', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
						<th class="sbk-col-order"><?php esc_html_e( 'Σειρά', 'simple-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $services ) : ?>
					<tr>
						<td colspan="8"><?php esc_html_e( 'Δεν υπάρχουν υπηρεσίες ακόμη. Πρόσθεσε την πρώτη.', 'simple-booking' ); ?></td>
					</tr>
				<?php endif; ?>

				<?php foreach ( $services as $service ) : ?>
					<?php
					$edit_url = add_query_arg(
						array(
							'page' => self::PAGE,
							'view' => 'edit',
							'id'   => (int) $service->id,
						),
						admin_url( 'admin.php' )
					);

					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'sbk_delete_service',
								'id'     => (int) $service->id,
							),
							admin_url( 'admin-post.php' )
						),
						'sbk_delete_service_' . (int) $service->id
					);

					$staff_count = isset( $counts[ (int) $service->id ] ) ? (int) $counts[ (int) $service->id ] : 0;
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $service->name ); ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Επεξεργασία', 'simple-booking' ); ?></a> | </span>
								<span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="sbk-delete-service submitdelete"><?php esc_html_e( 'Διαγραφή', 'simple-booking' ); ?></a></span>
							</div>
						</td>
						<td><?php echo $service->category ? esc_html( $service->category ) : '<span class="sbk-muted">—</span>'; ?></td>
						<td><?php echo esc_html( SBK_Helpers::format_duration( $service->duration_min ) ); ?></td>
						<td>
							<?php if ( $service->buffer_before_min || $service->buffer_after_min ) : ?>
								<?php echo esc_html( sprintf( '+%d / +%d λ', (int) $service->buffer_before_min, (int) $service->buffer_after_min ) ); ?>
							<?php else : ?>
								<span class="sbk-muted">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( SBK_Helpers::format_money( $service->price_cents ) ); ?></td>
						<td>
							<?php if ( 0 === $staff_count ) : ?>
								<span class="sbk-badge sbk-badge--warn"><?php esc_html_e( 'Κανείς', 'simple-booking' ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $staff_count ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $service->is_active ) : ?>
								<span class="sbk-badge sbk-badge--on"><?php esc_html_e( 'Ενεργή', 'simple-booking' ); ?></span>
							<?php else : ?>
								<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργή', 'simple-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="sbk-col-order"><?php echo (int) $service->sort_order; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Add/edit form, including staff assignment.
	 *
	 * @return void
	 */
	private static function render_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = $id ? SBK_Service_Repository::find( $id ) : null;

		if ( $id && ! $service ) {
			wp_die( esc_html__( 'Η υπηρεσία δεν βρέθηκε.', 'simple-booking' ), 404 );
		}

		$assigned_staff  = $id ? SBK_Service_Repository::get_staff_ids( $id ) : array();
		$assigned_extras = $id ? SBK_Extra_Repository::get_extra_ids( $id ) : array();
		$extras          = SBK_Extra_Repository::get_all();
		$staff           = SBK_Staff_Repository::get_all();
		$back_url        = add_query_arg( array( 'page' => self::PAGE ), admin_url( 'admin.php' ) );

		$value = static function ( $field, $default = '' ) use ( $service ) {
			return $service && null !== $service->$field ? $service->$field : $default;
		};
		?>
		<div class="wrap sbk-wrap">
			<h1><?php echo $id ? esc_html__( 'Επεξεργασία υπηρεσίας', 'simple-booking' ) : esc_html__( 'Νέα υπηρεσία', 'simple-booking' ); ?></h1>

			<?php SBK_Admin::print_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbk_save_service">
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-svc-name"><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
						<td><input name="name" id="sbk-svc-name" type="text" class="regular-text" required value="<?php echo esc_attr( $value( 'name' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-svc-slug"><?php esc_html_e( 'Slug', 'simple-booking' ); ?></label></th>
						<td>
							<input name="slug" id="sbk-svc-slug" type="text" class="regular-text" value="<?php echo esc_attr( $value( 'slug' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Αν μείνει κενό, παράγεται από το όνομα.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-svc-category"><?php esc_html_e( 'Κατηγορία', 'simple-booking' ); ?></label></th>
						<td><input name="category" id="sbk-svc-category" type="text" class="regular-text" value="<?php echo esc_attr( $value( 'category' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-svc-desc"><?php esc_html_e( 'Περιγραφή', 'simple-booking' ); ?></label></th>
						<td><textarea name="description" id="sbk-svc-desc" rows="4" class="large-text"><?php echo esc_textarea( $value( 'description' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-svc-duration"><?php esc_html_e( 'Διάρκεια (λεπτά)', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
						<td>
							<input name="duration_min" id="sbk-svc-duration" type="number" min="1" max="1440" step="1" required class="small-text" value="<?php echo esc_attr( $value( 'duration_min', 30 ) ); ?>">
							<p class="description"><?php esc_html_e( 'Ο χρόνος που βλέπει ο πελάτης.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Buffers (λεπτά)', 'simple-booking' ); ?></th>
						<td>
							<label for="sbk-svc-buffer-before"><?php esc_html_e( 'Πριν', 'simple-booking' ); ?></label>
							<input name="buffer_before_min" id="sbk-svc-buffer-before" type="number" min="0" max="1440" step="1" class="small-text" value="<?php echo esc_attr( $value( 'buffer_before_min', 0 ) ); ?>">
							&nbsp;
							<label for="sbk-svc-buffer-after"><?php esc_html_e( 'Μετά', 'simple-booking' ); ?></label>
							<input name="buffer_after_min" id="sbk-svc-buffer-after" type="number" min="0" max="1440" step="1" class="small-text" value="<?php echo esc_attr( $value( 'buffer_after_min', 0 ) ); ?>">
							<p class="description"><?php esc_html_e( 'Δεσμεύονται στο ημερολόγιο αλλά δεν φαίνονται στον πελάτη.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-svc-price"><?php esc_html_e( 'Τιμή (€)', 'simple-booking' ); ?></label></th>
						<td>
							<input name="price_euro" id="sbk-svc-price" type="text" class="small-text" value="<?php echo esc_attr( SBK_Helpers::cents_to_input( $value( 'price_cents', 0 ) ) ); ?>">
							<p class="description"><?php esc_html_e( 'Η πληρωμή γίνεται στο κατάστημα.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-svc-sort"><?php esc_html_e( 'Σειρά εμφάνισης', 'simple-booking' ); ?></label></th>
						<td><input name="sort_order" id="sbk-svc-sort" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr( $value( 'sort_order', 0 ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( $service ? (int) $service->is_active : 1, 1 ); ?>>
								<?php esc_html_e( 'Ενεργή — εμφανίζεται στη φόρμα κράτησης', 'simple-booking' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Ποιοι την εκτελούν', 'simple-booking' ); ?></h2>

				<?php if ( ! $staff ) : ?>
					<p><?php esc_html_e( 'Δεν υπάρχει προσωπικό ακόμη.', 'simple-booking' ); ?></p>
				<?php else : ?>
					<ul class="sbk-checklist">
						<?php foreach ( $staff as $member ) : ?>
							<li>
								<label>
									<input type="checkbox" name="staff[]" value="<?php echo esc_attr( (int) $member->id ); ?>" <?php checked( in_array( (int) $member->id, $assigned_staff, true ) ); ?>>
									<?php echo esc_html( $member->display_name ); ?>
									<?php if ( ! $member->is_active ) : ?>
										<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργός', 'simple-booking' ); ?></span>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="description"><?php esc_html_e( 'Οι εξατομικευμένες τιμές ανά άτομο ορίζονται στην καρτέλα του ατόμου.', 'simple-booking' ); ?></p>
				<?php endif; ?>


				<h2><?php esc_html_e( 'Διαθέσιμα extras', 'simple-booking' ); ?></h2>

				<?php if ( ! $extras ) : ?>
					<p><?php esc_html_e( 'Δεν υπάρχουν extras ακόμη.', 'simple-booking' ); ?></p>
				<?php else : ?>
					<ul class="sbk-checklist">
						<?php foreach ( $extras as $extra ) : ?>
							<li>
								<label>
									<input type="checkbox" name="extras[]" value="<?php echo esc_attr( (int) $extra->id ); ?>" <?php checked( in_array( (int) $extra->id, $assigned_extras, true ) ); ?>>
									<?php echo esc_html( $extra->name ); ?>
									<span class="sbk-muted">
										<?php echo esc_html( '+' . SBK_Helpers::format_money( (int) $extra->price_cents ) ); ?>
										<?php echo (int) $extra->duration_min > 0 ? esc_html( ' · +' . SBK_Helpers::format_duration( (int) $extra->duration_min ) ) : ''; ?>
									</span>
									<?php if ( ! $extra->is_active ) : ?>
										<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργό', 'simple-booking' ); ?></span>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
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
	 * Persist the service form.
	 *
	 * @return void
	 */
	public static function handle_save() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post = wp_unslash( $_POST ); // Sanitised field by field in the repository.

		$result = SBK_Service_Repository::save( $post, $id );

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

		$staff_ids = isset( $post['staff'] ) ? array_map( 'intval', (array) $post['staff'] ) : array();
		SBK_Service_Repository::set_staff( $result, $staff_ids );

		$extra_ids = isset( $post['extras'] ) ? array_map( 'absint', (array) $post['extras'] ) : array();
		SBK_Extra_Repository::set_extras( $result, $extra_ids );

		SBK_Admin::add_notice( __( 'Η υπηρεσία αποθηκεύτηκε.', 'simple-booking' ) );
		SBK_Admin::redirect(
			self::PAGE,
			array(
				'view' => 'edit',
				'id'   => $result,
			)
		);
	}

	/**
	 * Delete a service.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		SBK_Admin::require_cap();

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'sbk_delete_service_' . $id );

		$result = SBK_Service_Repository::delete( $id );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
		} else {
			SBK_Admin::add_notice( __( 'Η υπηρεσία διαγράφηκε.', 'simple-booking' ) );
		}

		SBK_Admin::redirect( self::PAGE );
	}
}
