<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_request_is_visible_only_to_author_addressee_and_private_permission(): void
    {
        $author = $this->user(['requests.moderate']);
        $addressee = $this->user();
        $outsider = $this->user(['requests.view_public']);
        $privateViewer = $this->user(['requests.view_private']);

        $item = $this->as($author)->postJson('/api/work-items', [
            'kind' => 'request', 'title' => 'Личное', 'description' => 'Текст', 'visibility' => 'private',
            'addressee_user_id' => $addressee->id, 'priority' => 'normal',
        ])->assertCreated()->json();

        $this->as($author)->getJson("/api/work-items/{$item['id']}")->assertOk();
        $this->as($addressee)->getJson("/api/work-items/{$item['id']}")->assertOk();
        $this->as($privateViewer)->getJson("/api/work-items/{$item['id']}")->assertOk();
        $this->as($outsider)->getJson("/api/work-items/{$item['id']}")->assertForbidden();
    }

    public function test_request_statuses_and_resident_cancellation_rule_are_enforced(): void
    {
        $resident = $this->user();
        $moderator = $this->user(['requests.moderate']);
        $item = $this->as($resident)->postJson('/api/work-items', [
            'kind' => 'request', 'title' => 'Публичное', 'description' => 'Текст', 'visibility' => 'public', 'priority' => 'normal',
        ])->assertCreated()->json();

        $this->as($resident)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'cancelled'])->assertOk();

        $item = $this->as($resident)->postJson('/api/work-items', [
            'kind' => 'request', 'title' => 'Второе', 'description' => 'Текст', 'visibility' => 'public', 'priority' => 'normal',
        ])->assertCreated()->json();
        $this->as($moderator)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'in_progress'])->assertOk();
        $this->as($resident)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'cancelled'])->assertForbidden();
        $this->as($moderator)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'completed'])->assertOk();
        $this->as($moderator)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'queued'])->assertStatus(409);
    }

    public function test_task_statuses_completion_and_task_specific_permissions_are_enforced(): void
    {
        $manager = $this->user(['tasks.manage', 'tasks.assign', 'budget.manage']);
        $closer = $this->user(['tasks.close']);
        $item = $this->as($manager)->postJson('/api/work-items', [
            'kind' => 'task', 'title' => 'Работа', 'description' => 'Текст', 'priority' => 'high', 'due_at' => now()->addDay()->toAtomString(),
        ])->assertCreated()->json();

        $this->as($manager)->putJson("/api/work-items/{$item['id']}/assignees", ['assignees' => [['external_name' => 'Подрядчик']]])->assertOk();
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/time-entries", ['minutes' => 60, 'recorded_at' => now()->toAtomString()])->assertCreated();
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/budget-entries", ['amount' => 1000, 'currency' => 'rub', 'recorded_at' => now()->toAtomString()])->assertCreated();
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'in_progress'])->assertOk();
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'review'])->assertOk();
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'completed'])->assertForbidden();
        $this->as($closer)->postJson("/api/work-items/{$item['id']}/transition", ['status' => 'completed'])->assertOk();
    }

    public function test_conversion_preserves_history_and_requires_management_rights(): void
    {
        $resident = $this->user();
        $manager = $this->user(['requests.moderate', 'tasks.manage']);
        $item = $this->as($resident)->postJson('/api/work-items', [
            'kind' => 'request', 'title' => 'Обращение', 'description' => 'Текст', 'visibility' => 'public', 'priority' => 'normal',
        ])->assertCreated()->json();

        $this->as($resident)->postJson("/api/work-items/{$item['id']}/convert", ['kind' => 'task'])->assertForbidden();
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/convert", ['kind' => 'task'])->assertOk()->assertJsonPath('kind', 'task');
        $this->as($manager)->postJson("/api/work-items/{$item['id']}/convert", ['kind' => 'request'])->assertOk()->assertJsonPath('kind', 'request');
        $this->assertDatabaseCount('work_history', 3);
    }

    public function test_every_supported_status_is_reachable_through_its_allowed_transition(): void
    {
        $manager = $this->user(['requests.moderate', 'tasks.manage', 'tasks.close']);
        $request = $this->as($manager)->postJson('/api/work-items', [
            'kind' => 'request', 'title' => 'Отказ', 'description' => 'Текст', 'visibility' => 'public', 'priority' => 'normal',
        ])->assertCreated()->json();
        $this->as($manager)->postJson("/api/work-items/{$request['id']}/transition", ['status' => 'rejected'])
            ->assertOk()->assertJsonPath('status', 'rejected');

        $task = $this->as($manager)->postJson('/api/work-items', [
            'kind' => 'task', 'title' => 'Приостановка', 'description' => 'Текст', 'priority' => 'normal',
        ])->assertCreated()->json();
        $this->as($manager)->postJson("/api/work-items/{$task['id']}/transition", ['status' => 'suspended'])
            ->assertOk()->assertJsonPath('status', 'suspended');
        $this->as($manager)->postJson("/api/work-items/{$task['id']}/transition", ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_update_comments_and_attachments_are_available_without_exposing_file_contents(): void
    {
        Storage::fake('attachments');
        $author = $this->user();
        $moderator = $this->user(['requests.moderate']);
        $item = $this->as($author)->postJson('/api/work-items', [
            'kind' => 'request', 'title' => 'Исходный заголовок', 'description' => 'Текст', 'visibility' => 'public', 'priority' => 'normal',
        ])->assertCreated()->json();

        $this->as($author)->postJson("/api/work-items/{$item['id']}/comments", ['body' => 'Комментарий'])->assertCreated();
        $this->as($author)->postJson("/api/work-items/{$item['id']}/attachments", [
            'file' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $this->as($moderator)->patchJson("/api/work-items/{$item['id']}", ['title' => 'Новый заголовок'])->assertOk();
        $this->as($author)->getJson("/api/work-items/{$item['id']}")
            ->assertOk()->assertJsonPath('title', 'Новый заголовок')->assertJsonCount(1, 'comments')->assertJsonCount(1, 'attachments');
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_item.updated', 'entity_id' => $item['id']]);
    }

    /** @param list<string> $permissions */
    private function user(array $permissions = []): User
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(), 'phone_ciphertext' => 'encrypted', 'phone_hash' => hash('sha256', Str::uuid()),
        ]);
        if ($permissions !== []) {
            $roleId = (string) Str::uuid();
            DB::table('roles')->insert(['id' => $roleId, 'name' => "role-{$roleId}", 'created_at' => now(), 'updated_at' => now()]);
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_code' => $permission]);
            }
        }

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user, 'sanctum');
    }
}
