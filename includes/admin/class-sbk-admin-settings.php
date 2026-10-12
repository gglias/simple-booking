<?php
/**
 * Settings screen: everything that until now lived only as an option.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles Κρατήσεις → Ρυθμίσεις.
 */
class SBK_Admin_Settings {

	const PAGE       = 'sbk-settings';
	const NONCE      = 'sbk_settings_form';
	const NONCE_TEST = 'sbk_settings_test_mail';

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		SBK_Admin::require_cap();

		$settings   = SBK_Settings::all();
		$page_id    = (int) $settings['appointment_page_id'];
		$page_link  = $page_id ? get_permalink( $page_id ) : '';
		$booking_id = self::find_booking_page();
		?>
		<div class="wrap sbk-wrap">
			<h1><?php esc_html_e( 'Ρυθμίσεις', 'simple-booking' ); ?></h1>

			<?php SBK_Admin::print_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbk_save_settings">
				<?php wp_nonce_field( self::NONCE ); ?>

				<h2 class="title"><?php esc_html_e( 'Το κατάστημα', 'simple-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Εμφανίζονται στα email και στη σελίδα του ραντεβού.', 'simple-booking' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-salon-name"><?php esc_html_e( 'Όνομα', 'simple-booking' ); ?></label></th>
						<td>
							<input type="text" id="sbk-salon-name" name="salon_name" class="regular-text"
								value="<?php echo esc_attr( $settings['salon_name'] ); ?>"
								placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Κενό σημαίνει το όνομα του site.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-salon-address"><?php esc_html_e( 'Διεύθυνση', 'simple-booking' ); ?></label></th>
						<td>
							<input type="text" id="sbk-salon-address" name="salon_address" class="regular-text"
								value="<?php echo esc_attr( $settings['salon_address'] ); ?>"
								placeholder="<?php esc_attr_e( 'π.χ. Επταπυργίου 44, Συκιές', 'simple-booking' ); ?>">
							<p class="description"><?php esc_html_e( 'Γίνεται σύνδεσμος προς Google Maps στη σελίδα του ραντεβού.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-salon-phone"><?php esc_html_e( 'Τηλέφωνο', 'simple-booking' ); ?></label></th>
						<td>
							<input type="text" id="sbk-salon-phone" name="salon_phone" class="regular-text"
								value="<?php echo esc_attr( $settings['salon_phone'] ); ?>">
							<p class="description"><?php esc_html_e( 'Εδώ καλεί ο πελάτης για αλλαγή ή ακύρωση.', 'simple-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Κανόνες κρατήσεων', 'simple-booking' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-slot-step"><?php esc_html_e( 'Βήμα ωρών', 'simple-booking' ); ?></label></th>
						<td>
							<input type="number" id="sbk-slot-step" name="slot_step_min" class="small-text" min="5" max="120" step="5"
								value="<?php echo esc_attr( $settings['slot_step_min'] ); ?>"> <?php esc_html_e( 'λεπτά', 'simple-booking' ); ?>
							<p class="description"><?php esc_html_e( 'Κάθε πόσο προσφέρεται ώρα έναρξης: 15 σημαίνει 09:00, 09:15, 09:30…', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-lead-time"><?php esc_html_e( 'Ελάχιστη προειδοποίηση', 'simple-booking' ); ?></label></th>
						<td>
							<input type="number" id="sbk-lead-time" name="lead_time_min" class="small-text" min="0" max="10080" step="15"
								value="<?php echo esc_attr( $settings['lead_time_min'] ); ?>"> <?php esc_html_e( 'λεπτά', 'simple-booking' ); ?>
							<p class="description"><?php esc_html_e( 'Πόσο πριν κλείνει η online κράτηση. 120 = δεν κλείνεται ραντεβού για τις επόμενες 2 ώρες.', 'simple-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-max-advance"><?php esc_html_e( 'Ορίζοντας κρατήσεων', 'simple-booking' ); ?></label></th>
						<td>
							<input type="number" id="sbk-max-advance" name="max_advance_days" class="small-text" min="1" max="365" step="1"
								value="<?php echo esc_attr( $settings['max_advance_days'] ); ?>"> <?php esc_html_e( 'ημέρες', 'simple-booking' ); ?>
							<p class="description"><?php esc_html_e( 'Πόσο μακριά στο μέλλον ανοίγει το ημερολόγιο.', 'simple-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Email', 'simple-booking' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Αποστολές', 'simple-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="email_customer_enabled" value="1" <?php checked( $settings['email_customer_enabled'] ); ?>>
								<?php esc_html_e( 'Επιβεβαίωση στον πελάτη', 'simple-booking' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="email_staff_enabled" value="1" <?php checked( $settings['email_staff_enabled'] ); ?>>
								<?php esc_html_e( 'Ειδοποίηση στον κουρέα και στο κατάστημα', 'simple-booking' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sbk-admin-email"><?php esc_html_e( 'Email καταστήματος', 'simple-booking' ); ?></label></th>
						<td>
							<input type="email" id="sbk-admin-email" name="email_admin_address" class="regular-text"
								value="<?php echo esc_attr( $settings['email_admin_address'] ); ?>"
								placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Λαμβάνει κάθε νέα κράτηση. Κενό σημαίνει το email διαχειριστή του WordPress.', 'simple-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Σελίδες', 'simple-booking' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sbk-appointment-page"><?php esc_html_e( 'Σελίδα ραντεβού', 'simple-booking' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'appointment_page_id',
									'id'                => 'sbk-appointment-page',
									'selected'          => $page_id,
									'show_option_none'  => __( '— Καμία —', 'simple-booking' ),
									'option_none_value' => 0,
								)
							);
							?>
							<?php if ( $page_link ) : ?>
								<a href="<?php echo esc_url( $page_link ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
									<?php esc_html_e( 'Προβολή', 'simple-booking' ); ?>
								</a>
							<?php endif; ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: shortcode */
									esc_html__( 'Εδώ οδηγεί το κουμπί του email. Πρέπει να περιέχει το %s.', 'simple-booking' ),
									'<code>[' . esc_html( SBK_Appointment_Page::SHORTCODE ) . ']</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Φόρμα κράτησης', 'simple-booking' ); ?></th>
						<td>
							<code>[simple_booking]</code>
							<?php if ( $booking_id ) : ?>
								<a href="<?php echo esc_url( get_permalink( $booking_id ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
									<?php echo esc_html( get_the_title( $booking_id ) ); ?>
								</a>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Βάλε αυτό το shortcode στη σελίδα όπου θέλεις τη φόρμα των 7 βημάτων.', 'simple-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Αποθήκευση ρυθμίσεων', 'simple-booking' ) ); ?>
			</form>

			<hr>

			<h2 class="title"><?php esc_html_e( 'Δοκιμή email', 'simple-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Στέλνει ένα δοκιμαστικό μήνυμα για να δεις αν ο διακομιστής στέλνει κανονικά.', 'simple-booking' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbk_test_mail">
				<?php wp_nonce_field( self::NONCE_TEST ); ?>
				<p>
					<input type="email" name="to" class="regular-text" value="<?php echo esc_attr( SBK_Settings::admin_address() ); ?>" required>
					<?php submit_button( __( 'Αποστολή δοκιμής', 'simple-booking' ), 'secondary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * The page holding the booking form, if there is one.
	 *
	 * @return int
	 */
	private static function find_booking_page() {
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[simple_booking]',
				'fields'         => 'ids',
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Persist the settings.
	 *
	 * @return void
	 */
	public static function handle_save() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE );

		$post = wp_unslash( $_POST ); // Sanitised by SBK_Settings per declared type.

		SBK_Settings::update(
			array(
				'salon_name'             => isset( $post['salon_name'] ) ? $post['salon_name'] : '',
				'salon_address'          => isset( $post['salon_address'] ) ? $post['salon_address'] : '',
				'salon_phone'            => isset( $post['salon_phone'] ) ? $post['salon_phone'] : '',
				'slot_step_min'          => SBK_Helpers::clamp_int( isset( $post['slot_step_min'] ) ? $post['slot_step_min'] : 15, 5, 120 ),
				'lead_time_min'          => SBK_Helpers::clamp_int( isset( $post['lead_time_min'] ) ? $post['lead_time_min'] : 120, 0, 10080 ),
				'max_advance_days'       => SBK_Helpers::clamp_int( isset( $post['max_advance_days'] ) ? $post['max_advance_days'] : 60, 1, 365 ),
				'email_customer_enabled' => ! empty( $post['email_customer_enabled'] ),
				'email_staff_enabled'    => ! empty( $post['email_staff_enabled'] ),
				'email_admin_address'    => isset( $post['email_admin_address'] ) ? $post['email_admin_address'] : '',
				'appointment_page_id'    => isset( $post['appointment_page_id'] ) ? (int) $post['appointment_page_id'] : 0,
			)
		);

		// An address that was typed but rejected as invalid would vanish silently.
		if ( ! empty( $post['email_admin_address'] ) && '' === SBK_Settings::get( 'email_admin_address' ) ) {
			SBK_Admin::add_notice( __( 'Οι ρυθμίσεις αποθηκεύτηκαν, αλλά το email καταστήματος δεν ήταν έγκυρο και αγνοήθηκε.', 'simple-booking' ), 'error' );
		} else {
			SBK_Admin::add_notice( __( 'Οι ρυθμίσεις αποθηκεύτηκαν.', 'simple-booking' ) );
		}

		SBK_Admin::redirect( self::PAGE );
	}

	/**
	 * Send a test email.
	 *
	 * @return void
	 */
	public static function handle_test_mail() {
		SBK_Admin::require_cap();
		check_admin_referer( self::NONCE_TEST );

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';

		if ( ! is_email( $to ) ) {
			SBK_Admin::add_notice( __( 'Δώσε έγκυρο email.', 'simple-booking' ), 'error' );
			SBK_Admin::redirect( self::PAGE );
		}

		// Use the same sender as the real emails, so the test tests them too.
		$from = static function () {
			return SBK_Settings::salon_name();
		};

		add_filter( 'wp_mail_from_name', $from );

		$sent = wp_mail(
			$to,
			sprintf(
				/* translators: %s: salon name */
				__( 'Δοκιμή από %s', 'simple-booking' ),
				SBK_Settings::salon_name()
			),
			__( 'Αν λαμβάνεις αυτό το μήνυμα, η αποστολή email δουλεύει.', 'simple-booking' )
		);

		remove_filter( 'wp_mail_from_name', $from );

		if ( $sent ) {
			/* translators: %s: email address */
			SBK_Admin::add_notice( sprintf( __( 'Το δοκιμαστικό email στάλθηκε στο %s.', 'simple-booking' ), $to ) );
		} else {
			SBK_Admin::add_notice( __( 'Η αποστολή απέτυχε. Δες τις ρυθμίσεις email του server.', 'simple-booking' ), 'error' );
		}

		SBK_Admin::redirect( self::PAGE );
	}
}
