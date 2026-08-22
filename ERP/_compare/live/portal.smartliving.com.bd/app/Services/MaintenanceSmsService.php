<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MaintenanceSmsService
{
    /**
     * SINGLE SOURCE OF TRUTH for the maintenance reminder messages,
     * keyed by the number of days after the sale date.
     *
     * Used by BOTH paths, so they can never drift apart:
     *   - handleTransactionIfPaid() : stored into the `message` column when scheduling
     *   - messageByDayOffset()      : fallback at send time if that column/value is missing
     *
     * ⚠️ Edit the wording HERE ONLY. Previously these three messages were
     * written out twice in this file; changing one copy and not the other
     * would silently send different text depending on which path ran.
     *
     * To change the timing, change the keys (e.g. 30 => 45). Existing rows
     * already scheduled in maintenance_sms_schedules are NOT affected —
     * only sales paid after the change use the new values.
     */
    private const REMINDER_MESSAGES = [
        30 => "AUN Care Tip:\nPlease clean your projector's dust filter regularly to ensure proper airflow and smooth performance. This helps extend product life.",
        60 => "AUN Reminder:\nDust buildup can block airflow and cause overheating. Clean the dust filter regularly to protect your projector's internal components.",
        90 => "AUN Important Notice:\nDamage from overheating due to blocked ventilation or dust buildup is not covered under warranty. Regular filter cleaning is essential.",
    ];

    /**
     * Call this AFTER a transaction becomes PAID.
     * This ONLY inserts rows into maintenance_sms_schedules (no API call here).
     */
    public function handleTransactionIfPaid(int $transaction_id): void
    {
        $txn = DB::table('transactions as t')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.id', $transaction_id)
            ->select([
                't.id',
                't.contact_id',
                't.payment_status',
                't.transaction_date',
                'c.contact_type',
                'c.mobile',
            ])
            ->first();

        if (!$txn) {
            return;
        }

        // ✅ only PAID
        if (strtolower((string) $txn->payment_status) !== 'paid') {
            return;
        }

        // ✅ only INDIVIDUAL (skip business/distributors)
        if (strtolower((string) $txn->contact_type) !== 'individual') {
            return;
        }

        $mobile = $this->normalizeMobile((string) $txn->mobile);
        if ($mobile === '') {
            return;
        }

        // ✅ eligible product check using Product Custom Field 1
        $eligible = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('tsl.transaction_id', $txn->id)
            ->where('p.product_custom_field1', 'MAINT_SMS')
            ->exists();

        if (!$eligible) {
            return;
        }

        // ✅ prevent duplicates (if already inserted for this transaction)
        $already = DB::table('maintenance_sms_schedules')
            ->where('transaction_id', $txn->id)
            ->exists();

        if ($already) {
            return;
        }

        $baseDate = Carbon::parse($txn->transaction_date ?? now())
            ->setTimezone(config('app.timezone'));

        $templates = self::REMINDER_MESSAGES;

        $hasMessageCol = Schema::hasColumn('maintenance_sms_schedules', 'message');
        $hasSentAtCol  = Schema::hasColumn('maintenance_sms_schedules', 'sent_at');
        $hasRespCol    = Schema::hasColumn('maintenance_sms_schedules', 'api_response');

        foreach ($templates as $day => $msg) {
            $scheduleAt = $baseDate->copy()->addDays($day)->setTime(10, 0, 0);

            $row = [
                'transaction_id' => (int) $txn->id,
                'contact_id'     => (int) $txn->contact_id,
                'mobile'         => $mobile,
                'day_offset'     => (int) $day,
                'scheduled_at'   => $scheduleAt->toDateTimeString(),
                'status'         => 'scheduled',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            if ($hasMessageCol) {
                $row['message'] = $msg;
            }

            if ($hasSentAtCol) {
                $row['sent_at'] = null;
            }

            if ($hasRespCol) {
                $row['api_response'] = null;
            }

            DB::table('maintenance_sms_schedules')->insert($row);
        }
    }

    /**
     * Cron job calls this every 5 minutes.
     * This pushes ALL 'scheduled' rows instantly to the background Queue!
     */
    public function processDueSchedules(int $limit = 50): array
    {
        $hasMessageCol = Schema::hasColumn('maintenance_sms_schedules', 'message');
        $hasRespCol    = Schema::hasColumn('maintenance_sms_schedules', 'api_response');

        $rows = DB::table('maintenance_sms_schedules')
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return ['ok' => true, 'picked' => 0, 'queued' => 0, 'failed' => 0];
        }

        $queued = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $to = $this->normalizeMobile((string) ($row->mobile ?? ''));
            if ($to === '') {
                $this->markFailedSimple((int) $row->id, 'ERR missing mobile', $hasRespCol);
                $failed++;
                continue;
            }

            $msg = '';
            if ($hasMessageCol && !empty($row->message)) {
                $msg = (string) $row->message;
            } else {
                $msg = $this->messageByDayOffset((int) $row->day_offset);
            }

            if (trim($msg) === '') {
                $this->markFailedSimple((int) $row->id, 'ERR missing message', $hasRespCol);
                $failed++;
                continue;
            }

            $schedule = (string) $row->scheduled_at; 

            // ✅ NEW: Dispatch to background queue instantly instead of waiting for API!
            \App\Jobs\ProcessMaintenanceSms::dispatch((int) $row->id, $to, $msg, $schedule);

            DB::table('maintenance_sms_schedules')
                ->where('id', (int) $row->id)
                ->update([
                    'status'       => 'queued', // Mark as queued so it isn't picked up again
                    'updated_at'   => now(),
                ]);

            $queued++;
        }

        return ['ok' => true, 'picked' => $rows->count(), 'queued' => $queued, 'failed' => $failed];
    }

    /**
     * ✅ NEW: Called by the background Job to actually send the SMS.
     */
    public function executeSend(int $rowId, string $to, string $msg, string $schedule): void
    {
        $hasRespCol = Schema::hasColumn('maintenance_sms_schedules', 'api_response');

        $resp = $this->sendToAlphaScheduled($to, $msg, $schedule);
        $isOk = $this->isAlphaSuccess($resp);

        if (!$isOk) {
            // Throwing an exception tells the background Job that this failed and needs to be retried!
            throw new \Exception("Alpha API Error: " . $resp);
        }

        DB::table('maintenance_sms_schedules')
            ->where('id', $rowId)
            ->update([
                'status'       => 'sent',
                'api_response' => $hasRespCol ? $resp : null,
                'updated_at'   => now(),
            ]);
    }

    /**
     * ✅ NEW: Called by the background Job if it fails 10 times in a row.
     */
    public function markAsFailedUltimate(int $rowId, \Throwable $e): void
    {
        $hasRespCol = Schema::hasColumn('maintenance_sms_schedules', 'api_response');

        DB::table('maintenance_sms_schedules')
            ->where('id', $rowId)
            ->update([
                'status'       => 'failed',
                'api_response' => $hasRespCol ? substr($e->getMessage(), 0, 250) : null,
                'updated_at'   => now(),
            ]);
    }

    /**
     * Alpha SMS scheduled send (send now to portal, schedule for future).
     */
    private function sendToAlphaScheduled(string $to, string $msg, string $schedule): string
    {
        $endpoint = env('ALPHA_SMS_ENDPOINT', 'https://api.sms.net.bd/sendsms');
        $apiKey   = env('ALPHA_SMS_API_KEY');
        $senderId = env('ALPHA_SMS_SENDER_ID');

        if (empty($apiKey) || empty($senderId)) {
            return 'ERR missing ALPHA_SMS_API_KEY or ALPHA_SMS_SENDER_ID in .env';
        }

        $payload = [
            'api_key'   => $apiKey,
            'sender_id' => $senderId,
            'to'        => $to,
            'msg'       => $msg,
            'schedule'  => $schedule,
        ];

        try {
            $r = Http::timeout(25)->asForm()->post($endpoint, $payload);
            return $r->status() . ' ' . $r->body();
        } catch (\Throwable $e) {
            return 'ERR ' . $e->getMessage();
        }
    }

    private function markFailedSimple(int $id, string $reason, bool $hasRespCol): void
    {
        $update = [
            'status'     => 'failed',
            'updated_at' => now(),
        ];

        if ($hasRespCol) {
            $update['api_response'] = $reason;
        }

        DB::table('maintenance_sms_schedules')->where('id', $id)->update($update);
    }

    private function messageByDayOffset(int $day): string
    {
        return self::REMINDER_MESSAGES[$day] ?? '';
    }

    private function isAlphaSuccess(string $resp): bool
    {
        $resp = trim($resp);

        if (preg_match('/^\d{3}\s+(.+)$/', $resp, $m)) {
            $resp = trim($m[1]);
        }

        $data = json_decode($resp, true);

        if (is_array($data) && array_key_exists('error', $data)) {
            return (string)$data['error'] === '0';
        }

        if (stripos($resp, '"error":0') !== false || stripos($resp, '"error":"0"') !== false) {
            return true;
        }

        return false;
    }

    private function normalizeMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        if ($mobile === '') return '';

        $mobile = preg_replace('/[\s\-]/', '', $mobile);

        if (strpos($mobile, '+880') === 0) {
            $mobile = '0' . substr($mobile, 4);
        }

        if (strpos($mobile, '880') === 0) {
            $mobile = '0' . substr($mobile, 3);
        }

        return $mobile;
    }
}