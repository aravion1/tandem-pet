<?php

namespace App\Services;

use App\Models\Discussion;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DiscussionService
{
    /** @var array<string, list<string>> */
    private array $permissions = [];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return list<array<string, mixed>> */
    public function index(User $user): array
    {
        return Discussion::query()->orderByDesc('created_at')->get()
            ->filter(fn (Discussion $discussion): bool => $this->canView($user, $discussion))
            ->map(fn (Discussion $discussion): array => $this->present($discussion))
            ->values()->all();
    }

    /** @param array{title:string,visibility:string} $attributes */
    public function create(User $user, array $attributes): Discussion
    {
        return DB::transaction(function () use ($user, $attributes): Discussion {
            $discussion = Discussion::query()->create([...$attributes, 'created_by' => $user->id]);
            DB::table('discussion_members')->insert(['discussion_id' => $discussion->id, 'user_id' => $user->id, 'joined_at' => now()]);
            $this->audit->record($user->id, 'discussion.created', 'discussion', $discussion->id, ['visibility' => $discussion->visibility]);

            return $discussion;
        });
    }

    /** @return array<string, mixed> */
    public function show(User $user, Discussion $discussion): array
    {
        $this->assertViewable($user, $discussion);

        return $this->present($discussion);
    }

    public function update(User $user, Discussion $discussion, string $title): Discussion
    {
        $this->assertAuthor($user, $discussion);
        $discussion->update(['title' => $title]);
        $this->audit->record($user->id, 'discussion.updated', 'discussion', $discussion->id);

        return $discussion->refresh();
    }

    /** @param list<string> $userIds */
    public function addMembers(User $user, Discussion $discussion, array $userIds): void
    {
        $this->assertAuthor($user, $discussion);
        abort_unless($discussion->visibility === 'private', 422);

        foreach ($userIds as $userId) {
            DB::table('discussion_members')->insertOrIgnore(['discussion_id' => $discussion->id, 'user_id' => $userId, 'joined_at' => now()]);
        }
        $this->audit->record($user->id, 'discussion.members_added', 'discussion', $discussion->id, ['count' => count($userIds)]);
    }

    public function addMessage(User $user, Discussion $discussion, string $body): Message
    {
        $this->assertViewable($user, $discussion);
        abort_if($discussion->is_closed, 409);

        return DB::transaction(function () use ($user, $discussion, $body): Message {
            DB::table('discussion_members')->insertOrIgnore(['discussion_id' => $discussion->id, 'user_id' => $user->id, 'joined_at' => now()]);
            $message = Message::query()->create(['discussion_id' => $discussion->id, 'created_by' => $user->id, 'body' => $body, 'created_at' => now()]);
            $this->audit->record($user->id, 'discussion.message_added', 'discussion', $discussion->id);

            return $message;
        });
    }

    public function like(User $user, Message $message): void
    {
        $discussion = Discussion::query()->findOrFail($message->discussion_id);
        $this->assertViewable($user, $discussion);
        abort_if(DB::table('message_likes')->where('message_id', $message->id)->where('user_id', $user->id)->exists(), 409);
        DB::table('message_likes')->insert(['message_id' => $message->id, 'user_id' => $user->id, 'created_at' => now()]);
    }

    public function verdict(User $user, Discussion $discussion, string $messageId): void
    {
        $this->assertMemberWithModeration($user, $discussion);
        abort_unless(Message::query()->where('id', $messageId)->where('discussion_id', $discussion->id)->exists(), 422);

        DB::transaction(function () use ($user, $discussion, $messageId): void {
            DB::table('discussion_verdicts')->where('discussion_id', $discussion->id)->where('is_current', true)->update(['is_current' => false, 'replaced_at' => now()]);
            DB::table('discussion_verdicts')->insert(['id' => (string) Str::uuid(), 'discussion_id' => $discussion->id, 'message_id' => $messageId, 'assigned_by' => $user->id, 'is_current' => true, 'created_at' => now()]);
            $this->audit->record($user->id, 'discussion.verdict_changed', 'discussion', $discussion->id);
        });
    }

    public function close(User $user, Discussion $discussion): void
    {
        abort_unless($discussion->created_by === $user->id || $this->isMember($user, $discussion) && $this->hasPermission($user, 'discussions.moderate'), 403);
        $discussion->update(['is_closed' => true, 'closed_at' => now()]);
        $this->audit->record($user->id, 'discussion.closed', 'discussion', $discussion->id);
    }

    /** @return array<string, mixed> */
    public function present(Discussion $discussion): array
    {
        $messages = DB::table('messages')->where('discussion_id', $discussion->id)->orderBy('created_at')->get()
            ->map(fn (object $message): array => ['id' => $message->id, 'discussion_id' => $message->discussion_id, 'author_id' => $message->created_by, 'body' => $message->body, 'attachments' => DB::table('attachments')->where('message_id', $message->id)->get(['id', 'original_name', 'mime_type', 'size_bytes', 'created_at']), 'created_at' => $message->created_at, 'likes_count' => DB::table('message_likes')->where('message_id', $message->id)->count()]);
        $verdict = DB::table('discussion_verdicts')->where('discussion_id', $discussion->id)->where('is_current', true)->first();

        return ['id' => $discussion->id, 'title' => $discussion->title, 'visibility' => $discussion->visibility, 'status' => $discussion->is_closed ? 'closed' : 'open', 'author_id' => $discussion->created_by, 'members' => DB::table('discussion_members')->where('discussion_id', $discussion->id)->pluck('user_id'), 'messages' => $messages, 'verdict' => $verdict === null ? null : ['message_id' => $verdict->message_id, 'assigned_by' => $verdict->assigned_by, 'created_at' => $verdict->created_at]];
    }

    private function canView(User $user, Discussion $discussion): bool
    {
        return $discussion->visibility === 'public' || $discussion->created_by === $user->id || $this->isMember($user, $discussion);
    }

    private function assertViewable(User $user, Discussion $discussion): void
    {
        abort_unless($this->canView($user, $discussion), 403);
    }

    private function assertAuthor(User $user, Discussion $discussion): void
    {
        abort_unless($discussion->created_by === $user->id, 403);
    }

    private function isMember(User $user, Discussion $discussion): bool
    {
        return DB::table('discussion_members')->where('discussion_id', $discussion->id)->where('user_id', $user->id)->exists();
    }

    private function assertMemberWithModeration(User $user, Discussion $discussion): void
    {
        abort_unless($this->isMember($user, $discussion) && $this->hasPermission($user, 'discussions.moderate'), 403);
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $permissions = $this->permissions[$user->id] ??= DB::table('user_roles')->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')->where('user_roles.user_id', $user->id)->pluck('permission_code')->all();

        return in_array($permission, $permissions, true);
    }
}
