<?php
/**
 * Plugin Name: AUN Repair Tracker
 * Description: Repair tracking shortcode + secure ERP proxy for AUN Projector.
 *              A Pathao consignment ID written in an engineer's note (e.g. "Pathao DA200826WQJCJ5")
 *              is rendered as a tappable parcel-tracking chip on the front end.
 * Version: 1.3.0
 * Author: Smart Living Bangladesh
 *
 * v1.3.0: • Repair log is a TIMELINE on phones. It was a 600px-wide four-column table inside a
 *           horizontal scroller, so "By" and "Note" were off-screen with nothing to hint at them.
 *         • Attached documents use a CSS grid with square tiles, so they no longer stack one per row.
 *         • ERP calls retry once, and the timeout drops 20s -> 8s (the ERP is on this machine and
 *           answers in ~20ms over loopback, so 20s only ever meant a stuck request the customer waited for).
 *         • If the ERP cannot be reached, the last good answer for that same search is shown with a
 *           clear "this may be out of date" note, instead of a dead end.
 *         • Failures are logged with attempt count, elapsed time, and whether the loopback breaker
 *           was open — enough to actually diagnose the next occurrence.
 */

if (!defined('ABSPATH')) { exit; }

class SLB_Repair_Tracker {

    /** Prefix for the "last known good" copy used when the ERP cannot be reached. */
    const STALE_PREFIX = 'slb_erp_stale_';

    private string $erp_url;
    private string $api_key;

    /** Tracks whether assets have been enqueued — avoids double-output. */
    private static bool $assets_enqueued = false;

    public function __construct() {
        // API key should be defined in wp-config.php as:
        //   define( 'SLB_ERP_API_KEY', 'your-key-here' );
        // Fallback to the hardcoded value only if the constant is not set.
        $this->erp_url = 'https://portal.smartliving.com.bd/api/repair-status';
        $this->api_key = defined('SLB_ERP_API_KEY') ? SLB_ERP_API_KEY : '';

        add_action('rest_api_init',       [$this, 'register_routes']);
        add_shortcode('slb_repair_tracker', [$this, 'shortcode']);
        // Assets are enqueued lazily from within the shortcode callback —
        // this prevents the CSS/JS from loading on every page of the site.
    }

    public function register_routes() {
        register_rest_route('slb/v1', '/repair-status', array(
            'methods'  => 'GET',
            'callback' => array($this, 'proxy_repair_status'),
            'permission_callback' => '__return_true',
        ));

        // ✅ NEW: Secure Image Proxy Route (Hides the ERP URL)
        register_rest_route('slb/v1', '/repair-image', array(
            'methods'  => 'GET',
            'callback' => array($this, 'proxy_repair_image'),
            'permission_callback' => '__return_true',
        ));
    }

    public function proxy_repair_image( $request ) {
        $file = sanitize_text_field( $request->get_param('file') );
        if ( empty($file) ) {
            return new WP_Error('no_file', 'No file specified', ['status' => 400]);
        }

        // Prevent directory traversal
        $file = basename($file);

        $erp_img_url = 'https://portal.smartliving.com.bd/uploads/media/' . $file;
        $response    = wp_remote_get($erp_img_url, ['timeout' => 15]);

        if ( is_wp_error($response) ) {
            return new WP_Error('fetch_error', 'Failed to fetch image', ['status' => 500]);
        }

        // Only forward a successful 200 response
        if ( (int) wp_remote_retrieve_response_code($response) !== 200 ) {
            return new WP_Error('not_found', 'Image not found', ['status' => 404]);
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Allowlist: only forward known image types — never forward HTML error pages
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $base_type     = strtolower(trim(explode(';', $content_type)[0]));
        if ( ! in_array($base_type, $allowed_types, true) ) {
            return new WP_Error('invalid_type', 'Invalid content type', ['status' => 400]);
        }

        header('Content-Type: ' . $base_type);
        header('Cache-Control: public, max-age=86400');
        echo wp_remote_retrieve_body($response);
        exit;
    }

    public function proxy_repair_status( $request ) {
        // Rate limiting — fixed time-bucket window (not sliding)
        // A sliding window resets on every request; fixed buckets don't.
        $ip     = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $bucket = floor(time() / 60); // changes every 60 seconds
        $key    = 'slb_rl_' . md5($ip . '_' . $bucket);
        $count  = (int) get_transient($key);

        if ( $count >= 10 ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Too many requests. Please wait a moment and try again.',
            ], 429);
        }
        set_transient($key, $count + 1, 65); // 65s TTL > 60s bucket so it never expires mid-window

        $search_by = sanitize_text_field($request->get_param('search_by'));
        $query     = sanitize_text_field($request->get_param('query'));
        $serial    = sanitize_text_field($request->get_param('serial'));

        if ( empty($search_by) || empty($query) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'search_by and query are required'], 422);
        }

        // Normalize (strip whitespace)
        $query_clean = preg_replace('/\s+/', '', $query);

        if ( $search_by === 'job_sheet' ) {
            if ( ! preg_match('/^\d{4}\/\d{4}$/', $query_clean) ) {
                return new WP_REST_Response(['success' => false, 'message' => 'Invalid Job Sheet format. Example: 2025/0001'], 422);
            }
        }

        if ( $search_by === 'mobile' ) {
            $mobile = preg_replace('/\D/', '', $query_clean);
            if ( ! preg_match('/^01\d{9}$/', $mobile) ) {
                return new WP_REST_Response(['success' => false, 'message' => 'Invalid mobile number. Example: 017XXXXXXXX'], 422);
            }
            $query_clean = $mobile;
        }

        $serial_clean = '';
        if ( ! empty($serial) ) {
            $serial_clean = preg_replace('/\D/', '', $serial);
            if ( strlen($serial_clean) < 4 || strlen($serial_clean) > 30 ) {
                return new WP_REST_Response(['success' => false, 'message' => 'Invalid serial number (4–30 digits)'], 422);
            }
        }

        $query = $query_clean;

        // Cache ERP responses for 2 minutes — repair status doesn't change by the second.
        // This protects against a slow ERP endpoint and reduces load on both systems.
        $cache_key = 'slb_erp_' . md5($search_by . '|' . $query . '|' . $serial_clean);
        $cached    = get_transient($cache_key);
        if ( $cached !== false ) {
            return new WP_REST_Response($cached, 200);
        }

        // The ERP failed moments ago. Don't pile on — hand back the last good answer
        // if we have one, and only bother the ERP again once the cool-off has passed.
        if ( get_transient($cache_key . '_fail') ) {
            $stale = get_transient(self::STALE_PREFIX . md5($search_by . '|' . $query . '|' . $serial_clean));
            if ( is_array($stale) ) {
                $stale['stale'] = true;
                return new WP_REST_Response($stale, 200);
            }
        }

        $params = ['search_by' => $search_by, 'query' => $query];
        if ( ! empty($serial_clean) ) $params['serial'] = $serial_clean;

        $url = add_query_arg($params, $this->erp_url);

        /*
         * The ERP lives on this same machine and AUN Local Upstream routes this
         * call over loopback, so a healthy request is ~20ms. A 20-second ceiling
         * therefore only ever means something is genuinely wrong, and the customer
         * sits watching a spinner for it. Ask for less, and retry once instead:
         * nearly every failure here is a momentary blip, not a dead service.
         */
        $attempts = 0;
        $started  = microtime(true);
        do {
            $attempts++;
            $response = wp_remote_get($url, [
                'headers' => ['X-API-KEY' => $this->api_key, 'Accept' => 'application/json'],
                'timeout' => 8,
            ]);
            if ( ! is_wp_error($response) ) {
                break;
            }
            if ( $attempts < 2 ) {
                usleep(250000); // 250ms — long enough to clear a blip, short enough not to be felt
            }
        } while ( $attempts < 2 );

        if ( is_wp_error($response) ) {
            // Log enough to actually diagnose this: how long it took, how many tries,
            // and whether the loopback route had been switched off by its breaker.
            error_log(sprintf(
                'SLB Repair Tracker ERP error after %d attempt(s) in %dms: %s (loopback breaker: %s)',
                $attempts,
                (int) ( ( microtime(true) - $started ) * 1000 ),
                $response->get_error_message(),
                get_transient('aun_local_upstream_off') ? 'OPEN - using DNS' : 'closed - using loopback'
            ));

            /*
             * Rather than show an error, serve the last good answer for this exact
             * search if we have one. Repair status changes a few times a week, so a
             * slightly stale answer is far more useful to a customer than a dead end.
             */
            $stale = get_transient(self::STALE_PREFIX . md5($search_by . '|' . $query . '|' . $serial_clean));
            if ( is_array($stale) ) {
                $stale['stale'] = true;
                return new WP_REST_Response($stale, 200);
            }

            // Nothing to fall back on. Remember the failure briefly so a a burst of
            // visitors does not queue up against an ERP that is already struggling.
            set_transient($cache_key . '_fail', 1, 30);

            return new WP_REST_Response(['success' => false, 'message' => 'Could not reach the repair service. Please try again later.'], 500);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $data        = json_decode($body, true);

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log('SLB Repair Tracker: invalid JSON from ERP. Status: ' . $status_code);
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid response from repair service.'], 500);
        }

        // Rewrite ERP document URLs through the WordPress image proxy
        if ( isset($data['data']['documents']) && is_array($data['data']['documents']) ) {
            $proxied = [];
            foreach ( $data['data']['documents'] as $doc_url ) {
                $filename  = basename($doc_url);
                $proxied[] = rest_url('slb/v1/repair-image?file=' . urlencode($filename));
            }
            $data['data']['documents'] = $proxied;
        }

        // Only cache successful responses
        if ( ! empty($data['success']) ) {
            set_transient($cache_key, $data, 2 * MINUTE_IN_SECONDS);
            // ...and keep a much longer-lived copy purely as a safety net for when
            // the ERP is unreachable. See the WP_Error branch above.
            set_transient(self::STALE_PREFIX . md5($search_by . '|' . $query . '|' . $serial_clean), $data, DAY_IN_SECONDS);
        }

        return new WP_REST_Response($data, $status_code);
    }

    public function enqueue_assets() {

        if ( self::$assets_enqueued ) return;
        self::$assets_enqueued = true;

        $css = <<<'EOD'
        .slb-repair-wrap{width:100%;max-width:100%;margin:0;padding:0;font-family:inherit;}
        .slb-repair-hero h1{font-size:34px;margin:0 0 6px 0}
        .slb-repair-hero p{opacity:.8;font-size:15px;margin:0}
        .slb-repair-card{background:#ffffff;border-radius:16px;padding:26px;box-shadow:0 12px 40px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.05)}
        .slb-form-row{margin-bottom:18px}
        .slb-form-row label{display:block;font-weight:600;margin-bottom:8px;font-size:14px;color:#374151;}
        .slb-form-row input,.slb-form-row select{width:100%;padding:14px 16px;border-radius:12px;border:1px solid #d1d5db;font-size:15px;outline:none;background:#f9fafb;color:#111827;transition:border 0.2s;}
        .slb-form-row input:focus,.slb-form-row select:focus{border-color:#0188fe;background:#fff;}
        .slb-loading{margin-top:14px;font-size:14px;opacity:.8;text-align:center;}
        .slb-result{margin-top:24px;}

        #slb_search_by{
          height:52px !important;line-height:52px !important;padding-top:0 !important;padding-bottom:0 !important;
          padding-left:16px !important;padding-right:40px !important;display:block !important;box-sizing:border-box !important;
          background-image:url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2720%27 height=%2720%27 viewBox=%270 0 20 20%27%3E%3Cpath fill=%27%234b5563%27 d=%27M5.5 7.5l4.5 5 4.5-5z%27/%3E%3C/svg%3E') !important;
          background-repeat:no-repeat !important;background-position:right 14px center !important;background-size:18px 18px !important;cursor:pointer !important;
        }

        .slb-result-header{text-align:center;padding-bottom:20px;margin-bottom:24px;border-bottom:1px solid #e5e7eb;}
        .slb-result-header h3{font-size:24px;margin:0;color:#111827;font-weight:800;letter-spacing:-0.5px;}
        .slb-section-title{font-weight:700;font-size:16px;margin:32px 0 16px;color:#374151;text-transform:uppercase;letter-spacing:0.5px;}
        .slb-data-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px;}
        .slb-data-item{background:#f9fafb;padding:14px 16px;border-radius:12px;border:1px solid #f3f4f6;}
        .slb-data-label{font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#6b7280;margin-bottom:6px;font-weight:700;}
        .slb-data-value{font-size:15px;color:#111827;font-weight:600;word-break:break-word;}
        .slb-data-item.full-width{grid-column:1 / -1;}

        /* Problem-reported pills — neutral chip + amber status dot, so the
           coloured status badge stays the focal point of the page. */
        .slb-pill-group{display:flex;flex-wrap:wrap;gap:8px;margin-top:2px;}
        .slb-pill{display:inline-flex;align-items:center;gap:7px;background:#f8fafc;color:#334155;border:1px solid #e2e8f0;padding:6px 13px 6px 11px;border-radius:999px;font-size:13px;font-weight:600;line-height:1.3;}
        .slb-pill::before{content:"";width:7px;height:7px;border-radius:50%;background:#d97706;flex:0 0 auto;}

        /* Status badge — exact colours matching the ERP status configuration */
        .slb-status-badge{display:inline-flex;align-items:center;padding:5px 13px;border-radius:20px;font-size:13px;font-weight:700;background:#e0e7ff;color:#3730a3;}

        /* Received – Inspection Pending  #6c757d */
        .slb-status-badge.s-received-inspection{background:#f0f1f2;color:#3d4349;}
        /* Initial Testing in Progress  #17a2b8 */
        .slb-status-badge.s-initial-testing{background:#d8f4f8;color:#0c6a77;}
        /* Issue Identified – Parts Diagnosis  #fd7e14 */
        .slb-status-badge.s-issue-identified{background:#fff0e0;color:#9a4a00;}
        /* Parts Requested from Factory  #20c997 */
        .slb-status-badge.s-parts-requested{background:#d4f7ed;color:#0a6648;}
        /* Parts Awaiting Dispatch  #6f42c1 */
        .slb-status-badge.s-parts-awaiting-dispatch{background:#ede8f9;color:#42287a;}
        /* Parts In Transit  #003366 */
        .slb-status-badge.s-parts-in-transit{background:#d6e4f0;color:#002244;}
        /* Parts Received – Repair in Progress  #ffc107 */
        .slb-status-badge.s-parts-received-repair{background:#fff8d6;color:#7a5900;}
        /* Repaired  #145a32 */
        .slb-status-badge.s-repaired{background:#d4edda;color:#0c3d22;}
        /* Service Only – No Repair Required  #1e90ff */
        .slb-status-badge.s-service-only{background:#dbeeff;color:#0a5db5;}
        /* Unrepairable – Parts Not Available  #d9534f */
        .slb-status-badge.s-unrepairable{background:#fde8e8;color:#8b1a1a;}
        /* Ready for Delivery / Collection  #0d6efd */
        .slb-status-badge.s-ready-delivery{background:#dbeafe;color:#1540a0;}
        /* Delivered / Collected  #198754 */
        .slb-status-badge.s-delivered{background:#d4edda;color:#0c5132;}
        /* Parts Issue – Reorder Required  #fd7e14 */
        .slb-status-badge.s-parts-issue{background:#fff0e0;color:#9a4a00;}
        /* Repair Deferred – Awaiting Parts  #fbbf24 */
        .slb-status-badge.s-repair-deferred{background:#fef9e7;color:#7a5200;}

        .slb-cost-highlight{color:#059669;font-weight:800;font-size:18px;}
        .slb-note-toggle{display:inline-block;margin-left:10px;font-weight:600;color:#0188fe;text-decoration:none;cursor:pointer}
        .slb-note-toggle:hover{text-decoration:underline;}
        .slb-note-text{word-break:break-word}
        /* Courier tracking chip rendered from a consignment ID inside a note */
        .slb-track-chip{display:inline-flex;align-items:center;gap:7px;margin:2px 0;padding:5px 11px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#0188fe;font-weight:700;font-size:13px;text-decoration:none;line-height:1.2;white-space:nowrap;vertical-align:middle;transition:background .15s,border-color .15s,color .15s;}
        .slb-track-chip:hover,.slb-track-chip:focus{background:#0188fe;border-color:#0188fe;color:#fff;text-decoration:none;}
        .slb-track-chip-id{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.4px;}
        .slb-track-chip-go{font-size:10px;opacity:.75;}
        .slb-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:12px;border:1px solid #e5e7eb;background:#fff;}
        .slb-table-scroll table{min-width:600px;width:100%;border-collapse:collapse;}
        .slb-table-scroll th{background:#f9fafb;padding:14px 16px;text-align:left;color:#4b5563;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #e5e7eb;}
        .slb-table-scroll td{padding:14px 16px;font-size:14px;border-bottom:1px solid #f3f4f6;color:#374151;}
        .slb-table-scroll tr:last-child td{border-bottom:none;}
        /* A grid, not a wrapping flex row: the column count is then guaranteed
           regardless of each image's intrinsic size, which is what let these
           stack one per row on a phone. */
        .slb-doc-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px;margin-bottom:24px;}
        .slb-doc-thumb{display:block;border:2px solid #fff;padding:0;border-radius:10px;background:#f3f4f6;box-shadow:0 4px 12px rgba(0,0,0,0.08);transition:transform 0.2s ease,box-shadow 0.2s ease;cursor:zoom-in;overflow:hidden;aspect-ratio:1/1;}
        .slb-doc-thumb:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.12);}
        /* !important: the theme sets a global img{height:auto} that would otherwise
           win and let a tall photo blow the tile out of shape. */
        .slb-doc-thumb img{width:100% !important;height:100% !important;object-fit:cover;display:block;}
        @media(min-width:768px){.slb-doc-gallery{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));}}

        /* ---- Repair log --------------------------------------------------
           On a phone the four-column table was 600px wide inside a horizontal
           scroller, so two columns sat off-screen with nothing to suggest they
           existed. A history is a timeline, so on small screens it is drawn as
           one: everything visible, nothing to discover. The table stays for
           desktop, where it fits and reads more densely. */
        .slb-timeline{list-style:none;margin:0;padding:0 0 0 26px;position:relative;}
        .slb-timeline:before{content:"";position:absolute;left:7px;top:8px;bottom:8px;width:2px;background:#e5e7eb;}
        .slb-tl-item{position:relative;padding:0 0 20px 0;}
        .slb-tl-item:last-child{padding-bottom:0;}
        .slb-tl-item:before{content:"";position:absolute;left:-26px;top:4px;width:16px;height:16px;border-radius:50%;background:#fff;border:3px solid #d1d5db;box-sizing:border-box;}
        /* The newest entry is what the customer came to see. */
        .slb-tl-item:first-child:before{border-color:#0188fe;box-shadow:0 0 0 4px rgba(1,136,254,.15);}
        .slb-tl-action{font-weight:700;color:#1f2937;font-size:15px;line-height:1.45;}
        .slb-tl-item:first-child .slb-tl-action{color:#0166c0;}
        .slb-tl-meta{color:#6b7280;font-size:12.5px;margin-top:3px;}
        .slb-tl-note{margin-top:7px;font-size:13.5px;color:#374151;background:#f9fafb;border:1px solid #f0f1f3;border-radius:9px;padding:9px 11px;}
        .slb-tl-empty{color:#9ca3af;text-align:center;padding:26px 0;}
        .slb-stale-note{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:10px 13px;font-size:13.5px;margin:0 0 16px;}
        .slb-timeline{display:block;}
        .slb-table-scroll{display:none;}
        @media(min-width:768px){.slb-timeline{display:none;}.slb-table-scroll{display:block;}}
        .slb-lightbox{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(17,24,39,0.9);z-index:999999;display:none;align-items:center;justify-content:center;cursor:zoom-out;backdrop-filter:blur(8px);}
        .slb-lightbox img{max-width:90%;max-height:90vh;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.5);animation:slbZoomIn 0.25s cubic-bezier(0.16,1,0.3,1);}
        .slb-lightbox-close{position:absolute;top:20px;right:30px;color:#fff;font-size:44px;font-weight:300;cursor:pointer;line-height:1;transition:color 0.2s;}
        .slb-lightbox-close:hover{color:#f87171;}
        @keyframes slbZoomIn{from{transform:scale(0.95);opacity:0;}to{transform:scale(1);opacity:1;}}
        .slb-error-box{background:#fee2e2;color:#991b1b;padding:16px 20px;border-radius:12px;font-weight:600;display:flex;align-items:center;gap:10px;}
        .slb-error-box i{font-size:18px;flex-shrink:0;}

        /* Primary CTA — same pattern as the AUN Live Tracking button: solid blue, simple background hover */
        .slb-check-btn{width:100%;margin-top:8px;padding:15px 24px;background:#0188fe;color:#fff;border:1px solid #0188fe;border-radius:12px;font-size:16px;font-weight:700;line-height:1;cursor:pointer;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:background .2s;}
        .slb-check-btn:hover{background:#0070d6;border-color:#0070d6;}
        .slb-check-btn:disabled{background:#99c9fb;border-color:#99c9fb;cursor:not-allowed;}
EOD;

        wp_register_style('slb-repair-tracker-inline', false);
        wp_enqueue_style('slb-repair-tracker-inline');
        wp_add_inline_style('slb-repair-tracker-inline', $css);

        // Pass the REST URL dynamically — never hardcode the domain in JS.
        wp_register_script('slb-repair-tracker-inline', '', [], null, true);
        wp_enqueue_script('slb-repair-tracker-inline');
        wp_localize_script('slb-repair-tracker-inline', 'SLB_REPAIR', [
            'rest_url' => rest_url('slb/v1/repair-status'),
        ]);

        $js = <<<'EOD'
document.addEventListener('DOMContentLoaded', function(){

    // Lightbox — injected once
    if (!document.getElementById('slb_lightbox')) {
        var lb = document.createElement('div');
        lb.id = 'slb_lightbox';
        lb.className = 'slb-lightbox';
        lb.innerHTML = '<span class="slb-lightbox-close">&times;</span><img id="slb_lightbox_img" src="" alt="">';
        document.body.appendChild(lb);
        lb.addEventListener('click', function() {
            lb.style.display = 'none';
            document.getElementById('slb_lightbox_img').src = '';
        });
    }

    // Helper functions — defined ONCE, outside any loop
    function slbEscapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    function slbNl2Br(str) {
        return String(str).replace(/\n/g, '<br>');
    }

    /**
     * "2026-08-21 20:31:02" -> "21 Aug 2026, 8:31 pm".
     * The raw database stamp is fine in a desktop table column, but in the phone
     * timeline it is the only date on screen, so it should read like a date.
     * Falls back to the original string if it cannot be parsed — never blanks it.
     */
    function slbPrettyDate(raw) {
        var str = String(raw || '').trim();
        if (!str) return '-';
        // Safari refuses "YYYY-MM-DD HH:MM:SS"; slashes parse everywhere.
        var d = new Date(str.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return str;
        return d.toLocaleString('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        }).replace(',', '');
    }

    /* ---------------------------------------------------------------------
     * Courier tracking inside engineer notes.
     * When a note carries a Pathao consignment ID (e.g. DA200826WQJCJ5) - or a
     * pasted Pathao tracking URL - it becomes a tappable "track" chip instead of
     * dead text. Uses the same public-tracking endpoint as the order tracker and
     * the spare-parts plugin (no phone number in the URL, no API call needed).
     * ------------------------------------------------------------------- */
    var SLB_PATHAO_TRACK = 'https://merchant.pathao.com/public-tracking?consignment_id=';

    function slbChipHtml(id) {
        return '<a class="slb-track-chip" href="' + SLB_PATHAO_TRACK + encodeURIComponent(id) + '"' +
               ' target="_blank" rel="noopener noreferrer"' +
               ' title="Track this parcel on Pathao" aria-label="Track parcel ' + id + ' on Pathao">' +
               '<i class="fa-solid fa-truck-fast" aria-hidden="true"></i>' +
               '<span class="slb-track-chip-id">' + id + '</span>' +
               '<i class="fa-solid fa-arrow-up-right-from-square slb-track-chip-go" aria-hidden="true"></i>' +
               '</a>';
    }

    /**
     * Linkify consignment IDs. Runs on ALREADY-ESCAPED html and only ever matches
     * [A-Z0-9] tokens, so the URL it builds can never carry markup. Matches are
     * stashed behind placeholders first so a URL match is not re-matched as a
     * bare ID (which would double-wrap the chip).
     */
    function slbTrackChip(html) {
        var out = String(html), chips = [];
        function stash(id) { chips.push(String(id).toUpperCase()); return '\u0000C' + (chips.length - 1) + '\u0000'; }

        // A pasted tracking URL (its '&' is already escaped to '&amp;' at this point).
        out = out.replace(/https?:\/\/[^\s<]*pathao[^\s<]*consignment_id=([A-Za-z0-9]+)/gi,
                          function (m, id) { return stash(id); });
        // A bare consignment ID: 2-3 letters + 6-8 digits + 4-10 alphanumerics.
        out = out.replace(/\b([A-Z]{2,3}\d{6,8}[A-Z0-9]{4,10})\b/g,
                          function (m, id) { return stash(id); });

        return out.replace(/\u0000C(\d+)\u0000/g, function (m, i) { return slbChipHtml(chips[parseInt(i, 10)]); });
    }

    /** Note -> safe display html: escape, keep line breaks, then linkify tracking IDs. */
    function slbNoteHtml(raw) {
        return slbTrackChip(slbNl2Br(slbEscapeHtml(raw)));
    }

    // Status badge class — exact match on ERP status names (case-insensitive, trimmed).
    // Each entry maps the exact string from the ERP to the CSS class defined in the stylesheet.
    function slbStatusClass(status) {
        var s = (status || '').toLowerCase().trim();
        var map = {
            'received – inspection pending':          's-received-inspection',
            'received - inspection pending':          's-received-inspection',
            'initial testing in progress':            's-initial-testing',
            'issue identified – parts diagnosis':     's-issue-identified',
            'issue identified - parts diagnosis':     's-issue-identified',
            'parts requested from factory':           's-parts-requested',
            'parts awaiting dispatch':                's-parts-awaiting-dispatch',
            'parts in transit':                       's-parts-in-transit',
            'parts received – repair in progress':   's-parts-received-repair',
            'parts received - repair in progress':   's-parts-received-repair',
            'repaired':                               's-repaired',
            'service only – no repair required':      's-service-only',
            'service only - no repair required':      's-service-only',
            'unrepairable – parts not available':     's-unrepairable',
            'unrepairable - parts not available':     's-unrepairable',
            'ready for delivery / collection':        's-ready-delivery',
            'delivered / collected':                  's-delivered',
            'parts issue – reorder required':         's-parts-issue',
            'parts issue - reorder required':         's-parts-issue',
            'repair deferred – awaiting parts':       's-repair-deferred',
            'repair deferred - awaiting parts':       's-repair-deferred',
        };
        return map[s] || '';
    }

    var btn     = document.getElementById('slb_check_btn');
    var loading = document.getElementById('slb_loading');
    var resultBox = document.getElementById('slb_result');
    var queryInput = document.getElementById('slb_query');
    var searchBySelect = document.getElementById('slb_search_by');

    if (!btn) return;

    // Update placeholder when search type changes
    var placeholders = {
        'job_sheet': 'Example: 2025/0001',
        'mobile':    'Example: 01712345678'
    };
    searchBySelect.addEventListener('change', function() {
        queryInput.placeholder = placeholders[this.value] || '';
    });
    // Set correct placeholder on load
    queryInput.placeholder = placeholders[searchBySelect.value] || '';

    // Enter key triggers search
    queryInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btn.click();
    });

    function showError(msg) {
        resultBox.style.display = 'block';
        resultBox.innerHTML = '<div class="slb-error-box">' +
            '<i class="fa-solid fa-circle-exclamation"></i>' +
            '<span>' + slbEscapeHtml(msg) + '</span></div>';
    }

    btn.addEventListener('click', async function(){

        var search_by = searchBySelect.value;
        var query  = queryInput.value.trim();
        var serial = document.getElementById('slb_serial').value.trim();

        if (!query) {
            showError('Please enter a value to search.');
            resultBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.style.opacity = '0.7';
        loading.style.display = 'block';
        resultBox.style.display = 'none';
        resultBox.innerHTML = '';

        try {
            var url = SLB_REPAIR.rest_url
                + '?search_by=' + encodeURIComponent(search_by)
                + '&query='     + encodeURIComponent(query)
                + (serial ? '&serial=' + encodeURIComponent(serial) : '');

            var res  = await fetch(url);
            var data = await res.json();

            loading.style.display = 'none';

            if (!data.success) {
                showError(data.message || 'No record found.');
                btn.disabled = false;
                btn.style.opacity = '1';
                return;
            }

            var d          = data.data || {};
            var activities = (d.activities && Array.isArray(d.activities)) ? d.activities : [];

            // Build repair log rows — helper functions are defined above, outside this loop
            var rows = '';
            var timeline = '';
            for (var i = 0; i < activities.length; i++) {
                var a        = activities[i];
                var noteRaw  = a.note || '';
                var noteLimit = 120;
                var noteFullHtml  = slbNoteHtml(noteRaw);
                var needsToggle   = String(noteRaw).length > noteLimit;
                var noteShortHtml = needsToggle
                    ? slbNoteHtml(String(noteRaw).substring(0, noteLimit)) + '...'
                    : noteFullHtml;

                var noteData = encodeURIComponent(String(noteRaw));
                var note = '<div class="slb-note" data-full="' + noteData + '">' +
                           '<span class="slb-note-text">' + noteShortHtml + '</span>' +
                           (needsToggle ? '<a href="#" class="slb-note-toggle" data-state="more">Show more</a>' : '') +
                           '</div>';

                rows += '<tr>' +
                    '<td style="white-space:nowrap;color:#6b7280;font-size:13px;">' + slbEscapeHtml(a.date || '-') + '</td>' +
                    '<td style="font-weight:600;color:#1f2937;">'                   + slbEscapeHtml(a.action || '-') + '</td>' +
                    '<td style="white-space:nowrap;color:#4b5563;">'                + slbEscapeHtml(a.by || '-') + '</td>' +
                    '<td>' + note + '</td>' +
                    '</tr>';

                // The same entry drawn as a timeline row for phones. It gets its own
                // note element so the Show more toggle works in whichever view is
                // currently on screen.
                var tlNote = '<div class="slb-note slb-tl-note" data-full="' + noteData + '">' +
                             '<span class="slb-note-text">' + noteShortHtml + '</span>' +
                             (needsToggle ? ' <a href="#" class="slb-note-toggle" data-state="more">Show more</a>' : '') +
                             '</div>';
                var tlBy   = String(a.by || '').trim();
                var tlMeta = slbEscapeHtml(slbPrettyDate(a.date || '')) + (tlBy ? ' &middot; by ' + slbEscapeHtml(tlBy) : '');

                timeline += '<li class="slb-tl-item">' +
                    '<div class="slb-tl-action">' + slbEscapeHtml(a.action || '-') + '</div>' +
                    '<div class="slb-tl-meta">' + tlMeta + '</div>' +
                    (String(noteRaw).trim() ? tlNote : '') +
                    '</li>';
            }

            if (!rows) {
                rows = '<tr><td colspan="4" style="text-align:center;padding:30px;color:#9ca3af;">No activities logged yet</td></tr>';
                timeline = '<li class="slb-tl-empty">No activities logged yet</li>';
            }

            // Format estimated cost
            var formattedCost = '-';
            if (d.estimated_cost) {
                var costVal = parseFloat(d.estimated_cost);
                if (!isNaN(costVal)) {
                    formattedCost = '৳ ' + costVal.toLocaleString('en-IN', { maximumFractionDigits: 0 });
                }
            }

            // Format received date
            var receivedDate = d.created_at || (activities.length > 0 ? activities[activities.length - 1].date : '-');
            if (receivedDate && receivedDate !== '-') {
                var dObj = new Date(receivedDate.replace(/-/g, '/'));
                if (!isNaN(dObj.getTime())) {
                    receivedDate = dObj.toLocaleString('en-IN', {
                        day: '2-digit', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: true
                    });
                }
            }

            // Build document gallery
            var docsHtml = '';
            if (d.documents && d.documents.length > 0) {
                docsHtml += '<div class="slb-doc-gallery">';
                for (var j = 0; j < d.documents.length; j++) {
                    var docUrl = slbEscapeHtml(d.documents[j]);
                    docsHtml += '<a href="' + docUrl + '" target="_blank" rel="noopener noreferrer" class="slb-doc-thumb" data-img="' + docUrl + '">' +
                                '<img src="' + docUrl + '" alt="Attached document"></a>';
                }
                docsHtml += '</div>';
            }

            // Status badge with dynamic colour class
            var statusText  = slbEscapeHtml(d.status || '-');
            var statusClass = slbStatusClass(d.status || '');
            var statusBadge = '<span class="slb-status-badge ' + statusClass + '">' + statusText + '</span>';

            // Build "Problem Reported by Customer" pills from the array the ERP
            // returns. Falls back to plain text, then to a dash, if needed.
            var problemHtml = '-';
            if (Array.isArray(d.problem_reported) && d.problem_reported.length > 0) {
                problemHtml = '<div class="slb-pill-group">';
                for (var p = 0; p < d.problem_reported.length; p++) {
                    var pv = String(d.problem_reported[p] || '').trim();
                    if (pv) problemHtml += '<span class="slb-pill">' + slbEscapeHtml(pv) + '</span>';
                }
                problemHtml += '</div>';
            } else if (typeof d.problem_reported === 'string' && d.problem_reported.trim() !== '') {
                problemHtml = slbNl2Br(slbEscapeHtml(d.problem_reported));
            }

            // All ERP string fields are escaped before insertion — prevents XSS
            resultBox.innerHTML =
                '<div class="slb-result-header"><h3>Repair Status</h3></div>' +
                // The ERP was unreachable and this came from our own last-good copy.
                // Say so plainly rather than passing off old data as current.
                (data.stale ? '<div class="slb-stale-note">Showing the last information we have &mdash; ' +
                              'our repair system is briefly unavailable. Please check again shortly.</div>' : '') +
                '<div class="slb-data-grid">' +
                    '<div class="slb-data-item"><div class="slb-data-label">Current Status</div><div class="slb-data-value">' + statusBadge + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Received On</div><div class="slb-data-value">'         + slbEscapeHtml(receivedDate) + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Estimated Cost</div><div class="slb-data-value"><span class="slb-cost-highlight">' + slbEscapeHtml(formattedCost) + '</span></div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Customer Name</div><div class="slb-data-value">'        + slbEscapeHtml(d.customer_name || '-') + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Job Sheet No.</div><div class="slb-data-value">'        + slbEscapeHtml(d.job_sheet_no || '-') + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Device Model</div><div class="slb-data-value">'         + slbEscapeHtml(d.device_model || '-') + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Serial Number</div><div class="slb-data-value">'        + slbEscapeHtml(d.serial_number || '-') + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Order Number</div><div class="slb-data-value">'         + slbEscapeHtml(d.order_number || '-') + '</div></div>' +
                    '<div class="slb-data-item"><div class="slb-data-label">Warranty</div><div class="slb-data-value">'             + slbEscapeHtml(d.warranty || '-') + '</div></div>' +
                    '<div class="slb-data-item full-width"><div class="slb-data-label">Problem Reported by Customer</div><div class="slb-data-value">' + problemHtml + '</div></div>' +
                    '<div class="slb-data-item full-width"><div class="slb-data-label">Accessories Received</div><div class="slb-data-value">' + slbEscapeHtml(d.accessories_received || '-') + '</div></div>' +
                '</div>' +
                (docsHtml ? '<div class="slb-section-title">Attached Documents</div>' + docsHtml : '') +
                '<div class="slb-section-title">Repair Log</div>' +
                '<ol class="slb-timeline">' + timeline + '</ol>' +
                '<div class="slb-table-scroll"><table>' +
                    '<thead><tr><th>Date</th><th>Action</th><th>By</th><th>Note</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table></div>';

            resultBox.style.display = 'block';

            // Bind Show more / Show less toggles
            resultBox.querySelectorAll('.slb-note-toggle').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var wrap    = this.closest('.slb-note');
                    if (!wrap) return;
                    var fullRaw = decodeURIComponent(wrap.getAttribute('data-full') || '');
                    var textEl  = wrap.querySelector('.slb-note-text');
                    if (!textEl) return;
                    var state   = this.getAttribute('data-state') || 'more';
                    if (state === 'more') {
                        textEl.innerHTML = slbNoteHtml(fullRaw);
                        this.textContent = 'Show less';
                        this.setAttribute('data-state', 'less');
                    } else {
                        textEl.innerHTML = slbNoteHtml(fullRaw.substring(0, 120)) + (fullRaw.length > 120 ? '...' : '');
                        this.textContent = 'Show more';
                        this.setAttribute('data-state', 'more');
                    }
                });
            });

            // Bind lightbox
            resultBox.querySelectorAll('.slb-doc-thumb').forEach(function(thumb) {
                thumb.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('slb_lightbox_img').src = this.getAttribute('data-img');
                    document.getElementById('slb_lightbox').style.display = 'flex';
                });
            });

            btn.disabled = false;
            btn.style.opacity = '1';

        } catch(e) {
            console.error(e);
            loading.style.display = 'none';
            showError('Server connection error. Please try again later.');
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    });

    // Deep link support: /repair-status/?job=2025/0001 (short alias ?j= also works).
    // The SMS carries the job sheet number in the URL, so the page auto-fills
    // the form and runs the search — customer taps the link and sees the status
    // with zero typing. Without the parameter the page behaves exactly as before.
    try {
        var slbParams = new URLSearchParams(window.location.search);
        var slbJob = (slbParams.get('job') || slbParams.get('j') || '').trim();
        if (slbJob) {
            searchBySelect.value = 'job_sheet';
            searchBySelect.dispatchEvent(new Event('change'));
            queryInput.value = slbJob;
            btn.click();
            var slbCard = document.querySelector('.slb-repair-card');
            if (slbCard && slbCard.scrollIntoView) {
                slbCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    } catch(e) { console.error(e); }
});
EOD;

        wp_add_inline_script('slb-repair-tracker-inline', $js);
    }

    public function shortcode() {
        // Enqueue assets here rather than on every page via wp_enqueue_scripts —
        // this way CSS/JS only load on pages that contain the shortcode.
        $this->enqueue_assets();

        ob_start();
        ?>
        <div class="slb-repair-wrap">
            <div class="slb-repair-hero"></div>

            <div class="slb-repair-card">

                <div class="slb-form-row">
                    <label for="slb_search_by">Search By</label>
                    <select id="slb_search_by">
                        <option value="job_sheet">Job Sheet Number</option>
                        <option value="mobile">Mobile Number</option>
                    </select>
                </div>

                <div class="slb-form-row">
                    <label for="slb_query">Enter Value</label>
                    <input id="slb_query" type="text" placeholder="Example: 2025/0001">
                </div>

                <div class="slb-form-row">
                    <label for="slb_serial">Serial Number <span style="font-weight:400;color:#9ca3af">(optional)</span></label>
                    <input id="slb_serial" type="text" placeholder="For extra verification">
                </div>

                <button id="slb_check_btn" type="button" class="slb-check-btn">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Check Repair Status
                </button>

                <div id="slb_loading" class="slb-loading" style="display:none;">
                    Checking your repair status...
                </div>
                <div id="slb_result" class="slb-result" style="display:none;"></div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new SLB_Repair_Tracker();