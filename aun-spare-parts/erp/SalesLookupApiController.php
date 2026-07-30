<?php
/**
 * AUN Sales Lookup API Controller
 *
 * Read-only endpoint on UltimatePOS that returns a customer's projector purchases
 * matched by phone / order number / serial, so the WordPress Spare Parts plugin can
 * verify a sale (model, purchase date, warranty, address). A customer may have
 * bought several times, so this returns a LIST (newest first). Only sales of
 * products in the "Projector" category are considered.
 *
 * INSTALLATION
 * ─────────────
 * 1. Copy this file to:  app/Http/Controllers/SalesLookupApiController.php
 * 2. Add ONE line to routes/api.php:
 *       Route::get('/sales-lookup', [\App\Http\Controllers\SalesLookupApiController::class, 'lookup']);
 * 3. Add to your .env (same value as AUN_SP_ERP_API_KEY in wp-config.php):
 *       SALES_LOOKUP_API_SECRET=change-this-to-a-long-random-string
 * 4. (Recommended) pin the Projector category id so we don't rely on its name:
 *       SALES_LOOKUP_PROJECTOR_CATEGORY_ID=12        # comma-separated ids allowed
 *    Find it in UltimatePOS: Products → Categories → the "Projector" row. If left
 *    unset, we fall back to matching the category named exactly "Projector".
 *
 * ENDPOINT
 * ─────────
 * GET /api/sales-lookup?search_by=mobile|order|serial&query=...
 * Header:  X-API-KEY: {SALES_LOOKUP_API_SECRET}
 *
 * RESPONSE (200)
 * { "success": true, "count": N, "data": [ {
 *     "invoice_no","order_number","sale_date","purchase_date",
 *     "customer_name","mobile","address","product_name","model","models":[...]
 * }, ... ] }
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SalesLookupApiController extends Controller
{
    /** Cap how many distinct sales we return for one lookup. */
    const MAX_SALES = 25;

    public function lookup(Request $request)
    {
        // ── Authentication ───────────────────────────────────────────────
        $expected = (string) env('SALES_LOOKUP_API_SECRET', '');
        $provided = trim((string) ($request->header('X-API-KEY') ?? $request->server('HTTP_X_API_KEY')));

        if ($expected === '') {
            Log::warning('SalesLookupApiController: SALES_LOOKUP_API_SECRET is not set in .env');
            return response()->json(['success' => false, 'message' => 'API secret not configured on server.'], 503);
        }
        if (! hash_equals($expected, $provided)) {
            Log::warning('SalesLookupApiController: invalid key from IP ' . $request->ip());
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // ── Input ────────────────────────────────────────────────────────
        $search_by = (string) $request->get('search_by', 'mobile');
        $query     = trim((string) $request->get('query', ''));

        if ($query === '' || ! in_array($search_by, ['mobile', 'order', 'serial'], true)) {
            return response()->json(['success' => false, 'message' => 'search_by and query are required.'], 422);
        }

        try {
            $q = DB::table('transactions as t')
                ->join('contacts as c', 'c.id', '=', 't.contact_id')
                ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
                ->join('products as p', 'p.id', '=', 'tsl.product_id')
                ->where('t.type', 'sell')
                ->where('t.status', 'final');

            // ── 2c: restrict to the Projector category (id from .env, else by name) ──
            $cat_env = (string) env('SALES_LOOKUP_PROJECTOR_CATEGORY_ID', '');
            $q->where(function ($w) use ($cat_env) {
                if ($cat_env !== '') {
                    $ids = array_filter(array_map('trim', explode(',', $cat_env)), 'strlen');
                    $w->whereIn('p.category_id', $ids)->orWhereIn('p.sub_category_id', $ids);
                } else {
                    $byName = function ($sub) {
                        $sub->select('id')->from('categories')->where('name', 'Projector');
                    };
                    $w->whereIn('p.category_id', $byName)->orWhereIn('p.sub_category_id', $byName);
                }
            });

            // ── Search condition ──
            if ($search_by === 'mobile') {
                // Match on the last 10 digits so 01711561441 / 8801711561441 /
                // "+880 1711-561441" all resolve to the same contact.
                $digits = preg_replace('/\D+/', '', $query);
                $last10 = substr($digits, -10);
                if (strlen($last10) < 9) {
                    return response()->json(['success' => false, 'message' => 'Invalid mobile number.'], 422);
                }
                $q->where(function ($w) use ($last10) {
                    $w->whereRaw("REPLACE(REPLACE(REPLACE(IFNULL(c.mobile,''),' ',''),'+',''),'-','') LIKE ?", ["%{$last10}"])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(IFNULL(c.alternate_number,''),' ',''),'+',''),'-','') LIKE ?", ["%{$last10}"]);
                });
            } elseif ($search_by === 'order') {
                $q->where(function ($w) use ($query) {
                    $w->where('t.invoice_no', $query)->orWhere('t.ref_no', $query);
                });
            } else { // serial — free-text in this same projector sell line
                $q->where('tsl.sell_line_note', 'like', '%' . $query . '%');
            }

            $rows = $q->orderBy('t.transaction_date', 'desc')
                ->select([
                    't.id', 't.invoice_no', 't.ref_no', 't.transaction_date',
                    'c.name', 'c.mobile',
                    'c.address_line_1', 'c.address_line_2', 'c.city', 'c.state', 'c.country', 'c.zip_code',
                    'p.name as product_name',
                ])
                ->limit(200)
                ->get();

            if ($rows->isEmpty()) {
                return response()->json(['success' => true, 'count' => 0, 'data' => []], 200);
            }

            // Group the projector sell-lines back up into one entry per sale.
            $sales = [];
            foreach ($rows as $r) {
                if (! isset($sales[$r->id])) {
                    $address = implode(', ', array_filter([
                        $r->address_line_1, $r->address_line_2, $r->city,
                        $r->state, $r->country, $r->zip_code,
                    ]));
                    $sales[$r->id] = [
                        'invoice_no'    => $r->invoice_no,
                        'order_number'  => $r->invoice_no ?: $r->ref_no,
                        'sale_date'     => $r->transaction_date,
                        'purchase_date' => substr((string) $r->transaction_date, 0, 10),
                        'customer_name' => $r->name,
                        'mobile'        => $r->mobile,
                        'address'       => $address,
                        'models'        => [],
                    ];
                }
                if ($r->product_name !== null && $r->product_name !== '') {
                    $sales[$r->id]['models'][] = $r->product_name;
                }
                if (count($sales) >= self::MAX_SALES) {
                    break;
                }
            }

            $data = [];
            foreach ($sales as $s) {
                $models         = array_values(array_unique($s['models']));
                $s['models']    = $models;
                $s['product_name'] = $models[0] ?? '';
                $s['model']        = $models[0] ?? '';
                $data[]            = $s;
            }

            return response()->json(['success' => true, 'count' => count($data), 'data' => $data], 200);
        } catch (Exception $e) {
            Log::error('SalesLookupApiController error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }
}
