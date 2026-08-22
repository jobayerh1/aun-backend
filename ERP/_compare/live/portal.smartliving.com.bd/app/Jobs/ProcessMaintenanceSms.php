<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMaintenanceSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $rowId;
    public $to;
    public $msg;
    public $schedule;

    // ✅ Tell the queue to try 10 times before giving up
    public $tries = 10;

    // ✅ Tell the queue to wait 60 seconds between each failed attempt
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct($rowId, $to, $msg, $schedule)
    {
        $this->rowId = $rowId;
        $this->to = $to;
        $this->msg = $msg;
        $this->schedule = $schedule;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Background Maintenance SMS Job Started for Schedule ID: {$this->rowId}");

        $service = app(\App\Services\MaintenanceSmsService::class);
        $service->executeSend($this->rowId, $this->to, $this->msg, $this->schedule);

        Log::info("Background Maintenance SMS Job Completed Successfully for ID: {$this->rowId}");
    }

    /**
     * Handle a job failure after 10 attempts.
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Maintenance SMS Ultimately Failed after 10 tries for ID {$this->rowId}: " . $exception->getMessage());

        $service = app(\App\Services\MaintenanceSmsService::class);
        $service->markAsFailedUltimate($this->rowId, $exception);
    }
}