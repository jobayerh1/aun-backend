<?php
/**
 * AUN Projector — Repair Status page structured data (Schema.org JSON-LD).
 *
 * SCOPE: RankMath already outputs your Organization / LocalBusiness sitewide, so this
 * adds only the page-specific schema it isn't generating:
 *   • Service  — your projector repair / service-centre offering
 *   • FAQPage  — the 6 Q&As shown on the repair-status page
 *
 * INSTALL: free "Code Snippets" plugin → Add New → paste → "Run everywhere" → Activate
 * (or child-theme functions.php). You can keep this in the SAME snippet area as the
 * warranty schema — they target different pages.
 *
 * If you edit the visible FAQ on the page, mirror the change here (Google requires
 * FAQ schema to match the visible content).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', function () {

    // Output only on the repair-status page. Change the slug if yours differs.
    if ( ! is_page( 'repair-status' ) ) return;

    $site     = 'https://aun-projector.com.bd';
    $page_url = $site . '/repair-status/';

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

            // ---- The repair service ----
            [
                '@type'       => 'Service',
                '@id'         => $page_url . '#service',
                'serviceType' => 'Projector Repair Service',
                'name'        => 'AUN Projector Repair & Service Tracking',
                'description' => 'Authorised AUN projector repair and service in Bangladesh with live, online repair-status tracking. Check your repair stage, estimated cost, and full service log using a Job Sheet number or mobile number.',
                'provider'    => $provider,
                'areaServed'  => [ '@type' => 'Country', 'name' => 'Bangladesh' ],
                'availableChannel' => [
                    '@type'         => 'ServiceChannel',
                    'serviceUrl'    => $page_url,
                    'servicePhone'  => '+8809638078888',
                ],
            ],

            // ---- The FAQ (must match the visible page FAQ) ----
            [
                '@type'      => 'FAQPage',
                '@id'        => $page_url . '#faq',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name'  => 'How do I check my AUN projector repair status?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Enter your Job Sheet number (e.g. 2025/0001) or the mobile number you gave during submission, then press "Check Repair Status." The latest status is pulled live from our service centre.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'Can I track my repair using my mobile number?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Yes. Choose "Mobile Number" and enter the same number you provided when you handed in your projector for service.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'How long does a projector repair take?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Service typically takes 2–3 working days when parts are in stock. If parts must be imported, it can take 45+ days. You can follow the exact stage online at any time.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'Will I see the repair cost?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Yes. Once our technicians assess your device, the estimated repair cost appears in your tracking result.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'My repair status is not showing — what should I do?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Double-check your Job Sheet number or mobile number and try again after a few minutes. If it still does not appear, contact our support team.' ],
                    ],
                    [
                        '@type' => 'Question',
                        'name'  => 'Is my repair information secure?',
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Yes. We show only limited details, data is fetched through a secure server proxy, and full information is reserved for the job owner.' ],
                    ],
                ],
            ],

        ],
    ];

    echo '<script type="application/ld+json">'
        . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>' . "\n";

}, 20 );
