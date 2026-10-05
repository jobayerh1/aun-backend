"""Copy the BD tool plugins into aunstore-core/includes/tools/ with the aunstore patches.

Re-run after any BD plugin update; every patch point is asserted, so a changed
BD file fails loudly instead of being ported half-patched.
"""
import pathlib

WORK = pathlib.Path(__file__).resolve().parents[2]
DST = pathlib.Path(__file__).resolve().parents[1] / "aunstore-core" / "includes" / "tools"
DST.mkdir(parents=True, exist_ok=True)


def port(src, dst, patches):
    s = (WORK / src).read_text(encoding="utf-8").replace("\r\n", "\n")
    s = s.replace(" * Plugin Name: ", " * Module (bundled in AUN Store Core): ", 1).replace("Smart Living Bangladesh", "AUN")
    for old, new in patches:
        n = s.count(old)
        assert n == 1, f"{src}: patch point found {n}x: {old[:70]!r}"
        s = s.replace(old, new)
    (DST / dst).write_text(s, encoding="utf-8", newline="\n")
    print("ported", dst)


# Compare: read the aunstore Specifications tab first; drop the plugin-list action link
# (plugin_basename(__FILE__) would point at this bundled file, not a plugin).
port("aun-compare-plugin.php", "compare.php", [
    (r"""        \add_filter( 'plugin_action_links_' . \plugin_basename( __FILE__ ), [__CLASS__, 'action_links'] );
""", ""),
    (r"""    private static function get_custom_tab_spec_content( $product_id ) {
        $raw = \get_post_meta( $product_id, 'wb_custom_tabs', true );""",
     r"""    private static function get_custom_tab_spec_content( $product_id ) {
        // aunstore.com: the spec sheet lives in the Specifications tab meta (AUN Store Core).
        $own = \get_post_meta( $product_id, '_aunstore_specs', true );
        if ( \is_string( $own ) && \stripos( $own, '<table' ) !== false ) return $own;

        $raw = \get_post_meta( $product_id, 'wb_custom_tabs', true );"""),
    (r"""\esc_url( \home_url( '/projector-price/' ) )""", r"""\esc_url( \aunstore_projectors_url() )"""),
])

# Throw calculator: no BD ERP link field (that belongs to the BD app); accept
# product="<id|sku|slug>" so the calculator can live on a normal page too.
port("aun-throw-distance-calculator.php", "throw-calculator.php", [
    (r"""        woocommerce_wp_text_input( [
            'id'                => '_aun_erp_product_id',
            'label'             => 'ERP product ID',
            'placeholder'       => 'e.g. 142',
            'description'       => 'UltimatePOS product ID for this model. This is what links a REGISTERED '
                                 . 'projector to this product, so the app can open the planner on the model the '
                                 . 'customer actually owns. Without it the app has to guess from the title, which '
                                 . 'breaks as soon as two models share a name prefix (A005 vs A005 Pro).',
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
        ] );

""", ""),
    (r"""        $erp_id = isset( $_POST['_aun_erp_product_id'] ) ? absint( $_POST['_aun_erp_product_id'] ) : 0;
        update_post_meta( $post_id, '_aun_erp_product_id', $erp_id > 0 ? $erp_id : '' );

""", ""),
    (r"""    public static function render_calculator() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return '';
""", r"""    public static function render_calculator( $atts = [] ) {
        global $product;
        $atts = shortcode_atts( [ 'product' => '' ], $atts, 'aun_throw_calculator' );
        if ( $atts['product'] !== '' ) {
            // aunstore.com: [aun_throw_calculator product="id|sku|slug"] works outside a product page.
            $ref = trim( (string) $atts['product'] );
            $pid = ctype_digit( $ref ) ? (int) $ref : (int) wc_get_product_id_by_sku( $ref );
            if ( ! $pid ) {
                $post = get_page_by_path( $ref, OBJECT, 'product' );
                $pid  = $post ? (int) $post->ID : 0;
            }
            $found = $pid ? wc_get_product( $pid ) : null;
            if ( ! $found || $found->get_status() !== 'publish' ) return '';
            $product = $found;
        }
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return '';
"""),
])

# Smart finder: aunstore's parent product category is "projectors".
port("aun-projector-wizard.php", "projector-wizard.php", [
    ("            'cat_main'         => 'projector-price',", "            'cat_main'         => 'projectors',"),
    ("            : home_url( '/projector-price/' );", "            : aunstore_projectors_url();"),
])

# Compare's front-end script is loaded from assets/ next to compare.php.
(DST / "assets").mkdir(exist_ok=True)
(DST / "assets" / "aun-compare.js").write_bytes((WORK / "aun-compare.js").read_bytes())
print("copied assets/aun-compare.js")
