<?php
/**
 * Email footer, Breo version of WooCommerce's emails/email-footer.php (10.4.0).
 * Closes the body, adds a "Need help?" card (customer emails only) and the
 * Breo footer. WooCommerce's footer text is shown only if it was changed from
 * the stock "Built with WooCommerce" line.
 */

defined( 'ABSPATH' ) || exit;

$email      = $email ?? ( isset( $GLOBALS['breo_email_current'] ) ? $GLOBALS['breo_email_current'] : null );
$breo_font  = breo_bd_email_font();
$breo_cust  = ! $email instanceof WC_Email || $email->is_customer_email();
$breo_wa    = breo_bd_whatsapp_url( 'Hi Breo Bangladesh, I have a question about my order' );
$breo_phone = breo_bd_opt( 'phone' );
$breo_mail  = breo_bd_opt( 'email' ) ? breo_bd_opt( 'email' ) : get_option( 'woocommerce_email_from_address' );
$breo_links = array();
foreach ( array( 'track-order' => 'Track an order', 'warranty-policy' => 'Warranty', 'returns-refunds' => 'Returns', 'contact' => 'Contact' ) as $breo_slug => $breo_label ) {
	$breo_p = get_page_by_path( $breo_slug );
	if ( $breo_p && 'publish' === $breo_p->post_status ) {
		$breo_links[] = '<a href="' . esc_url( get_permalink( $breo_p ) ) . '" target="_blank" style="color:#6f6a63;text-decoration:underline;">' . esc_html( $breo_label ) . '</a>';
	}
}
$breo_footer = trim( (string) get_option( 'woocommerce_email_footer_text' ) );
// WooCommerce's stock footer (site title + store address, or "Built with WooCommerce")
// repeats the Breo footer below, so only a footer text the owner wrote is shown.
if ( false !== stripos( $breo_footer, 'woocommerce' ) || '{site_title}{store_address}' === preg_replace( '~<br\s*/?>|\s+~i', '', $breo_footer ) ) {
	$breo_footer = '';
}
?>
																		</div>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<?php if ( $breo_cust && ( $breo_wa || $breo_phone || $breo_mail ) ) : ?>
											<tr>
												<td style="padding:0 32px 32px;">
													<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f7f2eb" style="width:100%;background:#f7f2eb;border-radius:16px;">
														<tr>
															<td style="padding:22px 24px;font-family:<?php echo esc_attr( $breo_font ); ?>;">
																<p style="margin:0 0 4px;font-size:16px;line-height:1.4;font-weight:700;color:#1c1c1c;">Need help with your order?</p>
																<p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#6f6a63;">Real people in Bangladesh, happy to help<?php echo breo_bd_opt( 'hours' ) ? ' (' . esc_html( breo_bd_opt( 'hours' ) ) . ')' : ''; ?>.</p>
																<?php if ( $breo_wa ) : ?>
																	<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px;"><tr>
																		<td bgcolor="#1faa53" style="padding:0;border-radius:999px;background:#1faa53;">
																			<a href="<?php echo esc_url( $breo_wa ); ?>" target="_blank" style="display:inline-block;padding:11px 22px;border-radius:999px;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:14px;line-height:1;font-weight:700;color:#ffffff;text-decoration:none;">Chat on WhatsApp</a>
																		</td>
																	</tr></table>
																<?php endif; ?>
																<p style="margin:0;font-size:14px;line-height:1.7;color:#3b3833;">
																	<?php if ( $breo_phone ) : ?>
																		Call <a href="<?php echo esc_url( breo_bd_tel( $breo_phone ) ); ?>" style="color:#1c1c1c;font-weight:700;text-decoration:none;"><?php echo esc_html( $breo_phone ); ?></a><br>
																	<?php endif; ?>
																	<?php if ( $breo_mail ) : ?>
																		Email <a href="mailto:<?php echo esc_attr( $breo_mail ); ?>" style="color:#1c1c1c;font-weight:700;text-decoration:none;"><?php echo esc_html( $breo_mail ); ?></a>, or just reply to this email.
																	<?php endif; ?>
																</p>
															</td>
														</tr>
													</table>
												</td>
											</tr>
										<?php endif; ?>
									</table>
								</td>
							</tr>
							<tr>
								<td align="center" valign="top" style="padding:22px 24px 6px;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:12px;line-height:1.7;color:#8a857d;text-align:center;">
									<?php if ( $breo_links ) : ?>
										<p style="margin:0 0 8px;"><?php echo implode( ' &nbsp;·&nbsp; ', $breo_links ); // phpcs:ignore ?></p>
									<?php endif; ?>
									<p style="margin:0;"><?php echo esc_html( breo_bd_company() ); ?> · Authorized distributor of Breo in Bangladesh</p>
									<?php if ( breo_bd_opt( 'address' ) ) : ?>
										<p style="margin:0;"><?php echo esc_html( breo_bd_opt( 'address' ) ); ?></p>
									<?php endif; ?>
									<?php if ( '' !== trim( $breo_footer ) && false === stripos( $breo_footer, 'woocommerce' ) ) : ?>
										<div style="margin-top:8px;">
											<?php
											// WooCommerce replaces {site_title} etc. on this filter.
											echo wp_kses_post( wpautop( wptexturize( apply_filters( 'woocommerce_email_footer_text', $breo_footer, $email ) ) ) );
											?>
										</div>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</td>
				<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
			</tr>
		</table>
	</body>
</html>
