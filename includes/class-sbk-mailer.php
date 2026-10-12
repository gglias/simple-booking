<?php
/**
 * Confirmation and notification emails.
 *
 * Hooked to sbk_appointment_created, which fires after the appointment is
 * committed. A failing mail server therefore cannot cost anyone a booking.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the booking emails.
 */
class SBK_Mailer {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'sbk_appointment_created', array( __CLASS__, 'on_appointment_created' ), 10, 2 );
	}

	/**
	 * Send both emails for a new appointment.
	 *
	 * @param object $appointment Stored appointment row.
	 * @param array  $context     Customer id, extras, account result.
	 * @return void
	 */
	public static function on_appointment_created( $appointment, $context ) {
		if ( ! $appointment ) {
			return;
		}

		$customer = SBK_Customer_Repository::find( (int) $context['customer_id'] );

		if ( ! $customer ) {
			return;
		}

		$data = self::prepare( $appointment, $customer );

		if ( SBK_Settings::get( 'email_customer_enabled' ) ) {
			self::send_customer( $data );
		}

		if ( SBK_Settings::get( 'email_staff_enabled' ) ) {
			self::send_staff( $data );
		}
	}

	/**
	 * Everything both templates need, already formatted for display.
	 *
	 * @param object $appointment Appointment row.
	 * @param object $customer    Customer row.
	 * @return array
	 */
	private static function prepare( $appointment, $customer ) {
		$tz    = SBK_Helpers::salon_timezone();
		$start = new DateTime( $appointment->start_utc, new DateTimeZone( 'UTC' ) );
		$end   = new DateTime( $appointment->end_utc, new DateTimeZone( 'UTC' ) );

		$start->setTimezone( $tz );
		$end->setTimezone( $tz );

		$extras = array();

		foreach ( SBK_Appointment_Repository::get_extras( (int) $appointment->id ) as $extra ) {
			$extras[] = array(
				'name'  => $extra->extra_name,
				'price' => SBK_Helpers::format_money( (int) $extra->price_cents ),
			);
		}

		return array(
			'appointment'    => $appointment,
			'customer'       => $customer,
			'salon_name'     => SBK_Settings::salon_name(),
			'salon_address'  => SBK_Settings::get( 'salon_address' ),
			'salon_phone'    => SBK_Settings::get( 'salon_phone' ),
			'customer_name'  => trim( $customer->first_name . ' ' . (string) $customer->last_name ),
			'date_label'     => SBK_Helpers::format_date( $start->format( 'Y-m-d' ) ),
			'time_label'     => $start->format( 'H:i' ) . ' – ' . $end->format( 'H:i' ),
			'duration_label' => SBK_Helpers::format_duration( (int) $appointment->duration_min ),
			'service_price'  => SBK_Helpers::format_money( (int) $appointment->service_price_cents ),
			'total_label'    => SBK_Helpers::format_money( (int) $appointment->total_price_cents ),
			'extras'         => $extras,
			'note'           => (string) $appointment->customer_note,
			'manage_url'     => SBK_Appointment_Page::url( $appointment->manage_token ),
			'reference'      => SBK_Appointment_Page::reference( $appointment->manage_token ),
		);
	}

	/**
	 * Confirmation to the customer.
	 *
	 * @param array $data Prepared data.
	 * @return bool
	 */
	private static function send_customer( array $data ) {
		$subject = sprintf(
			/* translators: 1: date, 2: time */
			__( 'Το ραντεβού σου επιβεβαιώθηκε — %1$s στις %2$s', 'simple-booking' ),
			$data['date_label'],
			$data['appointment']->start_utc ? substr( $data['time_label'], 0, 5 ) : ''
		);

		/**
		 * Filter the customer confirmation subject.
		 *
		 * @param string $subject Subject line.
		 * @param array  $data    Prepared data.
		 */
		$subject = apply_filters( 'sbk_email_customer_subject', $subject, $data );

		$body = self::render( 'customer-confirmation', $data );

		/**
		 * Filter the customer confirmation body.
		 *
		 * @param string $body HTML body.
		 * @param array  $data Prepared data.
		 */
		$body = apply_filters( 'sbk_email_customer_body', $body, $data );

		return self::send( $data['customer']->email, $subject, $body, $data );
	}

	/**
	 * Notification to the staff member, with the salon in copy.
	 *
	 * @param array $data Prepared data.
	 * @return bool
	 */
	private static function send_staff( array $data ) {
		$appointment = $data['appointment'];
		$staff       = SBK_Staff_Repository::find( (int) $appointment->staff_id );

		$recipients = array();

		if ( $staff && $staff->email && is_email( $staff->email ) ) {
			$recipients[] = $staff->email;
		}

		$admin = SBK_Settings::admin_address();

		if ( $admin && ! in_array( $admin, $recipients, true ) ) {
			$recipients[] = $admin;
		}

		/**
		 * Filter who gets the new-booking notification.
		 *
		 * @param string[] $recipients Email addresses.
		 * @param array    $data       Prepared data.
		 */
		$recipients = apply_filters( 'sbk_email_staff_recipients', $recipients, $data );

		if ( ! $recipients ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: service name, 2: date, 3: time */
			__( 'Νέο ραντεβού: %1$s — %2$s %3$s', 'simple-booking' ),
			$appointment->service_name,
			$data['date_label'],
			substr( $data['time_label'], 0, 5 )
		);

		$subject = apply_filters( 'sbk_email_staff_subject', $subject, $data );
		$body    = self::render( 'staff-notification', $data );
		$body    = apply_filters( 'sbk_email_staff_body', $body, $data );

		// Replying to the notification should reach the customer.
		$headers = array( 'Reply-To: ' . $data['customer_name'] . ' <' . $data['customer']->email . '>' );

		return self::send( $recipients, $subject, $body, $data, $headers );
	}

	/**
	 * Render an email template.
	 *
	 * @param string $template Template file name without extension.
	 * @param array  $data     Prepared data, extracted for the view.
	 * @return string
	 */
	private static function render( $template, array $data ) {
		$file = SBK_PLUGIN_DIR . 'includes/emails/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		ob_start();
		include $file;

		return ob_get_clean();
	}

	/**
	 * Send one HTML email.
	 *
	 * @param string|string[] $to      Recipients.
	 * @param string          $subject Subject.
	 * @param string          $body    HTML body.
	 * @param array           $data    Prepared data.
	 * @param string[]        $headers Extra headers.
	 * @return bool
	 */
	private static function send( $to, $subject, $body, array $data, $headers = array() ) {
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$from_name = apply_filters( 'sbk_email_from_name', SBK_Settings::salon_name(), $data );

		$name = static function () use ( $from_name ) {
			return $from_name;
		};

		// Only the display name is changed: overriding the From address too
		// would break SPF/DKIM on most shared hosts.
		add_filter( 'wp_mail_from_name', $name );

		$html = static function () {
			return 'text/html';
		};

		add_filter( 'wp_mail_content_type', $html );

		$sent = wp_mail( $to, $subject, $body, $headers );

		remove_filter( 'wp_mail_content_type', $html );
		remove_filter( 'wp_mail_from_name', $name );

		if ( ! $sent ) {
			// A failed email must never look like a failed booking, so this is
			// logged rather than surfaced.
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'Simple Booking: could not send "%s" for appointment #%d.',
					$subject,
					(int) $data['appointment']->id
				)
			);
		}

		return $sent;
	}
}
