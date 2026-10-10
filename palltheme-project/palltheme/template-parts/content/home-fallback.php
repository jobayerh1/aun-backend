<?php
/**
 * Minimal homepage used only when Palltheme Core is not active.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="pt-hero pt-hero--compact">
	<div class="pt-hero__bg" aria-hidden="true"><span class="pt-hero__grid"></span><span class="pt-hero__orb"></span></div>
	<div class="pt-container pt-hero__inner">
		<div class="pt-hero__content">
			<p class="pt-eyebrow"><?php bloginfo( 'name' ); ?></p>
			<h1 class="pt-hero__title"><?php bloginfo( 'description' ); ?></h1>
			<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
				<p class="pt-hero__lead"><?php esc_html_e( 'Activate the Palltheme Core plugin and run Palltheme → Import Demo to build the full website.', 'palltheme' ); ?></p>
				<a class="pt-btn pt-btn--primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'palltheme' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
