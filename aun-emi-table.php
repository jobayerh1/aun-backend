<?php
/**
 * Plugin Name: AUN EMI Table
 * Description: Displays a dynamic EMI table on WooCommerce product pages with an admin settings panel.
 * Version:     2.1.0
 * Author:      Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_EMI_Table {

    /** Set to true once EMI content is rendered — gates asset output. */
    private bool $assets_needed = false;

    public function __construct() {
        add_action( 'admin_menu',                   [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init',                   [ $this, 'settings_init' ] );
        add_action( 'admin_enqueue_scripts',        [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'woocommerce_product_meta_end', [ $this, 'display_emi_feature' ] );
        add_action( 'wp_footer',                    [ $this, 'add_assets_to_footer' ] );
    }

    // -------------------------------------------------------------------------
    // Front-end display
    // -------------------------------------------------------------------------

    public function display_emi_feature() {
        global $product;
        if ( ! is_product() || ! $product || ! $product->get_price() ) return;

        $options       = get_option( 'aun_emi_settings', [] );
        $min_price     = isset( $options['min_price'] ) ? (float) $options['min_price'] : 5000;
        $product_price = (float) $product->get_price();

        if ( $product_price < $min_price ) {
            echo '<div class="aun-emi-no-emi">';
            echo '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>';
            echo '<span>EMI is not available for products under ';
            echo wp_kses_post( wc_price( $min_price ) );
            echo '.</span></div>';
            return;
        }

        $emi_data = $this->build_emi_data( $options );
        if ( empty( $emi_data ) ) return;

        // Mark that assets are needed — add_assets_to_footer() checks this flag.
        $this->assets_needed = true;

        $formatted_price = wp_kses_post( wc_price( $product_price ) );
        $modal_title_id  = 'aun-emi-title-' . $product->get_id();
        ?>

        <div class="aun-emi-trigger-wrap">
            <a href="#" class="aun-emi-trigger" aria-haspopup="dialog">
                <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                <span>View EMI Options</span>
            </a>
        </div>

        <div class="aun-emi-overlay"
             role="dialog"
             aria-modal="true"
             aria-labelledby="<?php echo esc_attr( $modal_title_id ); ?>"
             hidden>
            <div class="aun-emi-modal">

                <div class="aun-emi-modal-hd">
                    <h3 class="aun-emi-modal-title" id="<?php echo esc_attr( $modal_title_id ); ?>">
                        <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                        EMI Options
                    </h3>
                    <button type="button" class="aun-emi-close" aria-label="Close EMI options">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="aun-emi-body">
                    <nav class="aun-emi-sidebar" aria-label="Select bank">
                        <ul>
                            <?php $first = true; foreach ( $emi_data as $slug => $bank ) : ?>
                            <li>
                                <button type="button"
                                        class="aun-emi-bank-btn<?php echo $first ? ' active' : ''; ?>"
                                        data-bank="<?php echo esc_attr( $slug ); ?>"
                                        aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                    <?php echo esc_html( $bank['name'] ); ?>
                                </button>
                            </li>
                            <?php $first = false; endforeach; ?>
                        </ul>
                    </nav>

                    <div class="aun-emi-panels">
                        <?php $first = true; foreach ( $emi_data as $slug => $bank ) : ?>
                        <div class="aun-emi-panel<?php echo $first ? ' active' : ''; ?>"
                             id="aun-emi-panel-<?php echo esc_attr( $slug ); ?>"
                             role="tabpanel">
                            <div class="aun-emi-panel-hd">
                                <span class="aun-emi-bank-name"><?php echo esc_html( $bank['name'] ); ?></span>
                                <span class="aun-emi-price">Price: <?php echo $formatted_price; ?></span>
                            </div>
                            <div class="aun-emi-table-wrap">
                                <table class="aun-emi-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Months</th>
                                            <th scope="col">Interest</th>
                                            <th scope="col">Per month</th>
                                            <th scope="col">Total cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $bank['tenures'] as $months => $rate ) :
                                            $total_interest = $product_price * ( $rate / 100 );
                                            $effective_cost = $product_price + $total_interest;
                                            $per_month      = $effective_cost / $months;
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $months ); ?> months</td>
                                            <td><?php echo esc_html( number_format( (float) $rate, 2 ) ); ?>%</td>
                                            <td><?php echo wp_kses_post( wc_price( $per_month ) ); ?></td>
                                            <td><?php echo wp_kses_post( wc_price( $effective_cost ) ); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Normalises the raw settings array into a clean keyed structure.
     * Banks with enabled === 0 are silently excluded from the front-end.
     * Banks without an 'enabled' key (pre-2.1 data) are treated as enabled.
     */
    private function build_emi_data( array $options ): array {
        $emi_data = [];
        if ( empty( $options['banks'] ) ) return $emi_data;

        foreach ( $options['banks'] as $bank ) {
            // Skip disabled banks — missing key means enabled (backward compat)
            if ( isset( $bank['enabled'] ) && (int) $bank['enabled'] === 0 ) continue;

            $slug    = sanitize_title( $bank['name'] );
            $tenures = [];
            if ( ! empty( $bank['tenures'] ) ) {
                foreach ( $bank['tenures'] as $t ) {
                    $tenures[ (int) $t['months'] ] = (float) $t['rate'];
                }
            }
            $emi_data[ $slug ] = [ 'name' => $bank['name'], 'tenures' => $tenures ];
        }
        return $emi_data;
    }

    // -------------------------------------------------------------------------
    // Assets — only output when EMI content was actually rendered
    // -------------------------------------------------------------------------

    public function add_assets_to_footer() {
        if ( ! $this->assets_needed ) return;
        ?>
        <style>
        /* ── AUN EMI Table v2.0 ────────────────────────────────────── */
        .aun-emi-trigger-wrap { margin-bottom: 15px; }

        .aun-emi-trigger {
            color: #1976D2; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; font-size: 1em;
            transition: color .15s;
        }
        .aun-emi-trigger:hover { color: #0d47a1; text-decoration: underline; }
        .aun-emi-trigger i { margin-right: 8px; font-size: 1.2em; }

        .aun-emi-no-emi {
            margin-top: 10px; display: inline-flex;
            align-items: center; gap: 7px;
            font-size: 0.9em; color: #777;
        }

        /* ── Overlay ── */
        .aun-emi-overlay {
            position: fixed; inset: 0; z-index: 10000;
            background: rgba(0,0,0,.55);
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            opacity: 0; transition: opacity .2s ease;
        }
        .aun-emi-overlay[hidden] { display: none; }
        .aun-emi-overlay.is-open { opacity: 1; }

        /* ── Modal shell ── */
        .aun-emi-modal {
            background: #fff; border-radius: 12px;
            width: 100%; max-width: 660px; max-height: 72vh;
            display: flex; flex-direction: column; overflow: hidden;
            transform: scale(.96); transition: transform .2s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
        }
        .aun-emi-overlay.is-open .aun-emi-modal { transform: scale(1); }

        /* ── Modal header ── */
        .aun-emi-modal-hd {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 20px; border-bottom: 1px solid #e2e8f0; flex-shrink: 0;
        }
        .aun-emi-modal-title {
            font-size: 16px; font-weight: 700; margin: 0; color: #0f172a;
            display: flex; align-items: center; gap: 8px;
        }
        .aun-emi-modal-title i { color: #0188fe; font-size: 15px; }

        .aun-emi-close {
            width: 32px; height: 32px; border: none; border-radius: 6px;
            background: #f1f5f9; cursor: pointer; color: #64748b; font-size: 15px;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s, color .15s; padding: 0;
        }
        .aun-emi-close:hover { background: #e2e8f0; color: #0f172a; }

        /* ── Modal body ── */
        .aun-emi-body { display: flex; flex: 1; overflow: hidden; min-height: 0; }

        /* ── Sidebar (bank list) ── */
        .aun-emi-sidebar {
            width: 180px; flex-shrink: 0;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto; overflow-x: hidden;
        }
        .aun-emi-sidebar ul { list-style: none; margin: 0; padding: 8px 0; }
        .aun-emi-sidebar li { margin: 0; }

        .aun-emi-bank-btn {
            width: 100%; text-align: left; background: none; border: none;
            cursor: pointer; padding: 11px 14px; font-size: 13px; font-weight: 500;
            color: #334155; border-left: 3px solid transparent;
            word-break: break-word; overflow-wrap: break-word; line-height: 1.4;
            transition: background .12s, color .12s, border-color .12s;
        }
        .aun-emi-bank-btn:hover { background: #f8fafc; color: #0188fe; }
        .aun-emi-bank-btn.active {
            background: #eff6ff; color: #0188fe;
            border-left-color: #0188fe; font-weight: 700;
        }

        /* ── Content panels ── */
        .aun-emi-panels { flex: 1; overflow-y: auto; padding: 20px; }
        .aun-emi-panel { display: none; }
        .aun-emi-panel.active { display: block; }

        .aun-emi-panel-hd {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 14px; padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; gap: 8px;
        }
        .aun-emi-bank-name { font-size: 15px; font-weight: 700; color: #0f172a; }
        .aun-emi-price     { font-size: 13px; color: #64748b; }

        /* ── Table ── */
        .aun-emi-table-wrap { overflow-x: auto; }
        .aun-emi-table { width: 100%; border-collapse: collapse; font-size: 14px; }

        .aun-emi-table th {
            background: #f8fafc; padding: 10px 12px; text-align: left;
            font-size: 11px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        .aun-emi-table td {
            padding: 10px 12px; border-bottom: 1px solid #f1f5f9;
            color: #1e293b; font-size: 13.5px;
        }
        .aun-emi-table tbody tr:hover td { background: #f8fafc; }
        .aun-emi-table tbody tr:last-child td { border-bottom: none; }

        /* ── Mobile ── */
        @media (max-width: 640px) {
            .aun-emi-body { flex-direction: column; }
            .aun-emi-sidebar {
                width: 100%; border-right: none;
                border-bottom: 1px solid #e2e8f0; max-height: 110px;
            }
            .aun-emi-sidebar ul {
                display: flex; flex-wrap: nowrap; overflow-x: auto;
                padding: 6px; gap: 6px;
            }
            .aun-emi-sidebar li { flex-shrink: 0; }
            .aun-emi-bank-btn {
                padding: 6px 12px; border-left: none;
                border-bottom: 3px solid transparent;
                border-radius: 6px; white-space: nowrap; font-size: 12.5px;
            }
            .aun-emi-bank-btn.active {
                background: #eff6ff;
                border-bottom-color: #0188fe; border-left-color: transparent;
            }
            .aun-emi-panels { padding: 14px; }
        }
        </style>

        <script>
        (function () {
            var overlay  = document.querySelector('.aun-emi-overlay');
            var trigger  = document.querySelector('.aun-emi-trigger');
            var closeBtn = document.querySelector('.aun-emi-close');
            if (!overlay || !trigger) return;

            var bankBtns = overlay.querySelectorAll('.aun-emi-bank-btn');
            var panels   = overlay.querySelectorAll('.aun-emi-panel');

            /* ── Open ── */
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                overlay.removeAttribute('hidden');
                requestAnimationFrame(function () {
                    overlay.classList.add('is-open');
                });
                document.body.style.overflow = 'hidden';
                closeBtn.focus();
            });

            /* ── Close ── */
            function closeModal() {
                overlay.classList.remove('is-open');
                overlay.addEventListener('transitionend', function onEnd() {
                    overlay.setAttribute('hidden', '');
                    overlay.removeEventListener('transitionend', onEnd);
                }, { once: true });
                document.body.style.overflow = '';
                trigger.focus();
            }

            closeBtn.addEventListener('click', closeModal);

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) closeModal();
            });

            /* ── Bank switching ── */
            bankBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    bankBtns.forEach(function (b) {
                        b.classList.remove('active');
                        b.setAttribute('aria-selected', 'false');
                    });
                    btn.classList.add('active');
                    btn.setAttribute('aria-selected', 'true');

                    var target = btn.dataset.bank;
                    panels.forEach(function (p) { p.classList.remove('active'); });
                    var panel = overlay.querySelector('#aun-emi-panel-' + target);
                    if (panel) panel.classList.add('active');
                });
            });

            /* ── Focus trap ── */
            overlay.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab' || overlay.hasAttribute('hidden')) return;
                var focusable = overlay.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                var first = focusable[0];
                var last  = focusable[focusable.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                } else {
                    if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
                }
            });
        })();
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function add_admin_menu() {
        add_options_page(
            'AUN EMI Settings', 'AUN EMI Settings',
            'manage_options', 'aun_emi_settings',
            [ $this, 'options_page_html' ]
        );
    }

    public function settings_init() {
        register_setting( 'aun_emi_page', 'aun_emi_settings', [ $this, 'sanitize_settings' ] );
    }

    /**
     * Enqueue jquery-ui-sortable (WordPress-bundled) only on our settings page.
     * Must run via admin_enqueue_scripts — page callbacks fire after <head> is output,
     * so wp_enqueue_script() inside options_page_html() would be too late.
     */
    public function enqueue_admin_scripts( string $hook ) {
        if ( $hook !== 'settings_page_aun_emi_settings' ) return;
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_style( 'dashicons' );
    }

    public function options_page_html() {
        // Re-verify capability — WordPress shows admin pages to any user who
        // finds the URL if this check is not present in the callback itself.
        if ( ! current_user_can( 'manage_options' ) ) return;

        $options   = get_option( 'aun_emi_settings', [] );
        $min_price = isset( $options['min_price'] ) ? $options['min_price'] : '5000';
        $banks     = isset( $options['banks'] ) ? $options['banks']
                     : [ [ 'name' => '', 'tenures' => [ [ 'months' => '', 'rate' => '' ] ] ] ];
        ?>
        <div class="wrap">
            <h1>AUN EMI Table Settings</h1>
            <form action="options.php" method="post">
                <?php settings_fields( 'aun_emi_page' ); ?>

                <h2>General settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aun_emi_min_price">Minimum price for EMI</label>
                        </th>
                        <td>
                            <input name="aun_emi_settings[min_price]" type="number"
                                   id="aun_emi_min_price" value="<?php echo esc_attr( $min_price ); ?>"
                                   class="regular-text" placeholder="e.g. 5000">
                            <p class="description">Products below this price will show a "no EMI" notice.</p>
                        </td>
                    </tr>
                </table>

                <hr>
                <h2>Bank configuration</h2>
                <div id="aun-banks-wrapper">
                    <?php foreach ( $banks as $bi => $bank ) : ?>
                    <?php
                    // enabled defaults to true for backward compatibility
                    $is_enabled = ! isset( $bank['enabled'] ) || (int) $bank['enabled'] === 1;
                    ?>
                    <div class="bank-group<?php echo $is_enabled ? '' : ' is-disabled'; ?>">
                        <div class="bank-header">
                            <div class="bank-header-left">
                                <span class="bank-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
                                <h3>Bank <?php echo esc_html( $bi + 1 ); ?></h3>
                            </div>
                            <div class="bank-header-right">
                                <label class="aun-bank-toggle" title="Enable or disable this bank">
                                    <input type="checkbox"
                                           class="aun-bank-enabled"
                                           name="aun_emi_settings[banks][<?php echo $bi; ?>][enabled]"
                                           value="1"
                                           <?php checked( $is_enabled ); ?>>
                                    <span class="aun-toggle-track">
                                        <span class="aun-toggle-thumb"></span>
                                    </span>
                                    <span class="aun-toggle-label"><?php echo $is_enabled ? 'Active' : 'Disabled'; ?></span>
                                </label>
                                <button type="button" class="button aun-remove-bank">Remove bank</button>
                            </div>
                        </div>
                        <p>
                            <label>Bank name:
                                <input type="text"
                                       class="aun-bank-name-input"
                                       name="aun_emi_settings[banks][<?php echo $bi; ?>][name]"
                                       value="<?php echo esc_attr( $bank['name'] ); ?>">
                            </label>
                        </p>
                        <h4>Tenures</h4>
                        <div class="tenures-wrapper">
                            <?php foreach ( $bank['tenures'] as $ti => $tenure ) : ?>
                            <div class="tenure-group">
                                <label>Months:
                                    <input type="number" min="1"
                                           name="aun_emi_settings[banks][<?php echo $bi; ?>][tenures][<?php echo $ti; ?>][months]"
                                           value="<?php echo esc_attr( $tenure['months'] ); ?>">
                                </label>
                                <label>Interest rate (%):
                                    <input type="number" step="0.01" min="0"
                                           name="aun_emi_settings[banks][<?php echo $bi; ?>][tenures][<?php echo $ti; ?>][rate]"
                                           value="<?php echo esc_attr( $tenure['rate'] ); ?>">
                                </label>
                                <button type="button" class="button aun-remove-tenure">Remove</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button aun-add-tenure">Add tenure</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex; gap:8px; margin-top:10px; align-items:center; flex-wrap:wrap;">
                    <button type="button" id="aun-add-bank" class="button button-primary">
                        + Add another bank
                    </button>
                    <button type="button" id="aun-sort-alpha" class="button">
                        <span class="dashicons dashicons-sort" style="vertical-align:middle;margin-right:4px;margin-top:3px;font-size:16px;"></span>
                        Sort A–Z
                    </button>
                </div>
                <?php submit_button( 'Save EMI settings' ); ?>
            </form>
        </div>

        <style>
        #aun-banks-wrapper { cursor: default; }

        .bank-group {
            padding: 15px; border: 1px solid #ccd0d4;
            margin-bottom: 12px; background: #fff; border-radius: 4px;
            transition: opacity .2s;
        }
        .bank-group.is-disabled { opacity: .45; }

        /* Faded placeholder shown where the dragged card will drop */
        .bank-group-placeholder {
            border: 2px dashed #0188fe; background: #f0f7ff;
            border-radius: 4px; margin-bottom: 12px;
        }
        /* Slight visual lift while dragging */
        .bank-group.ui-sortable-helper {
            box-shadow: 0 6px 20px rgba(0,0,0,.15);
            border-color: #0188fe; opacity: .95;
        }

        .bank-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;
            flex-wrap: wrap; gap: 8px;
        }
        .bank-header-left  { display: flex; align-items: center; gap: 8px; }
        .bank-header-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .bank-header h3 { margin: 0; }

        .bank-drag-handle {
            color: #aaa; cursor: grab; font-size: 20px;
            line-height: 1; user-select: none; transition: color .15s;
        }
        .bank-drag-handle:hover { color: #0188fe; }
        .bank-group.ui-sortable-helper .bank-drag-handle { cursor: grabbing; }

        /* ── Toggle switch ── */
        .aun-bank-toggle {
            display: inline-flex; align-items: center; gap: 7px;
            cursor: pointer; user-select: none;
        }
        .aun-bank-toggle input[type="checkbox"] {
            position: absolute; opacity: 0; width: 0; height: 0;
        }
        .aun-toggle-track {
            width: 40px; height: 22px; border-radius: 11px;
            background: #ccc; position: relative;
            transition: background .2s; flex-shrink: 0;
        }
        .aun-toggle-thumb {
            position: absolute; top: 3px; left: 3px;
            width: 16px; height: 16px; border-radius: 50%;
            background: #fff; transition: left .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.3);
        }
        .aun-bank-toggle input:checked ~ .aun-toggle-track { background: #0188fe; }
        .aun-bank-toggle input:checked ~ .aun-toggle-track .aun-toggle-thumb { left: 21px; }

        .aun-toggle-label {
            font-size: 13px; color: #555; min-width: 48px; font-weight: 500;
        }
        .bank-group:not(.is-disabled) .aun-toggle-label { color: #0a7c2c; }
        .bank-group.is-disabled .aun-toggle-label { color: #999; }

        .tenure-group {
            display: flex; gap: 15px; padding: 10px;
            border: 1px dashed #ddd; margin-bottom: 10px;
            align-items: center; flex-wrap: wrap;
        }
        </style>

        <script>
        jQuery(document).ready(function ($) {

            /* ── Core utility: re-number every bank's input names and headers
               after any reorder (drag-drop or sort-alpha or add/remove).     */
            function reindexBanks() {
                $('#aun-banks-wrapper .bank-group').each(function (i) {
                    // Update visible heading
                    $(this).find('.bank-header h3').text('Bank ' + (i + 1));
                    // Fix [banks][N] in every input name inside this card
                    $(this).find('input').each(function () {
                        if (this.name) {
                            this.name = this.name.replace(
                                /\[banks\]\[\d+\]/,
                                '[banks][' + i + ']'
                            );
                        }
                    });
                });
            }

            /* ── Enable / Disable toggle ── */
            $('#aun-banks-wrapper').on('change', '.aun-bank-enabled', function () {
                var $group = $(this).closest('.bank-group');
                var $label = $group.find('.aun-toggle-label');
                if ($(this).is(':checked')) {
                    $group.removeClass('is-disabled');
                    $label.text('Active');
                } else {
                    $group.addClass('is-disabled');
                    $label.text('Disabled');
                }
            });

            /* ── Drag-to-reorder ── */
            $('#aun-banks-wrapper').sortable({
                handle:                '.bank-drag-handle',
                placeholder:           'bank-group-placeholder',
                forcePlaceholderSize:  true,
                tolerance:             'pointer',
                update: function () {
                    reindexBanks();
                }
            });

            /* ── Sort A–Z ── */
            $('#aun-sort-alpha').on('click', function () {
                var $wrapper = $('#aun-banks-wrapper');
                var groups   = $wrapper.find('.bank-group').detach().toArray();

                groups.sort(function (a, b) {
                    var na = $(a).find('.aun-bank-name-input').val().trim().toLowerCase();
                    var nb = $(b).find('.aun-bank-name-input').val().trim().toLowerCase();
                    return na.localeCompare(nb);
                });

                $.each(groups, function (_, el) { $wrapper.append(el); });
                reindexBanks();
            });

            /* ── Add bank ── */
            $('#aun-add-bank').on('click', function () {
                var count = $('.bank-group').length;
                var $new  = $('.bank-group:first').clone();

                $new.find('.bank-header h3').text('Bank ' + (count + 1));
                $new.find('input[type="text"], input[type="number"]').val('');
                $new.find('.tenure-group:not(:first)').remove();
                // New banks always start enabled
                $new.removeClass('is-disabled');
                $new.find('.aun-bank-enabled').prop('checked', true);
                $new.find('.aun-toggle-label').text('Active');

                // Temporarily stamp the new index so reindexBanks() can normalise it
                $new.find('input').each(function () {
                    if (this.name) {
                        this.name = this.name
                            .replace(/\[banks\]\[\d+\]/, '[banks][' + count + ']')
                            .replace(/\[tenures\]\[\d+\]/, '[tenures][0]');
                    }
                });

                $('#aun-banks-wrapper').append($new);
                reindexBanks();
            });

            /* ── Remove bank ── */
            $('#aun-banks-wrapper').on('click', '.aun-remove-bank', function () {
                if ($('.bank-group').length > 1) {
                    $(this).closest('.bank-group').remove();
                    reindexBanks();
                } else {
                    alert('You must have at least one bank.');
                }
            });

            /* ── Add tenure ── */
            $('#aun-banks-wrapper').on('click', '.aun-add-tenure', function () {
                var $bankGroup  = $(this).closest('.bank-group');
                var bankIndex   = $bankGroup.index();
                var tenureCount = $bankGroup.find('.tenure-group').length;
                var $new        = $bankGroup.find('.tenure-group:first').clone();

                $new.find('input').val('');
                $new.find('input').each(function () {
                    if (this.name) {
                        this.name = this.name
                            .replace(/\[banks\]\[\d+\]/, '[banks][' + bankIndex + ']')
                            .replace(/\[tenures\]\[\d+\]/, '[tenures][' + tenureCount + ']');
                    }
                });

                $bankGroup.find('.tenures-wrapper').append($new);
            });

            /* ── Remove tenure ── */
            $('#aun-banks-wrapper').on('click', '.aun-remove-tenure', function () {
                var $wrapper = $(this).closest('.tenures-wrapper');
                if ($wrapper.find('.tenure-group').length > 1) {
                    $(this).closest('.tenure-group').remove();
                } else {
                    alert('Each bank must have at least one tenure.');
                }
            });
        });
        </script>
        <?php
    }

    public function sanitize_settings( $input ): array {
        $output = [];
        $output['min_price'] = isset( $input['min_price'] ) ? absint( $input['min_price'] ) : 5000;

        if ( isset( $input['banks'] ) && is_array( $input['banks'] ) ) {
            $banks = [];
            foreach ( $input['banks'] as $bank ) {
                if ( empty( $bank['name'] ) ) continue;

                $tenures = [];
                if ( ! empty( $bank['tenures'] ) && is_array( $bank['tenures'] ) ) {
                    foreach ( $bank['tenures'] as $tenure ) {
                        $months = absint( $tenure['months'] ?? 0 );
                        $rate   = (float) ( $tenure['rate'] ?? 0 );
                        // Validate: months must be a positive integer, rate must be non-negative
                        if ( $months < 1 || $rate < 0 ) continue;
                        $tenures[] = [ 'months' => $months, 'rate' => $rate ];
                    }
                }
                if ( empty( $tenures ) ) continue;

                $banks[] = [
                    'name'    => sanitize_text_field( $bank['name'] ),
                    'enabled' => isset( $bank['enabled'] ) ? 1 : 0,
                    'tenures' => $tenures,
                ];
            }
            $output['banks'] = $banks;
        }

        return $output;
    }
}

new AUN_EMI_Table();