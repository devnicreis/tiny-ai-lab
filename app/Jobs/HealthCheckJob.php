<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::info('Horizon working', ['job_id' => $this->job->getJobId()]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('HealthCheckJob failed');
    }
}
