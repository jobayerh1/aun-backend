"""Patch: Elementor widgets for Features/Trust and Split sections."""
import os

P = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "palltheme-core", "includes", "elementor", "elementor.php")
s = open(P, encoding="utf-8").read()

if "'features'     => array( 'name' => 'features'" not in s:
    anchor = "\t\t'contact'      => array( 'name' => 'contact',"
    assert anchor in s
    new = (
        "\t\t'features'     => array( 'name' => 'features', 'title' => __( 'Features / Trust Cards', 'palltheme-core' ), 'icon' => 'eicon-info-box', 'renderer' => 'pallcore_render_features', 'defaults' => array( 'eyebrow' => __( 'Why us', 'palltheme-core' ), 'title' => __( 'Built on *trust*', 'palltheme-core' ) ), 'controls' => array( "
        "'items' => array( 'type' => 'textarea', 'label' => __( 'Items (icon | Title | Text | optional link — one per line)', 'palltheme-core' ), 'description' => __( 'Leave empty to use Business Settings → Trust items. Icons: server, network, cloud, shield, lock, headset, speed, layers, users, check, code, ai, rack, backup, chart, globe, consult, settings…', 'palltheme-core' ) ), "
        "'style' => array( 'type' => 'select', 'label' => __( 'Style', 'palltheme-core' ), 'default' => 'cards', 'options' => array( 'cards' => __( 'Cards', 'palltheme-core' ), 'trust' => __( 'Compact trust strip', 'palltheme-core' ), 'minimal' => __( 'Minimal (no card)', 'palltheme-core' ) ) ), "
        "'columns' => $columns( 3 ) ) ),\n"
        "\t\t'split'        => array( 'name' => 'split', 'title' => __( 'Image + Text (Split)', 'palltheme-core' ), 'icon' => 'eicon-image-box', 'renderer' => 'pallcore_render_split', 'defaults' => array( 'eyebrow' => __( 'About us', 'palltheme-core' ), 'title' => __( 'Engineers *first*', 'palltheme-core' ) ), 'controls' => array( "
        "'image' => array( 'type' => 'media', 'label' => __( 'Image', 'palltheme-core' ), 'att_url' => 'image_url' ), "
        "'lottie' => array( 'type' => 'text', 'label' => __( 'Or Lottie JSON URL (replaces the image)', 'palltheme-core' ) ), "
        "'text' => array( 'type' => 'textarea', 'label' => __( 'Text (blank line = new paragraph)', 'palltheme-core' ) ), "
        "'bullets' => array( 'type' => 'textarea', 'label' => __( 'Bullet points (one per line)', 'palltheme-core' ) ), "
        "'button_text' => array( 'type' => 'text', 'label' => __( 'Button text', 'palltheme-core' ) ), "
        "'button_url' => array( 'type' => 'url', 'label' => __( 'Button link', 'palltheme-core' ) ), "
        "'button2_text' => array( 'type' => 'text', 'label' => __( 'Second button text', 'palltheme-core' ) ), "
        "'button2_url' => array( 'type' => 'url', 'label' => __( 'Second button link', 'palltheme-core' ) ), "
        "'badge_value' => array( 'type' => 'text', 'label' => __( 'Floating badge value (e.g. 15+)', 'palltheme-core' ) ), "
        "'badge_label' => array( 'type' => 'text', 'label' => __( 'Floating badge label', 'palltheme-core' ) ), "
        "'reverse' => array( 'type' => 'switch', 'label' => __( 'Image on the right', 'palltheme-core' ), 'default' => '' ) ) ),\n"
    )
    s = s.replace(anchor, new + anchor, 1)

if "final class Pallcore_Widget_Features" not in s:
    anchor = "/** Widget: contact. */"
    assert anchor in s
    cls = ""
    for key, cn in (("features", "Pallcore_Widget_Features"), ("split", "Pallcore_Widget_Split")):
        cls += (f"/** Widget: {key}. */\nfinal class {cn} extends Pallcore_Elementor_Widget {{\n\t/** @inheritDoc */\n"
                f"\tprotected function config(): array {{\n\t\treturn pallcore_elementor_widget_configs()['{key}'];\n\t}}\n}}\n\n")
    s = s.replace(anchor, cls + anchor, 1)
    s = s.replace("'Pallcore_Widget_Contact' )", "'Pallcore_Widget_Contact', 'Pallcore_Widget_Features', 'Pallcore_Widget_Split' )", 1)

open(P, "w", encoding="utf-8").write(s)
print("ok", "Pallcore_Widget_Split' )" in s)
