<?php
/**
 * Maintenance page (standalone, no theme dependency).
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

$pallcore_title = (string) pallcore_setting( 'maint_title' );
$pallcore_text  = (string) pallcore_setting( 'maint_text' );
$pallcore_until = (string) pallcore_setting( 'maint_until' );
$pallcore_logo  = (int) get_theme_mod( 'logo_light', 0 ) ?: (int) get_theme_mod( 'custom_logo', 0 );
$pallcore_email = (string) pallcore_setting( 'email' );
$pallcore_phone = (string) pallcore_setting( 'phone' );
$pallcore_primary = (string) get_theme_mod( 'color_primary', '#2567AD' );
$pallcore_accent  = (string) get_theme_mod( 'color_accent', '#00A8FF' );
$pallcore_dark    = (string) get_theme_mod( 'color_dark', '#07111F' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $pallcore_title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
<style>
	:root { --p: <?php echo esc_html( sanitize_hex_color( $pallcore_primary ) ?: '#2567AD' ); ?>; --a: <?php echo esc_html( sanitize_hex_color( $pallcore_accent ) ?: '#00A8FF' ); ?>; --d: <?php echo esc_html( sanitize_hex_color( $pallcore_dark ) ?: '#07111F' ); ?>; }
	* { box-sizing: border-box; }
	body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #dbe5f3; background: radial-gradient(900px 500px at 80% 0%, color-mix(in srgb, var(--p) 55%, transparent), transparent 65%), radial-gradient(700px 400px at 0% 100%, color-mix(in srgb, var(--a) 22%, transparent), transparent 65%), var(--d); }
	body::before { content: ""; position: fixed; inset: 0; pointer-events: none; opacity: .3; background-image: linear-gradient(rgba(148,190,255,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(148,190,255,.12) 1px, transparent 1px); background-size: 56px 56px; -webkit-mask-image: radial-gradient(ellipse at 50% 30%, #000 20%, transparent 75%); mask-image: radial-gradient(ellipse at 50% 30%, #000 20%, transparent 75%); }
	main { position: relative; width: min(680px, 100%); text-align: center; }
	.logo img { max-height: 48px; width: auto; margin: 0 auto 36px; display: block; }
	.logo span { display: inline-block; margin-bottom: 36px; font-weight: 800; font-size: 1.4rem; color: #fff; }
	.pill { display: inline-flex; gap: 8px; align-items: center; padding: 6px 14px; border-radius: 99px; border: 1px solid rgba(255,255,255,.14); background: rgba(255,255,255,.06); font-size: .85rem; }
	.pill i { width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.25); }
	h1 { margin: 22px 0 14px; color: #fff; font-size: clamp(2rem, 6vw, 3.2rem); line-height: 1.1; letter-spacing: -.03em; }
	p { margin: 0 auto 32px; max-width: 520px; color: rgba(219,229,243,.78); font-size: 1.1rem; }
	.cd { display: flex; justify-content: center; gap: 12px; margin-bottom: 36px; }
	.cd div { min-width: 82px; padding: 16px 10px; border-radius: 16px; background: rgba(12,24,41,.7); border: 1px solid rgba(255,255,255,.12); }
	.cd b { display: block; color: #fff; font-size: 1.9rem; line-height: 1; }
	.cd small { font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(219,229,243,.6); }
	.contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
	.contact a { padding: 10px 18px; border-radius: 10px; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,.2); }
	.contact a:hover { border-color: var(--a); }
	@media (prefers-reduced-motion: no-preference) { h1, p, .cd { animation: up .8s cubic-bezier(.2,.7,.2,1) both; } p { animation-delay: .1s; } .cd { animation-delay: .2s; } @keyframes up { from { opacity: 0; transform: translateY(14px); } } }
</style>
</head>
<body>
<main>
	<div class="logo">
		<?php if ( $pallcore_logo ) : ?>
			<?php echo wp_get_attachment_image( $pallcore_logo, 'medium', false, array( 'alt' => esc_attr( get_bloginfo( 'name' ) ) ) ); ?>
		<?php else : ?>
			<span><?php bloginfo( 'name' ); ?></span>
		<?php endif; ?>
	</div>
	<span class="pill"><i></i><?php esc_html_e( 'Scheduled maintenance', 'palltheme-core' ); ?></span>
	<h1><?php echo esc_html( $pallcore_title ); ?></h1>
	<p><?php echo esc_html( $pallcore_text ); ?></p>
	<?php if ( $pallcore_until ) : ?>
		<div class="cd" data-until="<?php echo esc_attr( $pallcore_until ); ?>" aria-live="polite">
			<div><b data-d>--</b><small><?php esc_html_e( 'Days', 'palltheme-core' ); ?></small></div>
			<div><b data-h>--</b><small><?php esc_html_e( 'Hours', 'palltheme-core' ); ?></small></div>
			<div><b data-m>--</b><small><?php esc_html_e( 'Minutes', 'palltheme-core' ); ?></small></div>
			<div><b data-s>--</b><small><?php esc_html_e( 'Seconds', 'palltheme-core' ); ?></small></div>
		</div>
		<script>
		(function(){var el=document.querySelector('.cd'),t=new Date(el.getAttribute('data-until')).getTime();if(isNaN(t)){el.remove();return;}function p(n){return String(n).padStart(2,'0');}function tick(){var d=Math.max(0,t-Date.now())/1000;el.querySelector('[data-d]').textContent=p(Math.floor(d/86400));el.querySelector('[data-h]').textContent=p(Math.floor(d%86400/3600));el.querySelector('[data-m]').textContent=p(Math.floor(d%3600/60));el.querySelector('[data-s]').textContent=p(Math.floor(d%60));}tick();setInterval(tick,1000);})();
		</script>
	<?php endif; ?>
	<div class="contact">
		<?php if ( $pallcore_email ) : ?>
			<a href="mailto:<?php echo esc_attr( antispambot( $pallcore_email ) ); ?>"><?php echo esc_html( antispambot( $pallcore_email ) ); ?></a>
		<?php endif; ?>
		<?php if ( $pallcore_phone ) : ?>
			<a href="tel:<?php echo esc_attr( pallcore_phone_digits( $pallcore_phone ) ); ?>"><?php echo esc_html( $pallcore_phone ); ?></a>
		<?php endif; ?>
		<?php foreach ( array( 'linkedin', 'facebook', 'x', 'youtube', 'instagram' ) as $pallcore_net ) : ?>
			<?php $pallcore_url = (string) pallcore_setting( 'social_' . $pallcore_net ); ?>
			<?php if ( $pallcore_url ) : ?>
				<a href="<?php echo esc_url( $pallcore_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( 'x' === $pallcore_net ? 'X' : ucfirst( $pallcore_net ) ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</main>
</body>
</html>
