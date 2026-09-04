<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface AttachmentStorage
{
    /** @return array{id:string,storage_key:string,original_name:string,mime_type:string,byte_size:int,created_at:string} */
    public function store(UploadedFile $file): array;
}
