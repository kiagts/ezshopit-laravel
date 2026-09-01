<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function handle(Category $category): void
    {
        if ($category->hasChildren()) {
            throw ValidationException::withMessages([
                'category' => 'This category cannot be deleted because it contains subcategories.',
            ]);
        }

        $category->delete();
    }
}
