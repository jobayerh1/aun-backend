"""Patch: theme menu locations, footer layout, widget areas, solution/case-study templates, archive CTA."""
import os

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "palltheme")


def edit(rel, old, new):
    p = os.path.join(ROOT, rel)
    s = open(p, encoding="utf-8").read()
    if new in s:
        return
    assert old in s, (rel, old[:80])
    open(p, "w", encoding="utf-8").write(s.replace(old, new, 1))


edit("inc/setup.php",
     "\t\t\t'footer_company'  => __( 'Footer: Company', 'palltheme' ),\n\t\t\t'footer_products' => __( 'Footer: Products', 'palltheme' ),\n\t\t\t'footer_support'  => __( 'Footer: Support', 'palltheme' ),\n\t\t\t'footer_legal'    => __( 'Footer: Legal', 'palltheme' ),",
     "\t\t\t'footer_company'   => __( 'Footer: Company', 'palltheme' ),\n\t\t\t'footer_services'  => __( 'Footer: Services', 'palltheme' ),\n\t\t\t'footer_solutions' => __( 'Footer: Solutions', 'palltheme' ),\n\t\t\t'footer_products'  => __( 'Footer: Products', 'palltheme' ),\n\t\t\t'footer_support'   => __( 'Footer: Support', 'palltheme' ),\n\t\t\t'footer_resources' => __( 'Footer: Resources', 'palltheme' ),\n\t\t\t'footer_legal'     => __( 'Footer: Legal', 'palltheme' ),")
edit("inc/setup.php", "\tfor ( $i = 1; $i <= 4; $i++ ) {", "\tfor ( $i = 1; $i <= 7; $i++ ) {")

edit("inc/helpers.php", "\t\t'footer_layout'      => '5', // 4 | 5 columns.", "\t\t'footer_layout'      => 'mega', // mega | 5 | 4.")
edit("inc/customizer.php",
     "\t\t\t\t\t\t'4' => __( 'Brand + 3 columns', 'palltheme' ),\n\t\t\t\t\t\t'5' => __( 'Brand + 4 columns', 'palltheme' ),",
     "\t\t\t\t\t\t'mega' => __( 'Enterprise: brand, newsletter & contact + 7 link columns', 'palltheme' ),\n\t\t\t\t\t\t'5'    => __( 'Brand + 4 columns', 'palltheme' ),\n\t\t\t\t\t\t'4'    => __( 'Brand + 3 columns', 'palltheme' ),")

# Archive CTA for Palltheme content types.
edit("archive.php",
     "\t\t\t\t<?php palltheme_pagination(); ?>\n\t\t\t<?php else : ?>\n\t\t\t\t<?php get_template_part( 'template-parts/content/none' ); ?>\n\t\t\t<?php endif; ?>\n\t\t</div>\n\t</main>",
     "\t\t\t\t<?php palltheme_pagination(); ?>\n\t\t\t<?php else : ?>\n\t\t\t\t<?php get_template_part( 'template-parts/content/none' ); ?>\n\t\t\t<?php endif; ?>\n\t\t</div>\n\t\t<?php get_template_part( 'template-parts/single/cta' ); ?>\n\t</main>")

# Case study: related solutions.
edit("single-pall_case_study.php",
     "\t\t<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_services' ), 'title' => __( 'Services delivered', 'palltheme' ) ) ); ?>",
     "\t\t<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_services' ), 'title' => __( 'Services delivered', 'palltheme' ) ) ); ?>\n\t\t<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_solutions' ), 'title' => __( 'Related solutions', 'palltheme' ) ) ); ?>")
print("ok")
