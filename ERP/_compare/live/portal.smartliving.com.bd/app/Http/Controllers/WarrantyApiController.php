<?php
/**
 * AUN Warranty Serial API Controller
 *
 * Adds a lightweight read-only endpoint to UltimatePOS that returns serial
 * numbers from completed dealer sales so the WordPress warranty plugin can
 * import them automatically.
 *
 * INSTALLATION
 * ─────────────
 * 1. Copy this file to:  app/Http/Controllers/WarrantyApiController.php
 *
 * 2. Add ONE line to routes/api.php  (at the bottom, before the closing ?>):
 *       Route::get('/warranty-serials', [\App\Http\Controllers\WarrantyApiController::class, 'getSerials']);
 *
 * 3. Add ONE line to your .env file:
 *       WARRANTY_API_SECRET=change-this-to-a-long-random-string
 *    Use the same string in the WordPress plugin's ERP Sync settings.
 *
 * 4. No other files need to be changed.
 *
 * ENDPOINT
 * ─────────
 * GET /api/warranty-serials
 *
 * Required header:
 *   X-Warranty-Secret: {WARRANTY_API_SECRET from .env}
 *
 * Optional query parameters:
 *   since           — ISO date (Y-m-d). Returns sales on or after this date.
 *                     Defaults to 7 days ago.
 *   dealer_group_id — ID of the customer group to filter (e.g. your Dealers group).
 *                     If omitted or 0, returns all contacts with serial numbers.
 *   per_page        — Max records per call. Defaults to 200.
 *
 * RESPONSE
 * ─────────
 * {
 *   "success": true,
 *   "count": 3,
 *   "since": "2025-05-01",
 *   "data": [
 *     {
 *       "serial":         "100000000001",
 *       "transaction_id": 412,
 *       "invoice_no":     "INV/2025/0042",
 *       "sale_date":      "2025-05-15 11:30:00",
 *       "contact_id":     160,
 *       "dealer_name":    "Dhaka Electronics",
 *       "product_id":     7,
 *       "product_name":   "A005 Projector",
 *       "product_sku":    "APB-A005-GRY"
 *     },
 *     ...
 *   ]
 * }
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarrantyApiController extends Controller
{
    /**
     * Return a list of serial numbers from completed dealer sales.
     */
    public function getSerials(Request $request)
    {
        // ── Authentication ────────────────────────────────────────────────
        $expected = env('WARRANTY_API_SECRET', '');
        $provided = $request->header('X-Warranty-Secret', '');

        if ( empty($expected) ) {
            // Secret not configured in .env — refuse all requests for safety
            Log::warning('WarrantyApiController: WARRANTY_API_SECRET is not set in .env');
            return response()->json([
                'success' => false,
                'message' => 'API secret not configured on server.',
            ], 503);
        }

        if ( ! hash_equals($expected, $provided) ) {
            Log::warning('WarrantyApiController: invalid secret from IP ' . $request->ip());
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // ── Parameters ───────────────────────────────────────────────────
        $since_raw = $request->get('since', '');
        if ( $since_raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $since_raw) ) {
            $since = $since_raw;
        } else {
            $since = date('Y-m-d', strtotime('-7 days'));
        }

        $dealer_group_id = (int) $request->get('dealer_group_id', 0);
        $per_page        = min(500, max(1, (int) $request->get('per_page', 200)));
        $offset          = max(0, (int) $request->get('offset', 0));

        // ── Query ─────────────────────────────────────────────────────────
        // Your UltimatePOS installation stores serial numbers in
        // transaction_sell_lines.sell_line_note as a free-text textarea.
        // Multiple serials per line are separated by newlines (confirmed from DB screenshot).
        $query = DB::table('transactions as t')
            ->join('contacts as c',                  't.contact_id',       '=', 'c.id')
            ->join('transaction_sell_lines as tsl',  'tsl.transaction_id', '=', 't.id')
            ->join('products as p',                  'tsl.product_id',     '=', 'p.id')
            ->where('t.type',   'sell')
            ->where('t.status', 'final')
            ->whereNotNull('tsl.sell_line_note')
            ->where('tsl.sell_line_note', '!=', '')
            // Filter by updated_at, NOT transaction_date. A draft keeps its
            // original transaction_date when later finalised, and editing a
            // sale to add a missed serial never changes transaction_date — so
            // filtering by transaction_date silently drops both. updated_at is
            // bumped whenever the sale is finalised or edited, so the hourly
            // sync re-picks it up and the WordPress side de-dupes by serial.
            ->where('t.updated_at', '>=', $since . ' 00:00:00')
            ->select([
                't.id as transaction_id',
                't.invoice_no',
                't.transaction_date as sale_date',
                't.contact_id',
                'c.name as dealer_name',
                'tsl.product_id',
                'p.name as product_name',
                'p.sku  as product_sku',
                'tsl.sell_line_note',
            ])
            // Order by updated_at to match the filter above, so the sync
            // cursor stays consistent with the column being paged through.
            // tsl.id is a deterministic tie-breaker so offset paging is stable.
            ->orderBy('t.updated_at', 'asc')
            ->orderBy('tsl.id', 'asc')
            ->offset($offset)
            ->limit($per_page);

        // Filter by dealer customer group if specified
        if ( $dealer_group_id > 0 ) {
            $query->where('c.customer_group_id', $dealer_group_id);
        }

        $rows      = $query->get();
        // Number of raw joined rows in THIS page (before serial splitting).
        // The client pages on this, not on the post-split serial count.
        $row_count = $rows->count();
        $has_more  = $row_count === $per_page;

        // ── Parse serials ─────────────────────────────────────────────────
        // sell_line_note is a textarea — multiple serials separated by newlines.
        // We split and return each one as a separate record.
        $result = [];

        foreach ( $rows as $row ) {
            $raw     = trim( $row->sell_line_note );
            // Split on ANY whitespace (space, tab, newline) as well as commas
            // and semicolons. Staff separate multiple serials with either a
            // space or a new line; the old pattern missed spaces, which glued
            // two serials into one unparseable string and dropped both.
            $serials = preg_split( '/[\s,;]+/', $raw );

            foreach ( $serials as $serial ) {
                $serial = trim( $serial );
                if ( $serial === '' ) continue;

                // Only accept strings that look like a numeric serial number
                // (6–15 digits). This filters out any descriptive notes that
                // staff may have typed into the sell_line_note field.
                if ( ! preg_match( '/^\d{6,15}$/', $serial ) ) continue;

                $result[] = [
                    'serial'         => $serial,
                    'transaction_id' => (int) $row->transaction_id,
                    'invoice_no'     => $row->invoice_no,
                    'sale_date'      => $row->sale_date,
                    'contact_id'     => (int) $row->contact_id,
                    'dealer_name'    => $row->dealer_name,
                    'product_id'     => (int) $row->product_id,
                    'product_name'   => $row->product_name,
                    'product_sku'    => trim( $row->product_sku ),
                ];
            }
        }

        Log::info('WarrantyApiController: returned ' . count($result) . ' serials since ' . $since);

        return response()->json([
            'success'  => true,
            'count'    => count( $result ),   // serials in this page (after splitting)
            'rows'     => $row_count,          // raw joined rows in this page (for paging)
            'per_page' => $per_page,
            'offset'   => $offset,
            'has_more' => $has_more,           // true => client should fetch next page
            'since'    => $since,
            'data'     => $result,
        ]);
    }
}
