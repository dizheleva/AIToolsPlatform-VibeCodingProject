<?php

namespace App\Jobs;

use App\Models\AiTool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IncrementToolViews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $aiToolId
    ) {
        // Set queue connection if specified in config
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Use direct DB increment for better performance
        // This avoids loading the model into memory
        DB::table('ai_tools')
            ->where('id', $this->aiToolId)
            ->increment('views_count');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure but don't throw - view counting is not critical
        \Log::warning('Failed to increment views for tool ID: ' . $this->aiToolId, [
            'error' => $exception->getMessage(),
        ]);
    }
}

