<?php

namespace App\Actions\Categories;

use App\Actions\Concerns\StoresUploadedFiles;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateCategory
{
    use StoresUploadedFiles;

    public function handle(Category $category, Request $request): Category
    {
        $oldImage = $category->image_path;
        $oldIcon = $category->icon_path;
        $newImage = null;
        $newIcon = null;

        $updated = DB::transaction(function () use ($category, $request, &$newImage, &$newIcon): Category {
            $data = $request->safe()->except(['image', 'icon']);

            if (blank($data['slug'] ?? null)) {
                unset($data['slug']);
            }

            $data['is_active'] = $request->boolean('is_active');
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

            $category->fill($data);

            if ($request->hasFile('image')) {
                $newImage = $this->storeUploadedFile($request->file('image'), "categories/{$category->id}/image");
                $category->image_path = $newImage;
            }

            if ($request->hasFile('icon')) {
                $newIcon = $this->storeUploadedFile($request->file('icon'), "categories/{$category->id}/icon");
                $category->icon_path = $newIcon;
            }

            $category->save();

            return $category;
        });

        if ($newImage) {
            $this->deleteStoredFile($oldImage);
        }

        if ($newIcon) {
            $this->deleteStoredFile($oldIcon);
        }

        return $updated;
    }
}
