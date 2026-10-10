<?php
/**
 * Header.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;
$phone = (string) dserver_opt( 'phone' );
$email = (string) dserver_opt( 'email' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="ds-skip" href="#ds-main"><?php esc_html_e( 'Skip to content', 'dserver' ); ?></a>

<?php if ( dserver_opt( 'topbar' ) ) : ?>
<div class="ds-topbar">
	<div class="ds-container ds-topbar__inner">
		<ul class="ds-topbar__info">
			<?php if ( $phone ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>"><?php echo dserver_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $phone ); ?></a></li><?php endif; ?>
			<?php if ( $email ) : ?><li><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo dserver_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( antispambot( $email ) ); ?></a></li><?php endif; ?>
			<li class="ds-topbar__hours"><?php echo dserver_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( (string) dserver_opt( 'hours' ) ); ?></li>
		</ul>
		<span class="ds-status"><span class="ds-status__dot" aria-hidden="true"></span><?php echo esc_html( (string) dserver_opt( 'status_text' ) ); ?></span>
	</div>
</div>
<?php endif; ?>

<header class="ds-header" data-ds-header>
	<div class="ds-container ds-header__inner">
		<div class="ds-header__brand"><?php dserver_logo(); ?></div>
		<nav class="ds-nav" id="ds-nav" aria-label="<?php esc_attr_e( 'Main menu', 'dserver' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'ds-menu',
					'fallback_cb'    => false,
					'depth'          => 2,
				)
			);
			?>
			<a class="ds-button ds-nav__cta-mobile" href="<?php echo esc_url( dserver_link( (string) dserver_opt( 'cta_url' ) ) ); ?>"><?php echo esc_html( (string) dserver_opt( 'cta_text' ) ); ?></a>
		</nav>
		<div class="ds-header__actions">
			<?php if ( dserver_opt( 'cta_text' ) ) : ?>
				<a class="ds-button ds-header__cta" href="<?php echo esc_url( dserver_link( (string) dserver_opt( 'cta_url' ) ) ); ?>"><?php echo esc_html( (string) dserver_opt( 'cta_text' ) ); ?></a>
			<?php endif; ?>
			<button class="ds-burger" type="button" aria-controls="ds-nav" aria-expanded="false" data-ds-burger>
				<span class="ds-burger__open"><?php echo dserver_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="ds-burger__close"><?php echo dserver_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'dserver' ); ?></span>
			</button>
		</div>
	</div>
</header>
<main id="ds-main" class="ds-main">
