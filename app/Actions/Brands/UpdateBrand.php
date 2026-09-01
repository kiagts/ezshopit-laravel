<?php

namespace App\Actions\Brands;

use App\Actions\Concerns\StoresUploadedFiles;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateBrand
{
    use StoresUploadedFiles;

    public function handle(Brand $brand, Request $request): Brand
    {
        $oldLogo = $brand->logo_path;
        $newLogo = null;

        $updated = DB::transaction(function () use ($brand, $request, &$newLogo): Brand {
            $data = $request->safe()->except(['logo']);

            if (blank($data['slug'] ?? null)) {
                unset($data['slug']);
            }

            $data['is_active'] = $request->boolean('is_active');
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

            $brand->fill($data);

            if ($request->hasFile('logo')) {
                $newLogo = $this->storeUploadedFile($request->file('logo'), "brands/{$brand->id}/logo");
                $brand->logo_path = $newLogo;
            }

            $brand->save();

            return $brand;
        });

        if ($newLogo) {
            $this->deleteStoredFile($oldLogo);
        }

        return $updated;
    }
}
