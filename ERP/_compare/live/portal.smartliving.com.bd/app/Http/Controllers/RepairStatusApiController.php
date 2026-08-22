<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class RepairStatusApiController extends Controller
{
    public function check(Request $request)
    {
        // CORS origin (site that will call this API)
        $allowed_origin = 'https://aun-projector.com.bd';

        // 1) Security: API KEY check (safe + trimmed)
        $api_key = trim((string) ($request->header('X-API-KEY') ?? $request->server('HTTP_X_API_KEY')));
        $env_key = trim((string) env('REPAIR_TRACK_API_KEY'));

        if ($api_key !== $env_key) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401)
            ->header('Access-Control-Allow-Origin', $allowed_origin)
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY');
        }

        // 2) Input
        $search_by = (string) $request->get('search_by'); // job_sheet | invoice | mobile | serial
        $query     = trim((string) $request->get('query'));
        $serial    = trim((string) $request->get('serial')); // optional

        if (empty($search_by) || empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Search type and query are required'
            ], 422)
            ->header('Access-Control-Allow-Origin', $allowed_origin)
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY');
        }

        try {
            // 3) Base query with correct joins (include rjs.id as job_sheet_id)
            $q = DB::table('repair_job_sheets as rjs')
                ->leftJoin('contacts as c', 'c.id', '=', 'rjs.contact_id')
                ->leftJoin('repair_statuses as rs', 'rs.id', '=', 'rjs.status_id')
                ->leftJoin('repair_device_models as rdm', 'rdm.id', '=', 'rjs.device_model_id');

            // Search condition
            if ($search_by === 'job_sheet') {
                $q->where('rjs.job_sheet_no', $query);
            } elseif ($search_by === 'mobile') {
                $q->where('c.mobile', $query);
            } elseif ($search_by === 'invoice') {
                // We assume invoice stored in custom_field_1 (if you store elsewhere change this)
                $q->where('rjs.custom_field_1', $query);
            } elseif ($search_by === 'serial') {
                // Direct serial lookup — the AUN app links its repair requests to
                // job sheets this way (serial is unique per physical unit and is
                // always typed into the job sheet).
                $q->where('rjs.serial_no', $query);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid search type'
                ], 422)
                ->header('Access-Control-Allow-Origin', $allowed_origin)
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY');
            }

            // Optional serial validation
            if (!empty($serial)) {
                $q->where('rjs.serial_no', $serial);
            }

            // Detect which column stores the customer-reported problem text.
            // The Repair module's column name varies between installs, so we pick
            // the first known column that actually exists. If none match, the
            // field is simply omitted (returned empty) — selecting a non-existent
            // column would throw and break the whole endpoint, so we never do that.
            $problem_col = null;
            $rjs_columns = Schema::getColumnListing('repair_job_sheets');
            foreach ([
                'problem_reported_by_customer',
                'problem_reported',
                'reported_problem',
                'customer_problem',
                'customer_complaint',
                'problem_description',
                'problem',
                'complaint',
                'defects',
            ] as $cand) {
                if (in_array($cand, $rjs_columns, true)) {
                    $problem_col = $cand;
                    break;
                }
            }

            // Select fields (include custom fields for your new labels)
            $select = [
                'rjs.id as job_sheet_id',
                'rjs.job_sheet_no',
                'rjs.serial_no',
                'rjs.delivery_date',
                'rjs.estimated_cost',
                'rjs.created_at',
                'rjs.custom_field_1',
                'rjs.custom_field_2',
                'rjs.custom_field_3',
                'rs.name as status_name',
                // Businesses flag their own terminal statuses (e.g. "Delivered /
                // Collected") in Repair Settings — expose the flag so consumers
                // never hardcode status names.
                'rs.is_completed_status as status_is_completed',
                'c.name as customer_name',
                'c.mobile as customer_mobile',
                'rdm.name as device_model_name',
            ];
            if ($problem_col) {
                $select[] = "rjs.$problem_col as problem_reported";
            }

            $job = $q->select($select)
                ->orderBy('rjs.id', 'desc')
                ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'No record found'
                ], 404)
                ->header('Access-Control-Allow-Origin', $allowed_origin)
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY');
            }

            // Parse the "Problem reported by customer" field. In this install it
            // is the repair module's `defects` tag input, stored as a JSON array
            // of {"value":"..."} objects. Normalise it to a plain array of strings
            // so the website can render each problem as its own pill. Falls back
            // gracefully if the value is a plain string or empty.
            $problems = [];
            if (isset($job->problem_reported) && trim((string) $job->problem_reported) !== '') {
                $raw     = (string) $job->problem_reported;
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (is_array($item) && isset($item['value'])) {
                            $val = trim((string) $item['value']);
                        } elseif (is_string($item)) {
                            $val = trim($item);
                        } else {
                            $val = '';
                        }
                        if ($val !== '') {
                            $problems[] = $val;
                        }
                    }
                } else {
                    // Not JSON — treat the whole value as a single problem entry.
                    $problems[] = trim($raw);
                }
            }

            // 4) Activities from activity_log (Spatie)
            $subjectType = 'Modules\\Repair\\Entities\\JobSheet';

            $activitiesQuery = DB::table('activity_log as al')
                ->leftJoin('users as u', 'u.id', '=', 'al.causer_id')
                ->where('al.subject_type', $subjectType)
                ->where('al.subject_id', $job->job_sheet_id)
                ->orderBy('al.id', 'desc')
                ->limit(50)
                ->get([
                    'al.description',
                    'al.properties',
                    'al.created_at',
                    'u.surname',
                    'u.first_name',
                    'u.last_name',
                ]);

            $activity_list = [];

            foreach ($activitiesQuery as $a) {
                if ($a->description !== 'status_changed') {
                    continue; // skip notifications and other logs
                }

                $props = json_decode($a->properties, true) ?: [];
                $updated_status = $props['updated_status'] ?? '';
                $update_note    = $props['update_note'] ?? '';

                $by = trim(
                    ($a->surname ?? '') . ' ' .
                    ($a->first_name ?? '') . ' ' .
                    ($a->last_name ?? '')
                );

                if (empty($by)) {
                    $by = 'Service Team';
                }

                $activity_list[] = [
                    'date' => $a->created_at,
                    'action' => 'Status changed to "' . $updated_status . '"',
                    'by' => $by,
                    'note' => $update_note,
                ];
            }

            // 5) Fetch Documents/Media for this Job Sheet
            $mediaFiles = DB::table('media')
                ->where('model_type', $subjectType)
                ->where('model_id', $job->job_sheet_id)
                ->pluck('file_name');

            $documents = [];
            foreach ($mediaFiles as $file) {
                // Generate the public URL to view the image
                $documents[] = url('uploads/media/' . $file);
            }

            // 6) Final response
            return response()->json([
                'success' => true,
                'data' => [
                    'customer_name'        => $job->customer_name ?? '',
                    'job_sheet_no'         => $job->job_sheet_no ?? '',
                    'status'               => $job->status_name ?? '',
                    'status_completed'     => (bool) ($job->status_is_completed ?? false),
                    'serial_number'        => $job->serial_no ?? '',
                    'device'               => 'AUN Projector',
                    'device_model'         => $job->device_model_name ?? '',
                    'problem_reported'     => $problems,
                    'delivery_date'        => $job->delivery_date ?? '',
                    'estimated_cost'       => $job->estimated_cost ?? '',
                    'created_at'           => $job->created_at ?? '',
                    // Newly added custom fields:
                    'accessories_received' => $job->custom_field_1 ?? '',
                    'warranty'             => $job->custom_field_2 ?? '',
                    'order_number'         => $job->custom_field_3 ?? '',
                    // Attached documents/images:
                    'documents'            => $documents,
                    // Activity logs:
                    'activities'           => $activity_list,
                ]
            ], 200)
            ->header('Access-Control-Allow-Origin', $allowed_origin)
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY');

        } catch (Exception $e) {
            // Log server error
            Log::error('RepairStatusApiController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500)
            ->header('Access-Control-Allow-Origin', $allowed_origin)
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY');
        }
    }
}