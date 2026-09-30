<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class HomepageImage
{
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = (string) $path;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'demo/') || str_starts_with($path, 'homepage/')) {
            return Storage::url($path);
        }

        return $path;
    }
}
