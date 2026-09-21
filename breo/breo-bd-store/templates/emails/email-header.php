<?php
/**
 * Email header, Breo version of WooCommerce's emails/email-header.php (10.7.0).
 * Keeps WooCommerce's ids (#wrapper, #template_container, #body_content…) so its
 * inlined CSS and any plugin styling still apply. Adds the dark brand band and an
 * inbox preview line. An empty $email_heading skips the heading row (the Breo
 * customer order template draws its own headline).
 */

defined( 'ABSPATH' ) || exit;

$store_name    = $store_name ?? get_bloginfo( 'name', 'display' );
$email_heading = $email_heading ?? '';
$breo_email    = isset( $GLOBALS['breo_email_current'] ) ? $GLOBALS['breo_email_current'] : null;
$breo_logo     = breo_bd_email_logo_url();
$breo_font     = breo_bd_email_font();
$breo_pre      = '';
if ( $breo_email instanceof WC_Email && $breo_email->object instanceof WC_Order && $breo_email->is_customer_email() ) {
	$breo_copy = breo_bd_email_copy( $breo_email, $breo_email->object );
	$breo_pre  = $breo_copy['text'] ? $breo_copy['text'] : $breo_copy['title'];
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<meta name="color-scheme" content="light only">
		<meta name="supported-color-schemes" content="light">
		<title><?php echo esc_html( $store_name ); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
		<?php if ( $breo_pre ) : ?>
			<div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all;font-size:1px;line-height:1px;color:#f3eee7;"><?php echo esc_html( $breo_pre ); ?></div>
		<?php endif; ?>
		<table width="100%" id="outer_wrapper" role="presentation">
			<tr>
				<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
				<td width="600" style="width:100%;max-width:600px;"><?php // width attr for Outlook; the style keeps it fluid on phones ?>
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
						<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="inner_wrapper" role="presentation">
							<tr>
								<td align="center" valign="top" style="padding:0;">
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" role="presentation">
										<tr>
											<td align="center" valign="middle" bgcolor="#1c1c1c" style="padding:24px 32px;background:#1c1c1c;">
												<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="text-decoration:none;display:inline-block;">
													<?php if ( $breo_logo ) : ?>
														<img src="<?php echo esc_url( $breo_logo ); ?>" width="88" alt="Breo" style="width:88px;height:auto;margin:0;display:inline-block;vertical-align:middle;border:0;" />
													<?php else : ?>
														<span style="font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:28px;font-weight:700;letter-spacing:-1px;color:#ffffff;vertical-align:middle;">breo</span>
													<?php endif; ?>
													<span style="display:inline-block;margin-left:12px;padding-left:12px;border-left:1px solid #4a4744;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:10px;line-height:14px;letter-spacing:3px;color:#d9d2c8;vertical-align:middle;">BANGLADESH</span>
												</a>
											</td>
										</tr>
										<?php if ( '' !== trim( (string) $email_heading ) ) : ?>
											<tr>
												<td align="center" valign="top" style="padding:0;">
													<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" role="presentation">
														<tr>
															<td id="header_wrapper">
																<h1><?php echo esc_html( $email_heading ); ?></h1>
															</td>
														</tr>
													</table>
												</td>
											</tr>
										<?php endif; ?>
										<tr>
											<td align="center" valign="top" style="padding:0;">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body" role="presentation">
													<tr>
														<td valign="top" id="body_content">
															<table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
																<tr>
																	<td valign="top" id="body_content_inner_cell">
																		<div id="body_content_inner">
