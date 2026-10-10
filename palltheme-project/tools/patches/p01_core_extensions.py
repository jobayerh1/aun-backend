"""Patch: settings (trust items, newsletter), wiring, solution fields, search + contact tweaks."""
import os

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "palltheme-core")


def edit(rel, old, new):
    p = os.path.join(ROOT, rel)
    s = open(p, encoding="utf-8").read()
    if new in s:
        return
    assert old in s, (rel, old[:80])
    open(p, "w", encoding="utf-8").write(s.replace(old, new, 1))


S = "includes/admin/settings.php"
edit(S, "\t\t'form'        => __( 'Contact Form', 'palltheme-core' ),",
     "\t\t'form'        => __( 'Contact Form', 'palltheme-core' ),\n\t\t'newsletter'  => __( 'Newsletter', 'palltheme-core' ),")
edit(S, "\t\t'cta_title'           => array( 'stats', 'text', __( 'CTA heading', 'palltheme-core' ) ),",
     "\t\t'trust_items'         => array( 'stats', 'textarea', __( 'Trust / why-us items', 'palltheme-core' ), __( 'One per line: icon | Title | Short text. Used by the \"Features / Trust\" widget when it has no items of its own. Icons: shield, headset, speed, layers, users, check, lock, server, cloud…', 'palltheme-core' ) ),\n"
     "\t\t'cta_title'           => array( 'stats', 'text', __( 'CTA heading', 'palltheme-core' ) ),")
edit(S, "\t\t// Maintenance.\n\t\t'maint_enabled'",
     "\t\t// Newsletter.\n"
     "\t\t'newsletter_enabled'  => array( 'newsletter', 'checkbox', __( 'Show newsletter sign-up in the footer', 'palltheme-core' ) ),\n"
     "\t\t'newsletter_text'     => array( 'newsletter', 'text', __( 'Short text above the field', 'palltheme-core' ) ),\n"
     "\t\t'newsletter_shortcode' => array( 'newsletter', 'text', __( 'Newsletter plugin shortcode (optional)', 'palltheme-core' ), __( 'Paste a shortcode from MailPoet, FluentCRM, Mailchimp for WP, etc. Leave empty to use the built-in form: sign-ups are listed under Theme Settings → Subscribers with a CSV export.', 'palltheme-core' ) ),\n\n"
     "\t\t// Maintenance.\n\t\t'maint_enabled'")
edit(S, "\t\t'cta_title'         => __( 'Ready to modernize your infrastructure?', 'palltheme-core' ),",
     "\t\t'trust_items'       => \"shield | Secure infrastructure | Security designed in from day one, not bolted on.\\nheadset | 24/7 support | Engineers on call around the clock for critical systems.\\nspeed | Fast deployment | Pre-staged hardware and proven runbooks shorten go-live.\\nlayers | Scalable solutions | Architectures sized for today with room to grow.\\nusers | Professional engineers | Specialists in networking, cloud and security.\\ncheck | Quality assurance | Every system is tested and documented before handover.\",\n"
     "\t\t'newsletter_enabled' => '1',\n"
     "\t\t'newsletter_text'   => __( 'Monthly insights on infrastructure, cloud and security. No spam.', 'palltheme-core' ),\n"
     "\t\t'cta_title'         => __( 'Ready to modernize your infrastructure?', 'palltheme-core' ),")

edit("palltheme-core.php", "require_once PALLCORE_DIR . 'includes/render/sections.php';",
     "require_once PALLCORE_DIR . 'includes/render/sections.php';\nrequire_once PALLCORE_DIR . 'includes/render/sections-extra.php';")
edit("palltheme-core.php", "require_once PALLCORE_DIR . 'includes/frontend/frontend.php';",
     "require_once PALLCORE_DIR . 'includes/frontend/frontend.php';\nrequire_once PALLCORE_DIR . 'includes/frontend/newsletter.php';")

SC = "includes/render/shortcodes.php"
edit(SC, "\t\t'pall_home'         => 'pallcore_default_homepage',",
     "\t\t'pall_home'         => 'pallcore_default_homepage',\n"
     "\t\t'pall_features'     => 'pallcore_render_features',\n"
     "\t\t'pall_split'        => 'pallcore_render_split',\n"
     "\t\t'pall_media_credits' => 'pallcore_render_media_credits',\n"
     "\t\t'pall_newsletter'   => static fn() => function_exists( 'pallcore_newsletter_form' ) ? pallcore_newsletter_form() : '',")
edit(SC, "\t\t\t\tstatic function ( $atts ) use ( $callback ) {\n\t\t\t\t\treturn call_user_func( $callback, is_array( $atts ) ? $atts : array() );",
     "\t\t\t\tstatic function ( $atts, $content = '' ) use ( $callback ) {\n\t\t\t\t\treturn call_user_func( $callback, is_array( $atts ) ? $atts : array(), (string) $content );")

F = "includes/fields/schema.php"
edit(F, "\t\t\t\t$icon + array(\n\t\t\t\t\t'features'         => array( 'type' => 'lines', 'label' => __( 'Key capabilities', 'palltheme-core' ), 'help' => __( 'One per line.', 'palltheme-core' ) ),",
     "\t\t\t\t$icon + array(\n"
     "\t\t\t\t\t'problem'          => array( 'type' => 'html', 'label' => __( 'The problem', 'palltheme-core' ), 'help' => __( 'The challenge this industry typically faces.', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'approach'         => array( 'type' => 'html', 'label' => __( 'Our solution', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'architecture'     => array( 'type' => 'html', 'label' => __( 'Reference architecture', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'implementation'   => array( 'type' => 'html', 'label' => __( 'Implementation approach', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'features'         => array( 'type' => 'lines', 'label' => __( 'Key capabilities', 'palltheme-core' ), 'help' => __( 'One per line.', 'palltheme-core' ) ),")
edit(F, "\t\t\t\t\t'related_services' => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Related services', 'palltheme-core' ) ),\n\t\t\t\t) + pallcore_cta_fields(),\n\t\t\t),\n\t\t),\n\t\t'pall_case_study'",
     "\t\t\t\t\t'related_services' => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Related services', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'related_products' => array( 'type' => 'products', 'label' => __( 'Related products', 'palltheme-core' ) ),\n"
     "\t\t\t\t) + pallcore_cta_fields(),\n\t\t\t),\n\t\t),\n\t\t'pall_case_study'")
edit(F, "\t\t\t\t\t'related_services'   => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Services delivered', 'palltheme-core' ) ),",
     "\t\t\t\t\t'related_services'   => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Services delivered', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'related_solutions'  => array( 'type' => 'posts', 'post_type' => 'pall_solution', 'label' => __( 'Related solutions', 'palltheme-core' ) ),")

R = "includes/ajax/rest.php"
edit(R, "\t\t\t\t\t$item['sub']   = $product->get_sku() ? 'SKU ' . $product->get_sku() : '';",
     "\t\t\t\t\t$cats          = get_the_terms( $id, 'product_cat' );\n"
     "\t\t\t\t\t$item['sub']   = trim( ( $cats && ! is_wp_error( $cats ) ? $cats[0]->name : '' ) . ( $product->get_sku() ? ' · SKU ' . $product->get_sku() : '' ), ' ·' );")
edit(R, "\t\t\t\t$item['sub'] = wp_trim_words( get_the_excerpt( $id ), 10 );",
     "\t\t\t\t$tax         = array( 'post' => 'category', 'pall_service' => 'pall_service_cat', 'pall_solution' => 'pall_industry', 'pall_case_study' => 'pall_industry' );\n"
     "\t\t\t\t$term        = isset( $tax[ $type ] ) ? get_the_terms( $id, $tax[ $type ] ) : false;\n"
     "\t\t\t\t$item['sub'] = ( $term && ! is_wp_error( $term ) ? $term[0]->name . ' · ' : '' ) . wp_trim_words( get_the_excerpt( $id ), 9 );")

X = "includes/render/sections.php"
edit(X, "\t\t$items .= '<li class=\"pt-card pt-contact-item\"><span class=\"pt-icon-tile\">' . pallcore_ui_icon( 'phone' ) . '</span><div><h3>' . esc_html__( 'Call us', 'palltheme-core' ) . '</h3><a href=\"tel:' . esc_attr( pallcore_phone_digits( $phone ) ) . '\">' . esc_html( $phone ) . '</a>' . ( $wa ? '<p><a href=\"https://wa.me/' . esc_attr( pallcore_phone_digits( $wa, false ) ) . '\" target=\"_blank\" rel=\"noopener\">WhatsApp: ' . esc_html( $wa ) . '</a></p>' : '' ) . '</div></li>';",
     "\t\t$tg     = (string) pallcore_setting( 'telegram' );\n"
     "\t\t$extras = ( $wa ? '<p><a href=\"https://wa.me/' . esc_attr( pallcore_phone_digits( $wa, false ) ) . '\" target=\"_blank\" rel=\"noopener\">WhatsApp: ' . esc_html( $wa ) . '</a></p>' : '' )\n"
     "\t\t\t. ( $tg ? '<p><a href=\"https://t.me/' . esc_attr( rawurlencode( ltrim( $tg, '@' ) ) ) . '\" target=\"_blank\" rel=\"noopener\">Telegram: @' . esc_html( ltrim( $tg, '@' ) ) . '</a></p>' : '' );\n"
     "\t\t$items .= '<li class=\"pt-card pt-contact-item\"><span class=\"pt-icon-tile\">' . pallcore_ui_icon( 'phone' ) . '</span><div><h3>' . esc_html__( 'Call us', 'palltheme-core' ) . '</h3><a href=\"tel:' . esc_attr( pallcore_phone_digits( $phone ) ) . '\">' . esc_html( $phone ) . '</a>' . $extras . '</div></li>';")
print("ok")
