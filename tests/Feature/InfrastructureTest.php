<?php

namespace Tests\Feature;

use App\Contracts\AttachmentStorage;
use App\Jobs\QueueHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_storage_returns_metadata_and_keeps_file_private(): void
    {
        Storage::fake('attachments');
        $metadata = app(AttachmentStorage::class)->store(UploadedFile::fake()->create('minutes.pdf', 12, 'application/pdf'));

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $metadata['id']);
        $this->assertSame('minutes.pdf', $metadata['original_name']);
        Storage::disk('attachments')->assertExists($metadata['storage_key']);
    }

    public function test_attachment_storage_rejects_executable_files(): void
    {
        $this->expectException(ValidationException::class);
        app(AttachmentStorage::class)->store(UploadedFile::fake()->create('script.php', 1, 'application/x-httpd-php'));
    }

    public function test_queue_health_job_processes_with_redis_cache_contract(): void
    {
        Queue::fake();
        QueueHealthCheck::dispatch('queue-health-test');
        Queue::assertPushed(QueueHealthCheck::class);

        Cache::shouldReceive('store')->with('redis')->andReturnSelf();
        Cache::shouldReceive('put')->once();
        (new QueueHealthCheck('queue-health-test'))->handle();
    }
}
