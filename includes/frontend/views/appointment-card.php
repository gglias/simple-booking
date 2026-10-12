<?php
/**
 * The customer's appointment card.
 *
 * @var array $data Prepared by SBK_Appointment_Page::render().
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

$sbk_appointment = $data['appointment'];
$sbk_status      = $sbk_appointment->status;
$sbk_label       = isset( $data['statuses'][ $sbk_status ] ) ? $data['statuses'][ $sbk_status ] : $sbk_status;
$sbk_customer    = $data['customer'];
$sbk_initials    = $sbk_customer
	? mb_substr( $sbk_customer->first_name, 0, 1 ) . mb_substr( (string) $sbk_customer->last_name, 0, 1 )
	: '';
$sbk_months      = array(
	__( 'Ιαν', 'simple-booking' ),
	__( 'Φεβ', 'simple-booking' ),
	__( 'Μαρ', 'simple-booking' ),
	__( 'Απρ', 'simple-booking' ),
	__( 'Μαΐ', 'simple-booking' ),
	__( 'Ιουν', 'simple-booking' ),
	__( 'Ιουλ', 'simple-booking' ),
	__( 'Αυγ', 'simple-booking' ),
	__( 'Σεπ', 'simple-booking' ),
	__( 'Οκτ', 'simple-booking' ),
	__( 'Νοε', 'simple-booking' ),
	__( 'Δεκ', 'simple-booking' ),
);
?>
<div class="sbk sbk-ticket">

	<header class="sbk-ticket__status sbk-ticket__status--<?php echo esc_attr( $sbk_status ); ?>">
		<span class="sbk-ticket__state">
			<span class="sbk-ticket__tick" aria-hidden="true"><?php echo 'cancelled' === $sbk_status ? '✕' : '✓'; ?></span>
			<?php echo esc_html( $sbk_label ); ?>
		</span>
		<span class="sbk-ticket__ref">
			<?php
			printf(
				/* translators: %s: booking reference */
				esc_html__( 'Κράτηση #%s', 'simple-booking' ),
				esc_html( $data['reference'] )
			);
			?>
		</span>
	</header>

	<div class="sbk-ticket__perf" aria-hidden="true"></div>

	<div class="sbk-ticket__body">

		<div class="sbk-ticket__head">
			<div class="sbk-ticket__date">
				<strong><?php echo esc_html( $data['start']->format( 'd' ) ); ?></strong>
				<span><?php echo esc_html( $sbk_months[ (int) $data['start']->format( 'n' ) - 1 ] ); ?></span>
			</div>

			<div class="sbk-ticket__title">
				<h2><?php echo esc_html( $sbk_appointment->service_name ); ?></h2>
				<p class="sbk-ticket__when">
					<?php
					echo esc_html(
						$data['start']->format( 'd' ) . ' ' .
						$sbk_months[ (int) $data['start']->format( 'n' ) - 1 ] . ', ' .
						$data['start']->format( 'Y, H:i' ) . ' – ' . $data['end']->format( 'H:i' )
					);
					?>
				</p>
			</div>
		</div>

		<div class="sbk-ticket__actions">
			<a class="sbk-chip" href="<?php echo esc_url( $data['ics_url'] ); ?>">
				<span aria-hidden="true">📅</span> <?php esc_html_e( 'Προσθήκη στο Ημερολόγιο', 'simple-booking' ); ?>
			</a>
			<button type="button" class="sbk-chip" onclick="window.print()">
				<span aria-hidden="true">🖨</span> <?php esc_html_e( 'Εκτύπωση', 'simple-booking' ); ?>
			</button>
		</div>

		<div class="sbk-ticket__cols">
			<section class="sbk-ticket__section">
				<h3><?php esc_html_e( 'Κουρέας', 'simple-booking' ); ?></h3>
				<div class="sbk-person">
					<?php if ( $data['staff'] && $data['staff']->avatar_id ) : ?>
						<?php echo wp_get_attachment_image( (int) $data['staff']->avatar_id, array( 40, 40 ), false, array( 'class' => 'sbk-person__avatar' ) ); ?>
					<?php else : ?>
						<span class="sbk-person__avatar sbk-person__avatar--initial"
							style="background:<?php echo esc_attr( $data['staff'] && $data['staff']->color ? $data['staff']->color : '#6c5ce7' ); ?>">
							<?php echo esc_html( mb_substr( $sbk_appointment->staff_name, 0, 1 ) ); ?>
						</span>
					<?php endif; ?>
					<span><?php echo esc_html( $sbk_appointment->staff_name ); ?></span>
				</div>
			</section>

			<?php if ( '' !== $data['address'] ) : ?>
				<section class="sbk-ticket__section">
					<h3><?php esc_html_e( 'Τοποθεσία', 'simple-booking' ); ?></h3>
					<div class="sbk-person">
						<span class="sbk-person__avatar sbk-person__avatar--initial" style="background:#e9f7ef;color:#0f9d58" aria-hidden="true">📍</span>
						<span>
							<a href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo rawurlencode( $data['address'] ); ?>"
								target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $data['salon_name'] ); ?>
							</a>
							<small><?php echo esc_html( $data['address'] ); ?></small>
						</span>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $sbk_customer ) : ?>
				<section class="sbk-ticket__section">
					<h3><?php esc_html_e( 'Πελάτης', 'simple-booking' ); ?></h3>
					<div class="sbk-person">
						<span class="sbk-person__avatar sbk-person__avatar--initial sbk-person__avatar--grey">
							<?php echo esc_html( mb_strtoupper( $sbk_initials ) ); ?>
						</span>
						<span>
							<?php echo esc_html( trim( $sbk_customer->first_name . ' ' . (string) $sbk_customer->last_name ) ); ?>
							<small><?php echo esc_html( $sbk_customer->email ); ?></small>
						</span>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<section class="sbk-ticket__section">
			<h3><?php esc_html_e( 'Ανάλυση Κόστους', 'simple-booking' ); ?></h3>

			<table class="sbk-breakdown">
				<tr>
					<td><?php echo esc_html( $sbk_appointment->service_name ); ?></td>
					<td><?php echo esc_html( SBK_Helpers::format_money( (int) $sbk_appointment->service_price_cents ) ); ?></td>
				</tr>
				<?php foreach ( $data['extras'] as $sbk_extra ) : ?>
					<tr>
						<td><?php echo esc_html( $sbk_extra->extra_name ); ?></td>
						<td><?php echo esc_html( SBK_Helpers::format_money( (int) $sbk_extra->price_cents ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr class="sbk-breakdown__total">
					<td><?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?></td>
					<td><?php echo esc_html( SBK_Helpers::format_money( (int) $sbk_appointment->total_price_cents ) ); ?></td>
				</tr>
			</table>

			<p class="sbk-ticket__pay"><?php esc_html_e( 'Η πληρωμή γίνεται στο κατάστημα.', 'simple-booking' ); ?></p>
		</section>

		<?php if ( '' !== $data['phone'] ) : ?>
			<p class="sbk-ticket__help">
				<?php esc_html_e( 'Για αλλαγή ή ακύρωση τηλεφώνησέ μας:', 'simple-booking' ); ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $data['phone'] ) ); ?>"><?php echo esc_html( $data['phone'] ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>
