<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscussionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_discussion_rejects_uninvited_user_and_allows_invited_member(): void
    {
        $author = $this->user();
        $member = $this->user();
        $outsider = $this->user();
        $discussion = $this->as($author)->postJson('/api/discussions', ['title' => 'Личное', 'visibility' => 'private'])->assertCreated()->json();

        $this->as($outsider)->getJson("/api/discussions/{$discussion['id']}")->assertForbidden();
        $this->as($author)->postJson("/api/discussions/{$discussion['id']}/members", ['user_ids' => [$member->id]])->assertOk();
        $this->as($member)->getJson("/api/discussions/{$discussion['id']}")->assertOk();
    }

    public function test_closed_discussion_rejects_messages_and_duplicate_like_returns_conflict(): void
    {
        $author = $this->user();
        $discussion = $this->as($author)->postJson('/api/discussions', ['title' => 'Публичное', 'visibility' => 'public'])->assertCreated()->json();
        $message = $this->as($author)->postJson("/api/discussions/{$discussion['id']}/messages", ['body' => 'Текст'])->assertCreated()->json();

        $this->as($author)->postJson("/api/messages/{$message['id']}/like")->assertCreated();
        $this->as($author)->postJson("/api/messages/{$message['id']}/like")->assertStatus(409);
        $this->as($author)->postJson("/api/discussions/{$discussion['id']}/close")->assertOk()->assertJsonPath('status', 'closed');
        $this->as($author)->postJson("/api/discussions/{$discussion['id']}/messages", ['body' => 'Поздно'])->assertStatus(409);
    }

    public function test_moderator_changes_verdict_with_history(): void
    {
        $author = $this->user(['discussions.moderate']);
        $discussion = $this->as($author)->postJson('/api/discussions', ['title' => 'Вердикт', 'visibility' => 'public'])->assertCreated()->json();
        $first = $this->as($author)->postJson("/api/discussions/{$discussion['id']}/messages", ['body' => 'Первый'])->assertCreated()->json();
        $second = $this->as($author)->postJson("/api/discussions/{$discussion['id']}/messages", ['body' => 'Второй'])->assertCreated()->json();

        $this->as($author)->postJson("/api/discussions/{$discussion['id']}/verdict", ['message_id' => $first['id']])->assertOk();
        $this->as($author)->postJson("/api/discussions/{$discussion['id']}/verdict", ['message_id' => $second['id']])->assertOk()->assertJsonPath('verdict.message_id', $second['id']);
        $this->assertDatabaseCount('discussion_verdicts', 2);
        $this->assertDatabaseCount('discussion_verdicts', 2);
        $this->assertDatabaseHas('discussion_verdicts', ['message_id' => $first['id'], 'is_current' => false]);
    }

    /** @param list<string> $permissions */
    private function user(array $permissions = []): User
    {
        $user = User::query()->create(['id' => (string) Str::uuid(), 'phone_ciphertext' => 'encrypted', 'phone_hash' => hash('sha256', Str::uuid())]);
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
