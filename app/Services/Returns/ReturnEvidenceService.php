<?php

namespace App\Services\Returns;

use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReturnEvidenceService
{
    public function upload(ReturnRequest $return, User $actor, array $files): void
    {
        Validator::make(['images' => $files], [
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ])->validate();
        $stored = [];
        try {
            DB::transaction(function () use ($return, $actor, $files, &$stored) {
                $return = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('upload', $return);
                $limit = (int) app(SettingsService::class)->get('returns.evidence_limit', 5);
                if ($return->evidence()->count() + count($files) > $limit) {
                    throw ValidationException::withMessages(['images' => "Maximum {$limit} images per return."]);
                }
                // Private disk: evidence is served only through the authorized controller.
                $disk = 'local';
                foreach ($files as $file) {
                    $path = $file->store("returns/{$return->id}/evidence", $disk);
                    if (! $path) {
                        throw new \RuntimeException('Unable to store return evidence.');
                    }
                    $stored[] = [$disk, $path];
                    $return->evidence()->create(['uploaded_by' => $actor->id, 'disk' => $disk, 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }
    }
}
