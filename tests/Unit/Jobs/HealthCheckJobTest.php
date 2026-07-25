<?php

namespace Tests\Unit\Jobs;

use App\Jobs\HealthCheckJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HealthCheckJobTest extends TestCase
{
    public function test_job_is_dispatched_to_default_queue(): void
    {
        Bus::fake();

        HealthCheckJob::dispatch();

        Bus::assertDispatched(HealthCheckJob::class, function (HealthCheckJob $job) {
            return $job->queue === 'default';
        });
    }

    public function test_job_has_expected_retry_configuration(): void
    {
        $job = new HealthCheckJob;

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([60, 300, 900], $job->backoff);
    }

    public function test_handle_logs_success_with_job_id(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Horizon working', \Mockery::on(fn ($context) => array_key_exists('job_id', $context)));

        HealthCheckJob::dispatchSync();
    }

    public function test_failed_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('HealthCheckJob failed');

        (new HealthCheckJob)->failed(new \Exception('boom'));
    }
}
