<?php

namespace App\Actions\Categories;

use App\Actions\Concerns\GeneratesUniqueSlugs;
use App\Actions\Concerns\StoresUploadedFiles;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateCategory
{
    use GeneratesUniqueSlugs, StoresUploadedFiles;

    public function handle(Request $request): Category
    {
        return DB::transaction(function () use ($request): Category {
            $data = $request->safe()->except(['image', 'icon']);
            $data['slug'] = filled($data['slug'] ?? null)
                ? $data['slug']
                : $this->uniqueSlugFor(Category::class, $data['name']);
            $data['is_active'] = $request->boolean('is_active');
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

            $category = Category::query()->create($data);

            if ($request->hasFile('image')) {
                $category->image_path = $this->storeUploadedFile($request->file('image'), "categories/{$category->id}/image");
            }

            if ($request->hasFile('icon')) {
                $category->icon_path = $this->storeUploadedFile($request->file('icon'), "categories/{$category->id}/icon");
            }

            $category->save();

            return $category;
        });
    }
}
