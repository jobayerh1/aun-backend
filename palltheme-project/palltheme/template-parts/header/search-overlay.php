<?php
/**
 * Global search overlay with live (AJAX) results.
 * Without JavaScript the form still submits to the normal search page.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( ! palltheme_mod( 'header_search' ) ) {
	return;
}
?>
<div id="pt-search" class="pt-search" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search', 'palltheme' ); ?>" hidden>
	<div class="pt-search__backdrop" data-pt-search-close></div>
	<div class="pt-search__panel">
		<form role="search" method="get" class="pt-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php palltheme_the_icon( 'search', 'pt-search__glyph' ); ?>
			<label class="screen-reader-text" for="pt-search-input"><?php esc_html_e( 'Search the site', 'palltheme' ); ?></label>
			<input id="pt-search-input" class="pt-search__input" type="search" name="s" autocomplete="off"
				placeholder="<?php esc_attr_e( 'Search servers, switches, services, articles…', 'palltheme' ); ?>"
				aria-controls="pt-search-results" aria-describedby="pt-search-status" data-pt-search-input>
			<button type="button" class="pt-icon-btn" data-pt-search-close>
				<?php palltheme_the_icon( 'close' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close search', 'palltheme' ); ?></span>
			</button>
		</form>
		<p id="pt-search-status" class="pt-search__status" role="status" aria-live="polite"></p>
		<div id="pt-search-results" class="pt-search__results" data-pt-search-results></div>
		<?php
		$popular = palltheme_biz( 'search_suggestions' );
		if ( $popular ) :
			$terms = array_filter( array_map( 'trim', explode( ',', (string) $popular ) ) );
			?>
			<div class="pt-search__suggest" data-pt-search-suggest>
				<p class="pt-eyebrow"><?php esc_html_e( 'Popular searches', 'palltheme' ); ?></p>
				<ul class="pt-chips">
					<?php foreach ( $terms as $term ) : ?>
						<li><a class="pt-chip" href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $term ), home_url( '/' ) ) ); ?>"><?php echo esc_html( $term ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
