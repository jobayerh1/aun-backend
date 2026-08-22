<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanOldSms extends Command
{
    // The command you will type in your cron job
    protected $signature = 'sms:clean-old';

    // A short description of what it does
    protected $description = 'Deletes maintenance SMS records older than 6 months';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Calculate the exact date and time 6 months ago from today
        $cutoffDate = Carbon::now()->subMonths(6);

        // ✅ MODIFIED: Delete rows that are 'sent' OR 'queued' AND older than 6 months
        $deletedCount = DB::table('maintenance_sms_schedules')
            ->whereIn('status', ['sent', 'queued'])
            ->where('scheduled_at', '<', $cutoffDate)
            ->delete();

        // Write a small note in the laravel.log file so you have a record of the cleanup
        Log::info("Monthly Database Cleanup: Deleted {$deletedCount} old maintenance SMS records.");

        $this->info("Successfully deleted {$deletedCount} old records.");
    }
}