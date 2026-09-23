<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_management_requires_permission_and_validates_contract(): void
    {
        $resident = $this->user();
        $this->as($resident)->postJson('/api/news', ['title' => 'Новость', 'body' => 'Текст'])->assertForbidden();

        $manager = $this->user(['news.manage']);
        $this->as($manager)->postJson('/api/news', ['title' => 'Новость', 'body' => 'Текст', 'presentation_format' => 'board'])->assertUnprocessable();
        $this->as($manager)->postJson('/api/news', ['title' => 'Новость', 'body' => 'Текст'])->assertCreated()->assertJsonPath('status', 'draft');
    }

    public function test_draft_is_hidden_from_resident_and_publish_creates_audit_and_notifications(): void
    {
        $manager = $this->user(['news.manage']);
        $resident = $this->user();
        $news = $this->as($manager)->postJson('/api/news', ['title' => 'Собрание', 'body' => 'В субботу'])->assertCreated()->json();

        $this->as($resident)->getJson("/api/news/{$news['id']}")->assertForbidden();
        $this->as($resident)->getJson('/api/news')->assertOk()->assertExactJson([]);
        $this->as($manager)->postJson("/api/news/{$news['id']}/publish")->assertOk()->assertJsonPath('status', 'published');

        $this->as($resident)->getJson("/api/news/{$news['id']}")->assertOk()->assertJsonPath('title', 'Собрание');
        $this->assertDatabaseHas('audit_logs', ['action' => 'publication.published', 'entity_id' => $news['id']]);
        $this->assertDatabaseHas('notifications', ['user_id' => $resident->id, 'event_type' => 'publication.published']);
        $this->as($manager)->postJson("/api/news/{$news['id']}/publish")->assertStatus(409);
    }

    public function test_unpublish_update_and_attachment_are_audited(): void
    {
        Storage::fake('attachments');
        $manager = $this->user(['news.manage']);
        $news = $this->as($manager)->postJson('/api/news', ['title' => 'До', 'body' => 'Текст'])->assertCreated()->json();
        $this->as($manager)->patchJson("/api/news/{$news['id']}", ['title' => 'После'])->assertOk()->assertJsonPath('title', 'После');
        $this->as($manager)->post("/api/news/{$news['id']}/attachments", ['file' => UploadedFile::fake()->create('notice.pdf', 12)])->assertCreated()->assertJsonCount(1, 'attachments');
        $this->as($manager)->postJson("/api/news/{$news['id']}/publish")->assertOk();
        $this->as($manager)->postJson("/api/news/{$news['id']}/unpublish")->assertOk()->assertJsonPath('status', 'unpublished');
        $this->assertDatabaseHas('audit_logs', ['action' => 'publication.updated', 'entity_id' => $news['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'publication.unpublished', 'entity_id' => $news['id']]);
    }

    public function test_infoboard_requires_valid_presentation_format_and_own_permission(): void
    {
        $newsManager = $this->user(['news.manage']);
        $this->as($newsManager)->postJson('/api/infoboards', ['title' => 'Щит', 'body' => 'Текст', 'presentation_format' => 'board'])->assertForbidden();

        $manager = $this->user(['infoboards.manage']);
        $this->as($manager)->postJson('/api/infoboards', ['title' => 'Щит', 'body' => 'Текст'])->assertUnprocessable();
        $this->as($manager)->postJson('/api/infoboards', ['title' => 'Щит', 'body' => 'Текст', 'presentation_format' => 'banner'])->assertCreated()->assertJsonPath('type', 'infoboard')->assertJsonPath('presentation_format', 'banner');
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
