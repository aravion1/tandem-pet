<?php

namespace App\Services;

use App\Contracts\AttachmentStorage;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WorkItemService
{
    private const STATUSES = [
        'request' => ['queued', 'in_progress', 'completed', 'cancelled', 'rejected'],
        'task' => ['queued', 'in_progress', 'suspended', 'review', 'completed', 'cancelled'],
    ];

    private const TRANSITIONS = [
        'request' => [
            'queued' => ['in_progress', 'cancelled', 'rejected'],
            'in_progress' => ['completed', 'rejected'],
        ],
        'task' => [
            'queued' => ['in_progress', 'suspended', 'cancelled'],
            'in_progress' => ['suspended', 'review', 'cancelled'],
            'suspended' => ['in_progress', 'cancelled'],
            'review' => ['in_progress', 'completed', 'cancelled'],
        ],
    ];

    /** @var array<string, list<string>> */
    private array $permissions = [];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AttachmentStorage $attachments,
    ) {}

    /** @return list<array<string, mixed>> */
    public function index(User $user): array
    {
        return DB::table('work_items')->orderByDesc('created_at')->get()
            ->filter(fn (object $item): bool => $this->canView($user, $item))
            ->map(fn (object $item): array => $this->present($item))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function show(User $user, WorkItem $item): array
    {
        $this->assertViewable($user, $item);

        return $this->present($item);
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $user, array $attributes): WorkItem
    {
        if ($attributes['kind'] === 'task') {
            $this->requirePermission($user, 'tasks.manage');
        }

        $this->validateVisibility($attributes);
        $item = WorkItem::query()->create([
            ...Arr::only($attributes, ['kind', 'title', 'description', 'visibility', 'addressee_user_id', 'priority', 'due_at']),
            'status' => 'queued',
            'created_by' => $user->id,
        ]);
        $this->history($item->id, $user->id, 'created', null, ['kind' => $item->kind]);
        $this->audit->record($user->id, 'work_item.created', 'work_item', $item->id, ['kind' => $item->kind]);

        return $item;
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $user, WorkItem $item, array $attributes): WorkItem
    {
        $this->requireManage($user, $item);
        $merged = [...$item->only(['visibility', 'addressee_user_id']), ...$attributes];
        $this->validateVisibility($merged);

        DB::transaction(function () use ($user, $item, $attributes): void {
            foreach ($attributes as $field => $value) {
                if ($item->getAttribute($field) != $value) {
                    $this->history($item->id, $user->id, $field, $item->getAttribute($field), $value);
                }
            }
            $item->fill($attributes)->save();
            $this->audit->record($user->id, 'work_item.updated', 'work_item', $item->id, ['fields_count' => count($attributes)]);
        });

        return $item->refresh();
    }

    public function addComment(User $user, WorkItem $item, string $body): void
    {
        $this->assertViewable($user, $item);
        DB::table('work_comments')->insert([
            'id' => (string) Str::uuid(), 'work_item_id' => $item->id, 'created_by' => $user->id,
            'body' => $body, 'created_at' => now(),
        ]);
        $this->audit->record($user->id, 'work_item.comment_added', 'work_item', $item->id);
    }

    public function addAttachment(User $user, WorkItem $item, UploadedFile $file): void
    {
        $this->assertViewable($user, $item);
        $stored = $this->attachments->store($file);
        DB::table('work_attachments')->insert([
            'id' => $stored['id'], 'work_item_id' => $item->id, 'created_by' => $user->id,
            'original_name' => $stored['original_name'], 'storage_path' => $stored['storage_key'],
            'mime_type' => $stored['mime_type'], 'size_bytes' => $stored['byte_size'], 'created_at' => now(),
        ]);
        $this->audit->record($user->id, 'work_item.attachment_added', 'work_item', $item->id);
    }

    public function transition(User $user, WorkItem $item, string $status): WorkItem
    {
        abort_unless(in_array($status, self::STATUSES[$item->kind], true), 422);
        $isResidentCancellation = $item->kind === 'request' && $status === 'cancelled'
            && $item->status === 'queued' && $item->created_by === $user->id;

        if (! $isResidentCancellation && ! ($item->kind === 'task' && $status === 'completed')) {
            $this->requireManage($user, $item);
        }
        if ($item->kind === 'task' && $status === 'completed') {
            $this->requirePermission($user, 'tasks.close');
        }
        abort_unless(in_array($status, self::TRANSITIONS[$item->kind][$item->status] ?? [], true), 409);

        DB::transaction(function () use ($user, $item, $status): void {
            $this->history($item->id, $user->id, 'status', $item->status, $status);
            $item->forceFill(['status' => $status])->save();
            $this->audit->record($user->id, 'work_item.status_changed', 'work_item', $item->id, ['status' => $status]);
        });

        return $item->refresh();
    }

    public function convert(User $user, WorkItem $item, string $kind): WorkItem
    {
        abort_unless($kind !== $item->kind, 409);
        $this->requireManage($user, $item);
        if ($kind === 'task') {
            $this->requirePermission($user, 'tasks.manage');
        }

        $status = in_array($item->status, self::STATUSES[$kind], true) ? $item->status : 'queued';
        DB::transaction(function () use ($user, $item, $kind, $status): void {
            $this->history($item->id, $user->id, 'kind', $item->kind, $kind);
            if ($status !== $item->status) {
                $this->history($item->id, $user->id, 'status', $item->status, $status);
            }
            $item->forceFill(['kind' => $kind, 'status' => $status])->save();
            $this->audit->record($user->id, 'work_item.converted', 'work_item', $item->id, ['kind' => $kind]);
        });

        return $item->refresh();
    }

    /** @param list<array{user_id?:string,external_name?:string}> $assignees */
    public function replaceAssignees(User $user, WorkItem $item, array $assignees): void
    {
        $this->assertTask($item);
        $this->requirePermission($user, 'tasks.assign');
        DB::transaction(function () use ($user, $item, $assignees): void {
            DB::table('work_assignees')->where('work_item_id', $item->id)->delete();
            foreach ($assignees as $assignee) {
                DB::table('work_assignees')->insert([
                    'id' => (string) Str::uuid(), 'work_item_id' => $item->id,
                    'user_id' => $assignee['user_id'] ?? null, 'external_name' => $assignee['external_name'] ?? null,
                ]);
            }
            $this->history($item->id, $user->id, 'assignees', null, ['count' => count($assignees)]);
            $this->audit->record($user->id, 'work_item.assignees_changed', 'work_item', $item->id, ['count' => count($assignees)]);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addTimeEntry(User $user, WorkItem $item, array $attributes): void
    {
        $this->assertTask($item);
        $this->requirePermission($user, 'budget.manage');
        DB::table('time_entries')->insert(['id' => (string) Str::uuid(), 'work_item_id' => $item->id,
            'created_by' => $user->id, ...$attributes, 'created_at' => now(), 'updated_at' => now()]);
        $this->history($item->id, $user->id, 'time_entry', null, ['minutes' => $attributes['minutes']]);
        $this->audit->record($user->id, 'work_item.time_added', 'work_item', $item->id, ['minutes' => $attributes['minutes']]);
    }

    /** @param array<string, mixed> $attributes */
    public function addBudgetEntry(User $user, WorkItem $item, array $attributes): void
    {
        $this->assertTask($item);
        $this->requirePermission($user, 'budget.manage');
        DB::table('budget_entries')->insert(['id' => (string) Str::uuid(), 'work_item_id' => $item->id,
            'created_by' => $user->id, ...$attributes, 'currency' => strtoupper($attributes['currency']), 'created_at' => now(), 'updated_at' => now()]);
        $this->history($item->id, $user->id, 'budget_entry', null, ['amount' => $attributes['amount']]);
        $this->audit->record($user->id, 'work_item.budget_added', 'work_item', $item->id, ['amount' => $attributes['amount']]);
    }

    /** @return array<string, mixed> */
    public function present(object $item): array
    {
        $id = $item->id;

        return [
            'id' => $id, 'kind' => $item->kind, 'title' => $item->title, 'description' => $item->description,
            'visibility' => $item->visibility, 'addressee' => $item->addressee_user_id === null ? null : ['id' => $item->addressee_user_id],
            'status' => $item->status, 'priority' => $item->priority, 'due_at' => $item->due_at,
            'assignees' => DB::table('work_assignees')->where('work_item_id', $id)->get(['user_id', 'external_name']),
            'attachments' => DB::table('work_attachments')->where('work_item_id', $id)->get(['id', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'created_at']),
            'comments' => DB::table('work_comments')->where('work_item_id', $id)->orderBy('created_at')->get(['id', 'created_by', 'body', 'created_at']),
            'history' => DB::table('work_history')->where('work_item_id', $id)->orderBy('created_at')->get(),
            'time_entries' => DB::table('time_entries')->where('work_item_id', $id)->orderBy('recorded_at')->get(),
            'budget_entries' => DB::table('budget_entries')->where('work_item_id', $id)->orderBy('recorded_at')->get(),
        ];
    }

    private function canView(User $user, object $item): bool
    {
        if ($item->kind === 'task') {
            return $this->hasPermission($user, 'tasks.view');
        }
        if ($item->created_by === $user->id || $item->addressee_user_id === $user->id) {
            return true;
        }

        return $item->visibility === 'public'
            ? $this->hasPermission($user, 'requests.view_public')
            : $this->hasPermission($user, 'requests.view_private');
    }

    private function assertViewable(User $user, WorkItem $item): void
    {
        abort_unless($this->canView($user, $item), 403);
    }

    private function requireManage(User $user, WorkItem $item): void
    {
        $this->requirePermission($user, $item->kind === 'task' ? 'tasks.manage' : 'requests.moderate');
    }

    private function requirePermission(User $user, string $permission): void
    {
        abort_unless($this->hasPermission($user, $permission), 403);
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $permissions = $this->permissions[$user->id] ??= DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)->pluck('permission_code')->all();

        return in_array($permission, $permissions, true);
    }

    /** @param array<string, mixed> $attributes */
    private function validateVisibility(array $attributes): void
    {
        abort_if(($attributes['visibility'] ?? null) === 'private' && empty($attributes['addressee_user_id']), 422);
    }

    private function assertTask(WorkItem $item): void
    {
        abort_unless($item->kind === 'task', 422);
    }

    private function history(string $workItemId, ?string $actorId, string $field, mixed $oldValue, mixed $newValue): void
    {
        DB::table('work_history')->insert([
            'id' => (string) Str::uuid(), 'work_item_id' => $workItemId, 'actor_user_id' => $actorId,
            'field' => $field, 'old_value' => $oldValue === null ? null : json_encode($oldValue, JSON_THROW_ON_ERROR),
            'new_value' => $newValue === null ? null : json_encode($newValue, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
