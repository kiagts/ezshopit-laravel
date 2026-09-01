<?php

namespace App\Actions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GeneratesUniqueSlugs
{
    /**
     * @param class-string<Model> $modelClass
     */
    protected function uniqueSlugFor(string $modelClass, string $value, ?Model $ignoreModel = null): string
    {
        $slug = Str::slug($value) ?: 'item';
        $candidate = $slug;
        $counter = 2;

        while ($modelClass::query()
            ->when($ignoreModel, fn ($query) => $query->whereKeyNot($ignoreModel->getKey()))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
