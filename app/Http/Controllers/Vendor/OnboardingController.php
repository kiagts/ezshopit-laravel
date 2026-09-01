<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendors\SubmitVendorApplication;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorOnboardingRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $vendor = $this->vendorFor($request);

        if ($vendor->status === VendorStatus::Approved) {
            return redirect()->route('vendor.dashboard');
        }

        if (in_array($vendor->status, [VendorStatus::Pending, VendorStatus::Suspended], true)) {
            return redirect()->route('vendor.application-status');
        }

        return view('vendor.onboarding.edit', [
            'vendor' => $vendor,
        ]);
    }

    public function update(VendorOnboardingRequest $request): RedirectResponse
    {
        $vendor = $this->editableVendorFor($request);

        $this->fillVendor($vendor, $request);
        $vendor->status = VendorStatus::Draft;
        $vendor->save();

        return redirect()->route('vendor.onboarding.edit')->with('status', 'Store application draft saved.');
    }

    public function submit(VendorOnboardingRequest $request, SubmitVendorApplication $submitVendorApplication): RedirectResponse
    {
        $vendor = $this->editableVendorFor($request);

        $this->fillVendor($vendor, $request);
        $vendor->save();

        $submitVendorApplication->handle($vendor);

        return redirect()->route('vendor.application-status')->with('status', 'Your vendor application has been submitted for review.');
    }

    private function vendorFor(Request $request): Vendor
    {
        $user = $request->user();

        if ($user->vendor) {
            return $user->vendor;
        }

        $vendor = new Vendor;
        $vendor->user()->associate($user);
        $vendor->status = VendorStatus::Draft;
        $vendor->business_name = $user->name.' Store';
        $vendor->display_name = $user->name.' Store';
        $vendor->slug = $this->uniqueSlug($vendor->display_name);
        $vendor->email = $user->email;
        $vendor->save();

        return $vendor;
    }

    private function editableVendorFor(Request $request): Vendor
    {
        $vendor = $this->vendorFor($request);

        abort_unless(in_array($vendor->status, [VendorStatus::Draft, VendorStatus::Rejected], true), 403);

        return $vendor;
    }

    private function fillVendor(Vendor $vendor, VendorOnboardingRequest $request): void
    {
        $validated = $request->safe()->except(['logo', 'banner']);
        $validated['slug'] = $validated['slug'] ?? $this->uniqueSlug($validated['display_name'] ?? $validated['business_name'] ?? $vendor->display_name, $vendor);

        $vendor->fill($validated);

        if ($request->hasFile('logo')) {
            $this->replaceFile($vendor, 'logo_path', $request->file('logo')->store("vendors/{$vendor->id}/logo", 'public'));
        }

        if ($request->hasFile('banner')) {
            $this->replaceFile($vendor, 'banner_path', $request->file('banner')->store("vendors/{$vendor->id}/banner", 'public'));
        }
    }

    private function replaceFile(Vendor $vendor, string $attribute, string $path): void
    {
        if ($vendor->{$attribute}) {
            Storage::disk('public')->delete($vendor->{$attribute});
        }

        $vendor->{$attribute} = $path;
    }

    private function uniqueSlug(string $value, ?Vendor $ignoreVendor = null): string
    {
        $slug = Str::slug($value) ?: 'store';
        $candidate = $slug;
        $counter = 2;

        while (Vendor::query()
            ->when($ignoreVendor, fn ($query) => $query->whereKeyNot($ignoreVendor->id))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
