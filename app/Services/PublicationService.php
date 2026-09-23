<?php

namespace App\Services;

use App\Contracts\AttachmentStorage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class PublicationService
{
    /** @var array<string, list<string>> */
    private array $permissions = [];

    public function __construct(private readonly AuditLogger $audit, private readonly AttachmentStorage $attachments) {}

    /** @return list<array<string, mixed>> */
    public function index(User $user, string $type): array
    {
        $query = $this->modelClass($type)::query()->orderByDesc('created_at');
        if (! $this->canManage($user, $type)) {
            $query->where('status', 'published');
        }

        return $query->get()->map(fn (Model $publication): array => $this->present($publication, $type))->all();
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $user, string $type, array $attributes): Model
    {
        $this->requireManage($user, $type);
        $class = $this->modelClass($type);
        $publication = $class::query()->create([...$attributes, 'created_by' => $user->id, 'status' => 'draft']);
        $this->audit->record($user->id, 'publication.created', 'publication', $publication->id, ['type' => $type]);

        return $publication;
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $user, Model $publication, string $type, array $attributes): Model
    {
        $this->requireManage($user, $type);
        $publication->update($attributes);
        $this->audit->record($user->id, 'publication.updated', 'publication', $publication->id, ['type' => $type, 'fields_count' => count($attributes)]);

        return $publication->refresh();
    }

    public function publish(User $user, Model $publication, string $type): Model
    {
        $this->requireManage($user, $type);
        abort_if($publication->status === 'published', 409);

        return DB::transaction(function () use ($user, $publication, $type): Model {
            $publication->update(['status' => 'published', 'published_at' => now()]);
            $this->audit->record($user->id, 'publication.published', 'publication', $publication->id, ['type' => $type]);
            foreach (User::query()->select('id')->cursor() as $resident) {
                DB::table('notifications')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'user_id' => $resident->id,
                    'event_type' => 'publication.published',
                    'payload' => json_encode(['publication_id' => $publication->id, 'type' => $type], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }

            return $publication->refresh();
        });
    }

    public function unpublish(User $user, Model $publication, string $type): Model
    {
        $this->requireManage($user, $type);
        abort_unless($publication->status === 'published', 409);
        $publication->update(['status' => 'unpublished']);
        $this->audit->record($user->id, 'publication.unpublished', 'publication', $publication->id, ['type' => $type]);

        return $publication->refresh();
    }

    public function attach(User $user, Model $publication, string $type, UploadedFile $file): void
    {
        $this->requireManage($user, $type);
        $stored = $this->attachments->store($file);
        DB::table('attachments')->insert([
            'id' => $stored['id'], "{$type}_id" => $publication->id, 'created_by' => $user->id,
            'original_name' => $stored['original_name'], 'storage_path' => $stored['storage_key'],
            'mime_type' => $stored['mime_type'], 'size_bytes' => $stored['byte_size'], 'created_at' => now(),
        ]);
        $this->audit->record($user->id, 'publication.attachment_added', 'publication', $publication->id, ['type' => $type]);
    }

    /** @return array<string, mixed> */
    public function show(User $user, Model $publication, string $type): array
    {
        abort_unless($publication->status === 'published' || $this->canManage($user, $type), 403);

        return $this->present($publication, $type);
    }

    /** @return array<string, mixed> */
    public function present(Model $publication, string $type): array
    {
        return [
            'id' => $publication->id, 'type' => $type, 'title' => $publication->title, 'body' => $publication->body,
            'presentation_format' => $type === 'infoboard' ? $publication->presentation_format : null,
            'author_id' => $publication->created_by, 'status' => $publication->status,
            'created_at' => $publication->created_at, 'published_at' => $publication->published_at, 'updated_at' => $publication->updated_at,
            'attachments' => DB::table('attachments')->where("{$type}_id", $publication->id)->get(['id', 'original_name', 'mime_type', 'size_bytes', 'created_at']),
        ];
    }

    private function requireManage(User $user, string $type): void
    {
        abort_unless($this->canManage($user, $type), 403);
    }

    private function canManage(User $user, string $type): bool
    {
        return $this->hasPermission($user, $type === 'news' ? 'news.manage' : 'infoboards.manage');
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $permissions = $this->permissions[$user->id] ??= DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)->pluck('permission_code')->all();

        return in_array($permission, $permissions, true);
    }

    /** @return class-string<Model> */
    private function modelClass(string $type): string
    {
        return $type === 'news' ? \App\Models\News::class : \App\Models\Infoboard::class;
    }
}
