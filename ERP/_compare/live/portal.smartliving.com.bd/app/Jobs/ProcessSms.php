<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;
    
    // ✅ NEW: Tell the queue to try 10 times before giving up
    public $tries = 10;
    
    // ✅ NEW: Tell the queue to wait 60 seconds between each failed attempt
    public $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Background SMS Job Started', ['phone' => $this->data['mobile_number'] ?? 'Unknown']);
        
        try {
            // This spins up the Util class and runs your original large SMS function quietly
            $util = new \App\Utils\Util();
            $util->sendSmsSync($this->data);
            
            Log::info('Background SMS Job Completed Successfully');
        } catch (\Throwable $e) {
            Log::error('Background SMS Job Failed (Attempt ' . $this->attempts() . '): ' . $e->getMessage());
            throw $e; // This triggers the automatic retry!
        }
    }
}