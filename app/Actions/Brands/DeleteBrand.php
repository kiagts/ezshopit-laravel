<?php

namespace App\Actions\Brands;

use App\Models\Brand;

class DeleteBrand
{
    public function handle(Brand $brand): void
    {
        $brand->delete();
    }
}
