<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_work_item_requires_an_addressee_and_history_survives_kind_change(): void
    {
        $authorId = $this->createUser();

        $this->expectException(QueryException::class);

        DB::table('work_items')->insert([
            'id' => (string) Str::uuid(),
            'kind' => 'request',
            'title' => 'Личное обращение',
            'description' => 'Описание',
            'visibility' => 'private',
            'status' => 'queued',
            'priority' => 'normal',
            'created_by' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_kind_can_change_without_removing_work_history(): void
    {
        $authorId = $this->createUser();
        $workItemId = $this->createWorkItem($authorId);

        DB::table('work_history')->insert([
            'id' => (string) Str::uuid(),
            'work_item_id' => $workItemId,
            'actor_user_id' => $authorId,
            'field' => 'kind',
            'old_value' => json_encode('request', JSON_THROW_ON_ERROR),
            'new_value' => json_encode('task', JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        DB::table('work_items')->where('id', $workItemId)->update([
            'kind' => 'task',
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('work_items', ['id' => $workItemId, 'kind' => 'task']);
        $this->assertDatabaseHas('work_history', ['work_item_id' => $workItemId, 'field' => 'kind']);
    }

    public function test_work_assignee_must_be_either_a_user_or_an_external_name(): void
    {
        $authorId = $this->createUser();
        $assigneeId = $this->createUser();
        $workItemId = $this->createWorkItem($authorId);

        DB::table('work_assignees')->insert([
            'id' => (string) Str::uuid(),
            'work_item_id' => $workItemId,
            'user_id' => $assigneeId,
        ]);
        DB::table('work_assignees')->insert([
            'id' => (string) Str::uuid(),
            'work_item_id' => $workItemId,
            'external_name' => 'ООО Подрядчик',
        ]);

        $this->assertDatabaseCount('work_assignees', 2);

        try {
            DB::table('work_assignees')->insert([
                'id' => (string) Str::uuid(),
                'work_item_id' => $workItemId,
            ]);
            $this->fail('Исполнитель без пользователя и внешнего имени не должен сохраняться.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);

        DB::table('work_assignees')->insert([
            'id' => (string) Str::uuid(),
            'work_item_id' => $workItemId,
            'user_id' => $assigneeId,
            'external_name' => 'ООО Подрядчик',
        ]);
    }

    public function test_closed_discussion_rejects_new_messages_and_allows_one_current_verdict(): void
    {
        $authorId = $this->createUser();
        $discussionId = (string) Str::uuid();
        $messageId = (string) Str::uuid();
        $secondMessageId = (string) Str::uuid();

        DB::table('discussions')->insert([
            'id' => $discussionId,
            'title' => 'Обсуждение',
            'visibility' => 'public',
            'created_by' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('messages')->insert([
            'id' => $messageId,
            'discussion_id' => $discussionId,
            'created_by' => $authorId,
            'body' => 'Первое сообщение',
            'created_at' => now(),
        ]);
        DB::table('messages')->insert([
            'id' => $secondMessageId,
            'discussion_id' => $discussionId,
            'created_by' => $authorId,
            'body' => 'Второе сообщение',
            'created_at' => now(),
        ]);
        DB::table('discussion_verdicts')->insert([
            'id' => (string) Str::uuid(),
            'discussion_id' => $discussionId,
            'message_id' => $messageId,
            'assigned_by' => $authorId,
            'is_current' => true,
            'created_at' => now(),
        ]);
        DB::table('discussions')->where('id', $discussionId)->update([
            'is_closed' => true,
            'closed_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('messages')->insert([
                'id' => (string) Str::uuid(),
                'discussion_id' => $discussionId,
                'created_by' => $authorId,
                'body' => 'Запрещённое сообщение',
                'created_at' => now(),
            ]);
            $this->fail('Закрытое обсуждение не должно принимать новые сообщения.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);

        DB::table('discussion_verdicts')->insert([
            'id' => (string) Str::uuid(),
            'discussion_id' => $discussionId,
            'message_id' => $secondMessageId,
            'assigned_by' => $authorId,
            'is_current' => true,
            'created_at' => now(),
        ]);
    }

    private function createUser(): string
    {
        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $userId,
            'phone_ciphertext' => 'encrypted-phone',
            'phone_hash' => hash('sha256', $userId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }

    private function createWorkItem(string $authorId): string
    {
        $workItemId = (string) Str::uuid();

        DB::table('work_items')->insert([
            'id' => $workItemId,
            'kind' => 'request',
            'title' => 'Обращение',
            'description' => 'Описание',
            'visibility' => 'public',
            'status' => 'queued',
            'priority' => 'normal',
            'created_by' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workItemId;
    }
}
