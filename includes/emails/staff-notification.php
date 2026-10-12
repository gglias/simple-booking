<?php
/**
 * Staff / salon notification email.
 *
 * Optimised for a phone glance: who, when, and how to reach them.
 *
 * @var array $data Prepared email data.
 *
 * @package SimpleBooking
 */

defined( 'ABSPATH' ) || exit;

$sbk_appointment = $data['appointment'];
$sbk_label       = 'style="padding:8px 0;color:#6b7280;font-size:14px;width:130px;vertical-align:top"';
$sbk_value       = 'style="padding:8px 0;color:#1d2327;font-size:15px;vertical-align:top"';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Νέο ραντεβού', 'simple-booking' ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:24px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;">

					<tr>
						<td style="background:#1d2327;padding:20px 24px;">
							<div style="color:#ffffff;font-size:18px;font-weight:700;">
								<?php esc_html_e( 'Νέο ραντεβού', 'simple-booking' ); ?>
							</div>
							<div style="color:#b6bcc4;font-size:13px;margin-top:2px;">
								<?php echo esc_html( $data['salon_name'] ); ?>
							</div>
							<div style="color:#b6bcc4;font-size:14px;margin-top:2px;">
								<?php echo esc_html( $data['date_label'] . ' · ' . $data['time_label'] ); ?>
							</div>
						</td>
					</tr>

					<tr>
						<td style="padding:20px 24px;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Κουρέας', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>><strong><?php echo esc_html( $sbk_appointment->staff_name ); ?></strong></td>
								</tr>
								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
										<?php echo esc_html( $sbk_appointment->service_name ); ?>
										<span style="color:#6b7280;"><?php echo esc_html( ' · ' . $data['duration_label'] ); ?></span>
									</td>
								</tr>

								<?php foreach ( $data['extras'] as $sbk_extra ) : ?>
									<tr>
										<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Extra', 'simple-booking' ); ?></td>
										<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $sbk_extra['name'] ); ?></td>
									</tr>
								<?php endforeach; ?>

								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Πελάτης', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
										<strong><?php echo esc_html( $data['customer_name'] ); ?></strong><br>
										<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $data['customer']->phone ) ); ?>"
											style="color:#6c5ce7;text-decoration:none;"><?php echo esc_html( $data['customer']->phone ); ?></a><br>
										<a href="mailto:<?php echo esc_attr( $data['customer']->email ); ?>"
											style="color:#6c5ce7;text-decoration:none;"><?php echo esc_html( $data['customer']->email ); ?></a>
									</td>
								</tr>

								<?php if ( '' !== $data['note'] ) : ?>
									<tr>
										<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Σημείωση', 'simple-booking' ); ?></td>
										<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $data['note'] ); ?></td>
									</tr>
								<?php endif; ?>

								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
										<strong><?php echo esc_html( $data['total_label'] ); ?></strong>
										<span style="color:#6b7280;"><?php esc_html_e( '(πληρωμή στο κατάστημα)', 'simple-booking' ); ?></span>
									</td>
								</tr>
							</table>

							<p style="margin:18px 0 0;font-size:13px;color:#6b7280;">
								<?php
								printf(
									/* translators: %d: appointment id */
									esc_html__( 'Κράτηση #%d — απάντησε σε αυτό το email για να επικοινωνήσεις με τον πελάτη.', 'simple-booking' ),
									(int) $sbk_appointment->id
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
