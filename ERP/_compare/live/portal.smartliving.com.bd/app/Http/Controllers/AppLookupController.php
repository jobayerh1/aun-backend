<?php
/**
 * AUN App — ERP lookup endpoints (serial + customer phone)
 *
 * Companion to WarrantyApiController.php: lets the customer app resolve a
 * box barcode/serial against EVERY finalised sale (dealer AND direct), and
 * list a customer's own purchases by phone number.
 *
 * INSTALLATION (same pattern as the warranty controller)
 * ──────────────────────────────────────────────────────
 * 1. Copy this file to:  app/Http/Controllers/AppLookupController.php
 *
 * 2. Add TWO lines to routes/api.php:
 *      Route::get('/app-lookup/serial', [\App\Http\Controllers\AppLookupController::class, 'bySerial']);
 *      Route::get('/app-lookup/phone',  [\App\Http\Controllers\AppLookupController::class, 'byPhone']);
 *
 * 3. Uses the SAME .env secret:  WARRANTY_API_SECRET
 *    (header X-Warranty-Secret — already configured for the serial sync)
 *
 * ENDPOINTS
 * ─────────
 * GET /api/app-lookup/serial?serial=100000000001
 *   → { success, found, data: { serial, sale_date, invoice_no, contact_id,
 *       contact_name, contact_mobile, customer_group_id, product_name, product_sku,
 *       maintenance, warranty_duration, warranty_unit, returned, qty, qty_returned } }
 *   NOTE: a fully RETURNED unit is still reported (found:true, returned:true) so
 *   the app can tell "given back" apart from "sale deleted" and react precisely.
 *
 * GET /api/app-lookup/phone?phone=1712345678     (last 10 digits)
 *   → { success, count, data: [ ...same rows, one per serial... ] }
 *   Returned units are omitted here — they are not purchases any more.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppLookupController extends Controller
{
    /** Shared secret check (same as WarrantyApiController). */
    private function authorize_request(Request $request)
    {
        $expected = env('WARRANTY_API_SECRET', '');
        $provided = $request->header('X-Warranty-Secret', '');

        if (empty($expected)) {
            return response()->json(['success' => false, 'message' => 'API secret not configured.'], 503);
        }
        if (!hash_equals($expected, $provided)) {
            Log::warning('AppLookupController: invalid secret from IP ' . $request->ip());
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }
        return null;
    }

    /** Base query: finalised sales with serial notes. */
    private function baseQuery()
    {
        return DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->leftJoin('warranties as w', 'w.id', '=', 'p.warranty_id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNotNull('tsl.sell_line_note')
            ->where('tsl.sell_line_note', '!=', '')
            ->select(
                't.id as transaction_id',
                't.invoice_no',
                't.transaction_date',
                'c.id as contact_id',
                'c.name as contact_name',
                'c.mobile as contact_mobile',
                'c.customer_group_id',
                'p.id as product_id',
                'p.name as product_name',
                'p.sku as product_sku',
                // Dust-filter maintenance flag (same marker the maintenance
                // SMS service checks) + the product's warranty as printed on
                // the sales invoice — the app must never invent durations.
                'p.product_custom_field1 as product_flag1',
                'w.duration as warranty_duration',
                'w.duration_type as warranty_duration_type',
                // Sell-return state. A return does NOT delete or unfinalise the
                // sale: TransactionUtil::addSellReturn() writes the returned
                // quantity onto this very line (and adds a separate
                // type='sell_return' transaction). So the line itself is the
                // authoritative "is this unit still sold?" signal.
                'tsl.quantity',
                'tsl.quantity_returned',
                'tsl.sell_line_note'
            );
    }

    /**
     * Whether a sell line is FULLY returned.
     *
     * Deliberately full-line only: one line can carry several serials in its
     * note, and a partial return does not say WHICH unit came back — so a
     * partial return never unlinks anything (a false positive would strip a
     * real customer's warranty; a false negative just keeps today's behaviour).
     */
    private function isReturned($line): bool
    {
        $qty      = (float) ($line->quantity ?? 0);
        $returned = (float) ($line->quantity_returned ?? 0);

        return $qty > 0 && $returned >= $qty;
    }

    /** Shape one line+serial into the response row. */
    private function row($line, string $serial): array
    {
        return [
            'serial'            => $serial,
            'sale_date'         => (string) $line->transaction_date,
            'invoice_no'        => (string) $line->invoice_no,
            'contact_id'        => (int) $line->contact_id,
            'contact_name'      => (string) $line->contact_name,
            'contact_mobile'    => (string) $line->contact_mobile,
            'customer_group_id' => (int) ($line->customer_group_id ?? 0),
            // The ERP product id — the app maps it EXACTLY to a WP product via
            // slb_products.erp_product_id (never guesses by name), so the
            // customer always gets the right model's firmware/manuals.
            'product_id'        => (int) $line->product_id,
            'product_name'      => (string) $line->product_name,
            'product_sku'       => (string) $line->product_sku,
            // Maintenance (dust-filter) reminder eligibility — identical rule
            // to MaintenanceSmsService (product_custom_field1 === 'MAINT_SMS').
            'maintenance'       => strtoupper(trim((string) ($line->product_flag1 ?? ''))) === 'MAINT_SMS',
            // Warranty exactly as configured on the product (invoice source).
            'warranty_duration' => $line->warranty_duration !== null ? (int) $line->warranty_duration : null,
            'warranty_unit'     => (string) ($line->warranty_duration_type ?? ''),
            // The customer gave this unit back (full line return) — the app
            // unlinks it, stops its reminders and frees the serial for resale.
            'returned'          => $this->isReturned($line),
            'qty'               => (float) ($line->quantity ?? 0),
            'qty_returned'      => (float) ($line->quantity_returned ?? 0),
        ];
    }

    /** Serials live in sell_line_note as free text, one per line. */
    private function noteSerials($note): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', (string) $note) as $token) {
            $token = strtoupper(trim($token));
            if ($token !== '' && strlen($token) >= 3 && strlen($token) <= 64) {
                $out[] = $token;
            }
        }
        return $out;
    }

    public function bySerial(Request $request)
    {
        if ($denied = $this->authorize_request($request)) {
            return $denied;
        }

        $serial = strtoupper(trim((string) $request->get('serial', '')));
        if (strlen($serial) < 3 || strlen($serial) > 64 || !preg_match('/^[A-Z0-9\-_\/]+$/', $serial)) {
            return response()->json(['success' => false, 'message' => 'Invalid serial.'], 422);
        }

        $lines = $this->baseQuery()
            ->where('tsl.sell_line_note', 'like', '%' . $serial . '%')
            ->orderBy('t.transaction_date', 'desc')
            ->limit(25)
            ->get();

        foreach ($lines as $line) {
            // LIKE may hit substrings of a longer serial — verify token-exact.
            if (in_array($serial, $this->noteSerials($line->sell_line_note), true)) {
                return response()->json([
                    'success' => true,
                    'found'   => true,
                    'data'    => $this->row($line, $serial),
                ]);
            }
        }

        return response()->json(['success' => true, 'found' => false, 'data' => null]);
    }

    public function byPhone(Request $request)
    {
        if ($denied = $this->authorize_request($request)) {
            return $denied;
        }

        $digits = preg_replace('/\D+/', '', (string) $request->get('phone', ''));
        // Accept 1XXXXXXXXX / 01XXXXXXXXX / 8801XXXXXXXXX — compare on last 10.
        $last10 = substr($digits, -10);
        if (strlen($last10) !== 10 || $last10[0] !== '1') {
            return response()->json(['success' => false, 'message' => 'Invalid phone.'], 422);
        }

        $lines = $this->baseQuery()
            ->where('c.mobile', 'like', '%' . $last10)
            ->orderBy('t.transaction_date', 'desc')
            ->limit(60)
            ->get();

        $out  = [];
        $seen = [];
        foreach ($lines as $line) {
            // Returned units are not purchases — never offer them for linking.
            if ($this->isReturned($line)) {
                continue;
            }
            foreach ($this->noteSerials($line->sell_line_note) as $serial) {
                if (isset($seen[$serial])) {
                    continue;
                }
                $seen[$serial] = true;
                $out[]         = $this->row($line, $serial);
                if (count($out) >= 30) {
                    break 2;
                }
            }
        }

        return response()->json(['success' => true, 'count' => count($out), 'data' => $out]);
    }
}
