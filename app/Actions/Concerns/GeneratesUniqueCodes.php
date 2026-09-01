<?php

namespace App\Actions\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GeneratesUniqueCodes
{
    /**
     * @param class-string<Model> $modelClass
     */
    protected function uniqueCodeFor(
        string $modelClass,
        string $value,
        string $column = 'code',
        ?Model $ignoreModel = null,
        string $separator = '_',
        ?Closure $scope = null,
    ): string {
        $base = Str::slug($value, $separator) ?: 'item';
        $candidate = $base;
        $counter = 2;

        while ($modelClass::query()
            ->when($scope, fn (Builder $query): Builder => $scope($query) ?? $query)
            ->when($ignoreModel, fn (Builder $query): Builder => $query->whereKeyNot($ignoreModel->getKey()))
            ->where($column, $candidate)
            ->exists()) {
            $candidate = "{$base}{$separator}{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
