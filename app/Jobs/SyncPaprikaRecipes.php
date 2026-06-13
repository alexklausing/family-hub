<?php

namespace App\Jobs;

use App\Services\PaprikaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPaprikaRecipes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaprikaSyncService $service): void
    {
        Log::info('Job: Starting Paprika Recipe Sync...');
        $service->syncRecipes();
        Log::info('Job: Paprika Recipe Sync completed.');
    }
}
