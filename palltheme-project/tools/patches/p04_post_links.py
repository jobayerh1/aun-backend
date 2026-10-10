"""Patch: related services/products fields on blog posts + output on single posts."""
import os

BASE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..")


def edit(rel, old, new):
    p = os.path.join(BASE, rel)
    s = open(p, encoding="utf-8").read()
    if new in s:
        return
    assert old in s, (rel, old[:80])
    open(p, "w", encoding="utf-8").write(s.replace(old, new, 1))


edit("palltheme-core/includes/fields/schema.php",
     "\t\t'product'          => array(",
     "\t\t'post'             => array(\n"
     "\t\t\t'pall_post_links' => array(\n"
     "\t\t\t\t__( 'Related services & products (Palltheme)', 'palltheme-core' ),\n"
     "\t\t\t\t'normal',\n"
     "\t\t\t\tarray(\n"
     "\t\t\t\t\t'related_services' => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Related services', 'palltheme-core' ), 'help' => __( 'Shown below the article.', 'palltheme-core' ) ),\n"
     "\t\t\t\t\t'related_products' => array( 'type' => 'products', 'label' => __( 'Recommended products', 'palltheme-core' ) ),\n"
     "\t\t\t\t),\n"
     "\t\t\t),\n"
     "\t\t),\n"
     "\t\t'product'          => array(")

edit("palltheme/single.php",
     "\t\t<div class=\"pt-container pt-section pt-section--tight\">\n\t\t\t<?php palltheme_related_posts(); ?>\n\t\t</div>",
     "\t\t<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( get_the_ID(), 'related_services' ), 'title' => __( 'Services related to this article', 'palltheme' ) ) ); ?>\n"
     "\t\t<?php get_template_part( 'template-parts/single/related-products', null, array( 'ids' => palltheme_meta( get_the_ID(), 'related_products' ) ) ); ?>\n\n"
     "\t\t<div class=\"pt-container pt-section pt-section--tight\">\n\t\t\t<?php palltheme_related_posts(); ?>\n\t\t</div>")
print("ok")
