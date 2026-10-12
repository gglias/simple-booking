<?php
/**
 * Staff admin screen: list, form, save, delete.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles everything under Κρατήσεις → Προσωπικό.
 */
class SBK_Admin_Staff {

	const PAGE  = 'sbk-staff';
	const NONCE = 'sbk_staff_form';

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
	 * The staff table.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$staff  = SBK_Staff_Repository::get_all( array( 'search' => $search ) );
		$counts = SBK_Staff_Repository::count_services_per_staff();
		$new_url = add_query_arg(
			array(
				'page' => self::PAGE,
				'view' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap sbk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Προσωπικό', 'simple-booking' ); ?></h1>
			<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Προσθήκη', 'simple-booking' ); ?></a>
			<hr class="wp-header-end">

			<?php SBK_Admin::print_notice(); ?>

			<form method="get" class="sbk-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<p class="search-box">
					<label class="screen-reader-text" for="sbk-staff-search"><?php esc_html_e( 'Αναζήτηση προσωπικού', 'simple-booking' ); ?></label>
					<input type="search" id="sbk-staff-search" name="s" value="<?php echo esc_attr( $search ); ?>">
					<?php submit_button( __( 'Αναζήτηση', 'simple-booking' ), '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped sbk-table">
				<thead>
					<tr>
						<th class="sbk-col-color"></th>
						<th><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Επικοινωνία', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Υπηρεσίες', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Λογαριασμός WP', 'simple-booking' ); ?></th>
						<th><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
						<th class="sbk-col-order"><?php esc_html_e( 'Σειρά', 'simple-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $staff ) : ?>
					<tr>
						<td colspan="7"><?php esc_html_e( 'Δεν υπάρχει ακόμη προσωπικό. Πρόσθεσε το πρώτο άτομο.', 'simple-booking' ); ?></td>
					</tr>
				<?php endif; ?>

				<?php foreach ( $staff as $member ) : ?>
					<?php
					$edit_url = add_query_arg(
						array(
							'page' => self::PAGE,
							'view' => 'edit',
							'id'   => (int) $member->id,
						),
						admin_url( 'admin.php' )
					);

					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'sbk_delete_staff',
								'id'     => (int) $member->id,
							),
							admin_url( 'admin-post.php' )
						),
						'sbk_delete_staff_' . (int) $member->id
					);

					$user = $member->user_id ? get_userdata( (int) $member->user_id ) : null;
					?>
					<tr>
						<td class="sbk-col-color">
							<span class="sbk-swatch" style="background:<?php echo esc_attr( $member->color ? $member->color : '#c3c4c7' ); ?>"></span>
						</td>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $member->display_name ); ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Επεξεργασία', 'simple-booking' ); ?></a> | </span>
								<span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="sbk-delete-staff submitdelete"><?php esc_html_e( 'Διαγραφή', 'simple-booking' ); ?></a></span>
							</div>
						</td>
						<td>
							<?php if ( $member->email ) : ?>
								<a href="mailto:<?php echo esc_attr( $member->email ); ?>"><?php echo esc_html( $member->email ); ?></a><br>
							<?php endif; ?>
							<?php echo $member->phone ? esc_html( $member->phone ) : '<span class="sbk-muted">—</span>'; ?>
						</td>
						<td><?php echo isset( $counts[ (int) $member->id ] ) ? (int) $counts[ (int) $member->id ] : 0; ?></td>
						<td><?php echo $user ? esc_html( $user->user_login ) : '<span class="sbk-muted">—</span>'; ?></td>
						<td>
							<?php if ( $member->is_active ) : ?>
								<span class="sbk-badge sbk-badge--on"><?php esc_html_e( 'Ενεργός', 'simple-booking' ); ?></span>
							<?php else : ?>
								<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργός', 'simple-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="sbk-col-order"><?php echo (int) $member->sort_order; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Add/edit form, including service assignments with overrides.
	 *
	 * @return void
	 */
	private static function render_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$member = $id ? SBK_Staff_Repository::find( $id ) : null;

		if ( $id && ! $member ) {
			wp_die( esc_html__( 'Το άτομο δεν βρέθηκε.', 'simple-booking' ), 404 );
		}

		$assigned = $id ? SBK_Staff_Repository::get_services_map( $id ) : array();
		$services = SBK_Service_Repository::get_all();
		$back_url = add_query_arg( array( 'page' => self::PAGE ), admin_url( 'admin.php' ) );

		$value = static function ( $field, $default = '' ) use ( $member ) {
			return $member && null !== $member->$field ? $member->$field : $default;
		};
		?>
		<div class="wrap sbk-wrap">
			<h1><?php echo $id ? esc_html__( 'Επεξεργασία ατόμου', 'simple-booking' ) : esc_html__( 'Νέο άτομο', 'simple-booking' ); ?></h1>

			<?php SBK_Admin::print_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbk_save_staff">
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-name"><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?> <span class="sbk-req">*</span></label></th>
						<td><input name="display_name" id="sbk-name" type="text" class="regular-text" required value="<?php echo esc_attr( $value( 'display_name' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-email"><?php esc_html_e( 'Email', 'simple-booking' ); ?></label></th>
						<td><input name="email" id="sbk-email" type="email" class="regular-text" value="<?php echo esc_attr( $value( 'email' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-phone"><?php esc_html_e( 'Τηλέφωνο', 'simple-booking' ); ?></label></th>
						<td><input name="phone" id="sbk-phone" type="text" class="regular-text" value="<?php echo esc_attr( $value( 'phone' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-bio"><?php esc_html_e( 'Περιγραφή', 'simple-booking' ); ?></label></th>
						<td>
							<textarea name="bio" id="sbk-bio" rows="4" class="large-text"><?php echo esc_textarea( $value( 'bio' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Εμφανίζεται στο βήμα επιλογής κουρέα.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Φωτογραφία', 'simple-booking' ); ?></th>
						<td>
							<?php $avatar_id = (int) $value( 'avatar_id', 0 ); ?>
							<div class="sbk-media">
								<div class="sbk-media__preview">
									<?php if ( $avatar_id ) : ?>
										<?php echo wp_get_attachment_image( $avatar_id, 'thumbnail' ); ?>
									<?php endif; ?>
								</div>
								<input type="hidden" name="avatar_id" id="sbk-avatar-id" value="<?php echo esc_attr( $avatar_id ); ?>">
								<button type="button" class="button sbk-media__pick"><?php esc_html_e( 'Επιλογή', 'simple-booking' ); ?></button>
								<button type="button" class="button-link sbk-media__clear"><?php esc_html_e( 'Αφαίρεση', 'simple-booking' ); ?></button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-color"><?php esc_html_e( 'Χρώμα ημερολογίου', 'simple-booking' ); ?></label></th>
						<td><input name="color" id="sbk-color" type="text" class="sbk-color-field" value="<?php echo esc_attr( $value( 'color', '#6c5ce7' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-user"><?php esc_html_e( 'Σύνδεση με χρήστη WP', 'simple-booking' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_users(
								array(
									'name'            => 'user_id',
									'id'              => 'sbk-user',
									'selected'        => (int) $value( 'user_id', 0 ),
									'include_selected' => true,
									'show_option_none' => __( '— Κανένας —', 'simple-booking' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Προαιρετικό. Χρήσιμο αν το άτομο θα βλέπει μόνο του το πρόγραμμά του.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-sort"><?php esc_html_e( 'Σειρά εμφάνισης', 'simple-booking' ); ?></label></th>
						<td><input name="sort_order" id="sbk-sort" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr( $value( 'sort_order', 0 ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Κατάσταση', 'simple-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( $member ? (int) $member->is_active : 1, 1 ); ?>>
								<?php esc_html_e( 'Ενεργός — δέχεται κρατήσεις', 'simple-booking' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Υπηρεσίες', 'simple-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Άφησε κενά τα πεδία τιμής και διάρκειας για να ισχύσουν οι προεπιλογές της υπηρεσίας.', 'simple-booking' ); ?></p>

				<?php if ( ! $services ) : ?>
					<p><?php esc_html_e( 'Δεν υπάρχουν υπηρεσίες ακόμη.', 'simple-booking' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat striped sbk-table sbk-assign">
						<thead>
							<tr>
								<th class="sbk-col-check"></th>
								<th><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></th>
								<th><?php esc_html_e( 'Προεπιλογή', 'simple-booking' ); ?></th>
								<th><?php esc_html_e( 'Τιμή (€)', 'simple-booking' ); ?></th>
								<th><?php esc_html_e( 'Διάρκεια (λεπτά)', 'simple-booking' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $services as $service ) : ?>
							<?php
							$sid      = (int) $service->id;
							$is_on    = isset( $assigned[ $sid ] );
							$price    = $is_on && null !== $assigned[ $sid ]['price_cents'] ? SBK_Helpers::cents_to_input( $assigned[ $sid ]['price_cents'] ) : '';
							$duration = $is_on && null !== $assigned[ $sid ]['duration_min'] ? (int) $assigned[ $sid ]['duration_min'] : '';
							?>
							<tr>
								<td class="sbk-col-check">
									<input type="checkbox" class="sbk-assign__toggle" name="services[]" value="<?php echo esc_attr( $sid ); ?>" <?php checked( $is_on ); ?>>
								</td>
								<td>
									<?php echo esc_html( $service->name ); ?>
									<?php if ( ! $service->is_active ) : ?>
										<span class="sbk-badge sbk-badge--off"><?php esc_html_e( 'Ανενεργή', 'simple-booking' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="sbk-muted">
									<?php
									echo esc_html(
										SBK_Helpers::format_money( $service->price_cents ) . ' · ' . SBK_Helpers::format_duration( $service->duration_min )
									);
									?>
								</td>
								<td>
									<input type="text" class="small-text sbk-assign__field" name="override_price[<?php echo esc_attr( $sid ); ?>]" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php echo esc_attr( SBK_Helpers::cents_to_input( $service->price_cents ) ); ?>" <?php disabled( ! $is_on ); ?>>
								</td>
								<td>
									<input type="number" min="1" step="1" class="small-text sbk-assign__field" name="override_duration[<?php echo esc_attr( $sid ); ?>]" value="<?php echo esc_attr( $duration ); ?>" placeholder="<?php echo esc_attr( (int) $service->duration_min ); ?>" <?php disabled( ! $is_on ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
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
	 * Persist the staff form.
	 *
	 * @return void
	 */
	public static function handle_save() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post = wp_unslash( $_POST ); // Sanitised field by field in the repository.

		$result = SBK_Staff_Repository::save( $post, $id );

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

		$map      = array();
		$selected = isset( $post['services'] ) ? array_map( 'intval', (array) $post['services'] ) : array();

		foreach ( $selected as $service_id ) {
			$price    = isset( $post['override_price'][ $service_id ] ) ? trim( (string) $post['override_price'][ $service_id ] ) : '';
			$duration = isset( $post['override_duration'][ $service_id ] ) ? trim( (string) $post['override_duration'][ $service_id ] ) : '';

			$map[ $service_id ] = array(
				'price_cents'  => '' === $price ? null : SBK_Helpers::to_cents( $price ),
				'duration_min' => '' === $duration ? null : SBK_Helpers::clamp_int( $duration, 1, 1440 ),
			);
		}

		SBK_Staff_Repository::set_services( $result, $map );

		SBK_Admin::add_notice( __( 'Το άτομο αποθηκεύτηκε.', 'simple-booking' ) );
		SBK_Admin::redirect(
			self::PAGE,
			array(
				'view' => 'edit',
				'id'   => $result,
			)
		);
	}

	/**
	 * Delete a staff member.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		SBK_Admin::require_cap();

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'sbk_delete_staff_' . $id );

		$result = SBK_Staff_Repository::delete( $id );

		if ( is_wp_error( $result ) ) {
			SBK_Admin::add_notice( $result->get_error_message(), 'error' );
		} else {
			SBK_Admin::add_notice( __( 'Το άτομο διαγράφηκε.', 'simple-booking' ) );
		}

		SBK_Admin::redirect( self::PAGE );
	}
}
