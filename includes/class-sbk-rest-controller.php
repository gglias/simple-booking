<?php
/**
 * REST API for the booking flow.
 *
 * Every endpoint is public, because guest booking is the point. The write
 * endpoint is protected by a REST nonce, a honeypot field and per-IP rate
 * limiting rather than by authentication.
 *
 * Money crosses the wire twice: as integer cents for any logic, and as a
 * preformatted string for display, so the frontend never does float maths.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers simple-booking/v1 routes.
 */
class SBK_Rest_Controller {

	const NAMESPACE_V1 = 'simple-booking/v1';

	/**
	 * Bookings allowed per IP per hour.
	 */
	const RATE_LIMIT = 5;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Route table.
	 *
	 * @return void
	 */
	public static function register_routes() {
		$public = '__return_true';

		register_rest_route(
			self::NAMESPACE_V1,
			'/session',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_session' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/staff',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_staff' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_services' ),
				'permission_callback' => $public,
				'args'                => array(
					'staff_id' => self::arg_id( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/extras',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_extras' ),
				'permission_callback' => $public,
				'args'                => array(
					'service_id' => self::arg_id( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/days',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_days' ),
				'permission_callback' => $public,
				'args'                => array(
					'staff_id'   => self::arg_id( true ),
					'service_id' => self::arg_id( true ),
					'from'       => self::arg_date( true ),
					'to'         => self::arg_date( true ),
					'extras'     => self::arg_extras(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/slots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_slots' ),
				'permission_callback' => $public,
				'args'                => array(
					'staff_id'   => self::arg_id( true ),
					'service_id' => self::arg_id( true ),
					'date'       => self::arg_date( true ),
					'extras'     => self::arg_extras(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/appointments',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_appointment' ),
				'permission_callback' => array( __CLASS__, 'can_create' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/appointments/(?P<token>[A-Za-z0-9]{32})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_appointment' ),
				'permission_callback' => $public,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Argument schemas
	// -------------------------------------------------------------------------

	/**
	 * A positive integer id argument.
	 *
	 * @param bool $required Whether the argument is required.
	 * @return array
	 */
	private static function arg_id( $required = false ) {
		return array(
			'required'          => $required,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				return (int) $value > 0;
			},
		);
	}

	/**
	 * A Y-m-d date argument.
	 *
	 * @param bool $required Whether the argument is required.
	 * @return array
	 */
	private static function arg_date( $required = false ) {
		return array(
			'required'          => $required,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function ( $value ) {
				return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value );
			},
		);
	}

	/**
	 * A list of extra ids, accepted as an array or a comma separated string.
	 *
	 * @return array
	 */
	private static function arg_extras() {
		return array(
			'required'          => false,
			'default'           => array(),
			'sanitize_callback' => static function ( $value ) {
				if ( is_string( $value ) ) {
					$value = '' === $value ? array() : explode( ',', $value );
				}

				return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
			},
		);
	}

	// -------------------------------------------------------------------------
	// Read endpoints
	// -------------------------------------------------------------------------

	/**
	 * A fresh REST nonce, so a page served from cache can still submit.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_session() {
		return rest_ensure_response(
			array(
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'today'    => ( new DateTime( 'now', SBK_Helpers::salon_timezone() ) )->format( 'Y-m-d' ),
				'timezone' => SBK_TIMEZONE,
				'settings' => array(
					'max_advance_days' => SBK_Settings::get( 'max_advance_days' ),
					'lead_time_min'    => SBK_Settings::get( 'lead_time_min' ),
				),
			)
		);
	}

	/**
	 * Step 1: bookable staff.
	 *
	 * Staff with no active service assigned are left out: picking them would
	 * dead-end the flow at step 2.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_staff() {
		$active_service_ids = wp_list_pluck( SBK_Service_Repository::get_all( array( 'only_active' => true ) ), 'id' );
		$active_service_ids = array_map( 'intval', $active_service_ids );
		$out                = array();

		foreach ( SBK_Staff_Repository::get_all( array( 'only_active' => true ) ) as $member ) {
			$assigned = array_keys( SBK_Staff_Repository::get_services_map( (int) $member->id ) );

			if ( ! array_intersect( $assigned, $active_service_ids ) ) {
				continue;
			}

			$out[] = array(
				'id'     => (int) $member->id,
				'name'   => $member->display_name,
				'bio'    => (string) $member->bio,
				'color'  => $member->color ? $member->color : null,
				'avatar' => $member->avatar_id ? wp_get_attachment_image_url( (int) $member->avatar_id, 'thumbnail' ) : null,
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Step 2: services a staff member performs, with their own price and time.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_services( WP_REST_Request $request ) {
		$staff_id = (int) $request->get_param( 'staff_id' );
		$staff    = SBK_Staff_Repository::find( $staff_id );

		if ( ! $staff || ! $staff->is_active ) {
			return new WP_Error( 'sbk_staff_missing', __( 'Το άτομο δεν είναι διαθέσιμο.', 'simple-booking' ), array( 'status' => 404 ) );
		}

		$map = SBK_Staff_Repository::get_services_map( $staff_id );
		$out = array();

		foreach ( SBK_Service_Repository::get_all( array( 'only_active' => true ) ) as $service ) {
			$id = (int) $service->id;

			if ( ! isset( $map[ $id ] ) ) {
				continue;
			}

			$duration = null === $map[ $id ]['duration_min'] ? (int) $service->duration_min : (int) $map[ $id ]['duration_min'];
			$price    = null === $map[ $id ]['price_cents'] ? (int) $service->price_cents : (int) $map[ $id ]['price_cents'];

			$out[] = array(
				'id'             => $id,
				'name'           => $service->name,
				'description'    => (string) $service->description,
				'category'       => $service->category ? $service->category : null,
				'duration_min'   => $duration,
				'duration_label' => SBK_Helpers::format_duration( $duration ),
				'price_cents'    => $price,
				'price_label'    => SBK_Helpers::format_money( $price ),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Step 3: optional extras for a service.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_extras( WP_REST_Request $request ) {
		$out = array();

		foreach ( SBK_Extra_Repository::get_for_service( (int) $request->get_param( 'service_id' ) ) as $extra ) {
			$out[] = array(
				'id'             => (int) $extra->id,
				'name'           => $extra->name,
				'description'    => (string) $extra->description,
				'duration_min'   => (int) $extra->duration_min,
				'duration_label' => SBK_Helpers::format_duration( (int) $extra->duration_min ),
				'price_cents'    => (int) $extra->price_cents,
				'price_label'    => SBK_Helpers::format_money( (int) $extra->price_cents ),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Step 4a: which dates have availability, for the calendar.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_days( WP_REST_Request $request ) {
		$from = $request->get_param( 'from' );
		$to   = $request->get_param( 'to' );

		// Keep the window bounded: this walks a day at a time.
		if ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS > 62 ) {
			return new WP_Error( 'sbk_range_too_wide', __( 'Το εύρος δεν μπορεί να ξεπερνά τους δύο μήνες.', 'simple-booking' ), array( 'status' => 400 ) );
		}

		$days = SBK_Availability::get_days(
			(int) $request->get_param( 'staff_id' ),
			(int) $request->get_param( 'service_id' ),
			$from,
			$to,
			(array) $request->get_param( 'extras' )
		);

		if ( is_wp_error( $days ) ) {
			return self::error_response( $days );
		}

		$out = array();

		foreach ( $days as $date => $count ) {
			$out[] = array(
				'date'      => $date,
				'available' => $count > 0,
				'slots'     => $count,
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Step 4b: bookable times on one date.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_slots( WP_REST_Request $request ) {
		$slots = SBK_Availability::get_slots(
			(int) $request->get_param( 'staff_id' ),
			(int) $request->get_param( 'service_id' ),
			$request->get_param( 'date' ),
			(array) $request->get_param( 'extras' )
		);

		if ( is_wp_error( $slots ) ) {
			return self::error_response( $slots );
		}

		$out = array();

		foreach ( $slots as $slot ) {
			$out[] = array(
				'time'      => $slot['time'],
				'start_utc' => $slot['start_utc'],
				'end_utc'   => $slot['end_utc'],
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Step 7 result: look a booking up by its manage token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_appointment( WP_REST_Request $request ) {
		$appointment = SBK_Appointment_Repository::find_by_token( $request->get_param( 'token' ) );

		if ( ! $appointment ) {
			return new WP_Error( 'sbk_not_found', __( 'Το ραντεβού δεν βρέθηκε.', 'simple-booking' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			self::shape_appointment(
				$appointment,
				SBK_Appointment_Repository::get_extras( (int) $appointment->id ),
				SBK_Customer_Repository::find( (int) $appointment->customer_id )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Write endpoint
	// -------------------------------------------------------------------------

	/**
	 * Guard the write endpoint: nonce, honeypot and rate limit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function can_create( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'sbk_bad_nonce',
				__( 'Η σελίδα έληξε. Ανανέωσε και δοκίμασε ξανά.', 'simple-booking' ),
				array( 'status' => 403 )
			);
		}

		// Hidden field: a real customer never fills this in.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return new WP_Error( 'sbk_spam', __( 'Η υποβολή απορρίφθηκε.', 'simple-booking' ), array( 'status' => 400 ) );
		}

		if ( ! self::within_rate_limit() ) {
			return new WP_Error(
				'sbk_rate_limited',
				__( 'Πολλές κρατήσεις σε σύντομο διάστημα. Δοκίμασε αργότερα ή τηλεφώνησέ μας.', 'simple-booking' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Step 7: create the appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_appointment( WP_REST_Request $request ) {
		$customer = (array) $request->get_param( 'customer' );

		$result = SBK_Booking_Service::create(
			array(
				'staff_id'       => (int) $request->get_param( 'staff_id' ),
				'service_id'     => (int) $request->get_param( 'service_id' ),
				'date'           => (string) $request->get_param( 'date' ),
				'time'           => (string) $request->get_param( 'time' ),
				'extras'         => array_map( 'absint', (array) $request->get_param( 'extras' ) ),
				'customer'       => $customer,
				'note'           => (string) $request->get_param( 'note' ),
				'create_account' => (bool) $request->get_param( 'create_account' ),
				'created_via'    => 'frontend',
			)
		);

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		self::record_submission();

		$response = self::shape_appointment( $result['appointment'], $result['extras'], $result['customer'] );

		$response['account_created'] = ! empty( $result['account_user'] );
		$response['account_error']   = $result['account_error'];
		$response['manage_token']    = $result['appointment']->manage_token;

		return new WP_REST_Response( $response, 201 );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Shape an appointment for the API, in salon local time.
	 *
	 * @param object     $appointment Appointment row.
	 * @param array      $extras      Extra rows.
	 * @param object|null $customer   Customer row.
	 * @return array
	 */
	private static function shape_appointment( $appointment, $extras, $customer ) {
		$tz    = SBK_Helpers::salon_timezone();
		$start = new DateTime( $appointment->start_utc, new DateTimeZone( 'UTC' ) );
		$end   = new DateTime( $appointment->end_utc, new DateTimeZone( 'UTC' ) );

		$start->setTimezone( $tz );
		$end->setTimezone( $tz );

		$extra_lines = array();

		foreach ( $extras as $extra ) {
			$extra_lines[] = array(
				'name'        => $extra->extra_name,
				'price_cents' => (int) $extra->price_cents,
				'price_label' => SBK_Helpers::format_money( (int) $extra->price_cents ),
			);
		}

		return array(
			'id'             => (int) $appointment->id,
			'status'         => $appointment->status,
			'date'           => $start->format( 'Y-m-d' ),
			'date_label'     => SBK_Helpers::format_date( $start->format( 'Y-m-d' ) ),
			'time'           => $start->format( 'H:i' ),
			'end_time'       => $end->format( 'H:i' ),
			'start_utc'      => $appointment->start_utc,
			'staff_name'     => $appointment->staff_name,
			'service_name'   => $appointment->service_name,
			'duration_min'   => (int) $appointment->duration_min,
			'duration_label' => SBK_Helpers::format_duration( (int) $appointment->duration_min ),
			'extras'         => $extra_lines,
			'total_cents'    => (int) $appointment->total_price_cents,
			'total_label'    => SBK_Helpers::format_money( (int) $appointment->total_price_cents ),
			'note'           => (string) $appointment->customer_note,
			'customer'       => $customer ? array(
				'first_name' => $customer->first_name,
				'last_name'  => (string) $customer->last_name,
				'email'      => $customer->email,
				'phone'      => $customer->phone,
			) : null,
		);
	}

	/**
	 * Turn a domain WP_Error into one carrying an HTTP status.
	 *
	 * @param WP_Error $error Domain error.
	 * @return WP_Error
	 */
	private static function error_response( WP_Error $error ) {
		$statuses = array(
			'sbk_slot_taken'    => 409,
			'sbk_busy'          => 409,
			'sbk_no_slots'      => 409,
			'sbk_staff_missing' => 404,
			'sbk_service_missing' => 404,
			'sbk_db_error'      => 500,
		);

		$code   = $error->get_error_code();
		$status = isset( $statuses[ $code ] ) ? $statuses[ $code ] : 400;

		return new WP_Error( $code, $error->get_error_message(), array( 'status' => $status ) );
	}

	/**
	 * Key for the per-IP rate limit counter.
	 *
	 * @return string
	 */
	private static function rate_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		return 'sbk_rate_' . md5( $ip );
	}

	/**
	 * Whether this IP may still book.
	 *
	 * @return bool
	 */
	private static function within_rate_limit() {
		/**
		 * Filter the number of bookings allowed per IP per hour.
		 *
		 * @param int $limit Bookings per hour.
		 */
		$limit = (int) apply_filters( 'sbk_rate_limit', self::RATE_LIMIT );

		if ( $limit <= 0 ) {
			return true;
		}

		return (int) get_transient( self::rate_key() ) < $limit;
	}

	/**
	 * Count a successful submission against the limit.
	 *
	 * Only successes count, so a customer fixing a typo is never locked out.
	 *
	 * @return void
	 */
	private static function record_submission() {
		$key   = self::rate_key();
		$count = (int) get_transient( $key );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}
}
