<?php

namespace App\Services;

use App\Contracts\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LocalAttachmentStorage implements AttachmentStorage
{
    private const EXECUTABLE_EXTENSIONS = [
        'bat', 'cmd', 'com', 'exe', 'jar', 'msi', 'phar', 'php', 'php3', 'php4', 'php5', 'phtml', 'ps1', 'sh',
    ];

    public function store(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, self::EXECUTABLE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Исполняемые файлы не поддерживаются.']);
        }

        $id = (string) Str::uuid();
        $storageKey = "attachments/{$id}";
        Storage::disk('attachments')->putFileAs('attachments', $file, $id);

        return [
            'id' => $id,
            'storage_key' => $storageKey,
            'original_name' => basename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'byte_size' => $file->getSize(),
            'created_at' => now()->toAtomString(),
        ];
    }
}
