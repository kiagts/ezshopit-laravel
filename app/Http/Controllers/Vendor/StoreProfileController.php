<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateStoreProfileRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StoreProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('vendor.store.edit', [
            'vendor' => $request->user()->vendor,
        ]);
    }

    public function update(UpdateStoreProfileRequest $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;

        $vendor->fill($request->safe()->except(['logo', 'banner']));

        if ($request->hasFile('logo')) {
            $this->replaceFile($vendor, 'logo_path', $request->file('logo')->store("vendors/{$vendor->id}/logo", 'public'));
        }

        if ($request->hasFile('banner')) {
            $this->replaceFile($vendor, 'banner_path', $request->file('banner')->store("vendors/{$vendor->id}/banner", 'public'));
        }

        $vendor->save();

        return redirect()
            ->route('vendor.store.edit')
            ->with('status', 'Store profile updated.');
    }

    private function replaceFile(Vendor $vendor, string $attribute, string $path): void
    {
        if ($vendor->{$attribute}) {
            Storage::disk('public')->delete($vendor->{$attribute});
        }

        $vendor->{$attribute} = $path;
    }
}
