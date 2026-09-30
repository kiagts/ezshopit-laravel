<?php

namespace App\Services\Disputes;

use App\Models\DisputeMessage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DisputeEvidenceService
{
    public function validate(array $files): void
    {
        $limit = max(1, min(5, (int) config('disputes.evidence_limit', 5)));
        Validator::make(['images' => $files], ['images' => "array|max:$limit", 'images.*' => 'required|file|image|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|extensions:jpg,jpeg,png,webp|max:5120'])->validate();
    }

    public function store(DisputeMessage $message, User $actor, array $files, array &$stored): void
    {
        foreach ($files as $file) {
            $path = $file->store('disputes/'.$message->dispute_id.'/evidence', 'local');
            if (! $path) {
                throw new \RuntimeException('Unable to store dispute evidence.');
            }
            $stored[] = $path;
            $message->evidence()->create(['uploaded_by' => $actor->id, 'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
        }
    }

    public function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
