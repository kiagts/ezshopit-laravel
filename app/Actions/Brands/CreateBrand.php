<?php

namespace App\Actions\Brands;

use App\Actions\Concerns\GeneratesUniqueSlugs;
use App\Actions\Concerns\StoresUploadedFiles;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateBrand
{
    use GeneratesUniqueSlugs, StoresUploadedFiles;

    public function handle(Request $request): Brand
    {
        return DB::transaction(function () use ($request): Brand {
            $data = $request->safe()->except(['logo']);
            $data['slug'] = filled($data['slug'] ?? null)
                ? $data['slug']
                : $this->uniqueSlugFor(Brand::class, $data['name']);
            $data['is_active'] = $request->boolean('is_active');
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

            $brand = Brand::query()->create($data);

            if ($request->hasFile('logo')) {
                $brand->logo_path = $this->storeUploadedFile($request->file('logo'), "brands/{$brand->id}/logo");
                $brand->save();
            }

            return $brand;
        });
    }
}
