<?php
/**
 * Customer confirmation email.
 *
 * Table layout with inline styles: email clients ignore most of a stylesheet.
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
	<title><?php echo esc_html( $data['salon_name'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:24px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;">

					<tr>
						<td style="background:#6c5ce7;padding:24px;text-align:center;">
							<div style="color:#ffffff;font-size:20px;font-weight:700;">
								<?php esc_html_e( 'Το ραντεβού σου επιβεβαιώθηκε', 'simple-booking' ); ?>
							</div>
							<div style="color:#e6e1ff;font-size:14px;margin-top:4px;">
								<?php echo esc_html( $data['salon_name'] ); ?>
							</div>
							<div style="color:#c7bdff;font-size:12px;margin-top:6px;">
								<?php
								printf(
									/* translators: %s: booking reference */
									esc_html__( 'Κράτηση #%s', 'simple-booking' ),
									esc_html( $data['reference'] )
								);
								?>
							</div>
						</td>
					</tr>

					<tr>
						<td style="padding:24px;">
							<p style="margin:0 0 16px;font-size:15px;color:#1d2327;">
								<?php
								printf(
									/* translators: %s: customer first name */
									esc_html__( 'Γεια σου %s, σε περιμένουμε!', 'simple-booking' ),
									esc_html( $data['customer']->first_name )
								);
								?>
							</p>

							<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
								style="background:#f0edff;border-radius:8px;padding:16px;margin-bottom:16px;">
								<tr>
									<td style="text-align:center;padding:16px;">
										<div style="font-size:18px;font-weight:700;color:#1d2327;">
											<?php echo esc_html( $data['date_label'] ); ?>
										</div>
										<div style="font-size:22px;font-weight:700;color:#6c5ce7;margin-top:4px;">
											<?php echo esc_html( $data['time_label'] ); ?>
										</div>
									</td>
								</tr>
							</table>

							<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Υπηρεσία', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
										<?php echo esc_html( $sbk_appointment->service_name ); ?>
										<span style="color:#6b7280;"><?php echo esc_html( ' · ' . $data['service_price'] ); ?></span>
									</td>
								</tr>

								<?php foreach ( $data['extras'] as $sbk_extra ) : ?>
									<tr>
										<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Extra', 'simple-booking' ); ?></td>
										<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
											<?php echo esc_html( $sbk_extra['name'] ); ?>
											<span style="color:#6b7280;"><?php echo esc_html( ' · ' . $sbk_extra['price'] ); ?></span>
										</td>
									</tr>
								<?php endforeach; ?>

								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Μαζί σου', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $sbk_appointment->staff_name ); ?></td>
								</tr>

								<tr>
									<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Διάρκεια', 'simple-booking' ); ?></td>
									<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $data['duration_label'] ); ?></td>
								</tr>

								<?php if ( '' !== $data['note'] ) : ?>
									<tr>
										<td <?php echo $sbk_label; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Σημείωσή σου', 'simple-booking' ); ?></td>
										<td <?php echo $sbk_value; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $data['note'] ); ?></td>
									</tr>
								<?php endif; ?>

								<tr>
									<td style="padding:14px 0 0;border-top:1px solid #e2e4e7;color:#1d2327;font-size:16px;font-weight:700;">
										<?php esc_html_e( 'Σύνολο', 'simple-booking' ); ?>
									</td>
									<td style="padding:14px 0 0;border-top:1px solid #e2e4e7;color:#1d2327;font-size:16px;font-weight:700;">
										<?php echo esc_html( $data['total_label'] ); ?>
									</td>
								</tr>
							</table>

							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 4px;">
								<tr>
									<td align="center">
										<a href="<?php echo esc_url( $data['manage_url'] ); ?>"
											style="display:inline-block;padding:14px 30px;border-radius:8px;background:#6c5ce7;color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;">
											<?php esc_html_e( 'Δες το ραντεβού σου', 'simple-booking' ); ?>
										</a>
									</td>
								</tr>
							</table>

							<p style="margin:20px 0 0;padding:12px 14px;background:#f6f7f8;border-radius:8px;font-size:14px;color:#3c434a;">
								<?php esc_html_e( 'Η πληρωμή γίνεται στο κατάστημα.', 'simple-booking' ); ?>
								<?php if ( '' !== $data['salon_phone'] ) : ?>
									<?php
									printf(
										/* translators: %s: salon phone number */
										esc_html__( 'Για αλλαγή ή ακύρωση τηλεφώνησέ μας στο %s.', 'simple-booking' ),
										esc_html( $data['salon_phone'] )
									);
									?>
								<?php endif; ?>
							</p>
						</td>
					</tr>

					<tr>
						<td style="padding:16px 24px 24px;text-align:center;color:#6b7280;font-size:13px;">
							<strong style="color:#1d2327;"><?php echo esc_html( $data['salon_name'] ); ?></strong><br>
							<?php if ( '' !== $data['salon_address'] ) : ?>
								<?php echo esc_html( $data['salon_address'] ); ?><br>
							<?php endif; ?>
							<?php if ( '' !== $data['salon_phone'] ) : ?>
								<?php echo esc_html( $data['salon_phone'] ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
