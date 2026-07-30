<?php
/**
 * AUN Projector — Warranty page structured data (Schema.org JSON-LD).
 *
 * SCOPE: RankMath already outputs your Organization / LocalBusiness (ElectronicsStore)
 * schema sitewide, so this snippet deliberately does NOT repeat it (that would create
 * duplicate entities). It adds ONLY what RankMath isn't generating for this page:
 *   • FAQPage  — the 6 Q&As shown on the warranty page (great for AI + Google)
 *   • Service  — the free warranty-registration service, linked to your organization
 *
 * INSTALL (pick one):
 *   A) Free "Code Snippets" plugin → Add New → paste → "Run everywhere" → Activate.
 *   B) Child theme functions.php.
 *
 * Data is pre-filled from your RankMath export. If you edit the visible FAQ on the
 * page, mirror the change here so the schema keeps matching the page (Google requires
 * FAQ schema to match visible content).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', function () {

    // Output only on the warranty registration page.
    if ( ! is_page( 'warranty-register' ) ) return;

    $site     = 'https://aun-projector.com.bd';
    $page_url = $site . '/warranty-register/';

    // Lightweight reference to your organization (full details live in RankMath's schema).
    $provider = [
        '@type'    => 'Organization',
        'name'     => 'AUN Projector Bangladesh',
        'legalName'=> 'Smart Living Bangladesh',
        'url'      => $site . '/',
        'logo'     => $site . '/wp-content/uploads/2020/08/aun_logo_notxt.png',
        'telephone'=> '+8809638078888',
        'email'    => 'info@aun-projector.com.bd',
        'sameAs'   => [
            'https://www.facebook.com/aunprojectorbd/',
            'https://www.youtube.com/@aunprojectorbd/',
            'https://www.instagram.com/aunprojectorbd/',
            'https://www.tiktok.com/@aunprojectorbd/',
        ],
    ];

    $graph = [
        '@context' => 'https://schema.org',
        '@graph'   => [

            // ---- The warranty service ----
            [
                '@type'       => 'Service',
                '@id'         => $page_url . '#service',
                'serviceType' => 'Projector Warranty Registration',
                'name'        => 'AUN Projector Warranty Registration',
                'description' => 'Free online warranty activation for AUN projectors purchased anywhere in Bangladesh. Instant SMS and email confirmation, with manual invoice review within 24–48 hours when needed.',
                'provider'    => $provider,
                'areaServed'  => [ '@type' => 'Country', 'name' => 'Bangladesh' ],
                'offers'      => [
                    '@type'         => 'Offer',
                    'price'         => '0',
                    'priceCurrency' => 'BDT',
                    'url'           => $page_url,
                ],
            ],

            // ---- The FAQ (must match the visible page FAQ) ----
            [
                '@type'      => 'FAQPage',
                '@id'        => $page_url . '#faq',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name'  => 'How do I register my AUN projector warranty in Bangladesh?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Fill in the online form with your serial number, purchase date, dealer/shop name, and an invoice photo or PDF. Our system verifies the details and activates your 12-month warranty automatically, then sends an SMS and email confirmation.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'How long is the AUN projector warranty?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Every AUN projector comes with a 12-month official warranty, valid from the invoice (purchase) date. Registering ensures the start date is recorded correctly.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'I bought my projector from a local shop, can I still register?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Yes. This page is specifically for customers who purchased from dealers, resellers, or physical shops anywhere in Bangladesh. Just upload your invoice and we will verify it.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'Where is the serial number on my projector?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'The 11–14 digit serial number is printed on a sticker on the top or side of the packaging box, and often on the bottom of the projector itself.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'How will I know my warranty is approved?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'If your serial and shop details match our records, approval is instant and you receive an SMS and email right away. If a manual review is needed, our team confirms within 24–48 hours.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'Can I track a projector repair?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Yes. AUN Projector Bangladesh offers a live repair-status tracker so you can check the progress of any service request online at any time.' ],
                    ],
                ],
            ],

        ],
    ];

    echo '<script type="application/ld+json">'
        . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>' . "\n";

}, 20 );
