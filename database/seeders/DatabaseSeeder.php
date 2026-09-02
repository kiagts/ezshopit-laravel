<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VendorBusinessType;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $this->call(BrandSeeder::class);
        $this->call(AttributeSeeder::class);
        $this->call(ProductAttributeSetSeeder::class);

        $admin = $this->user('Marketplace Admin', 'admin@example.com', UserRole::Admin);
        $this->user('John Customer', 'customer@example.com', UserRole::Customer);

        $approvedVendorUser = $this->user('Demo Vendor', 'vendor@example.com', UserRole::Vendor);
        $this->vendor($approvedVendorUser, [
            'business_name' => 'TechVerse Commerce Ltd',
            'display_name' => 'TechVerse',
            'slug' => 'techverse',
            'status' => VendorStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $pendingVendorUser = $this->user('Sara Merchant', 'pending.vendor@example.com', UserRole::Vendor);
        $this->vendor($pendingVendorUser, [
            'business_name' => 'Urban Outfit Collective',
            'display_name' => 'Urban Outfit Collective',
            'slug' => 'urban-outfit-collective',
            'status' => VendorStatus::Pending,
            'submitted_at' => now()->subDay(),
        ]);

        $rejectedVendorUser = $this->user('Bilal Traders', 'rejected.vendor@example.com', UserRole::Vendor);
        $this->vendor($rejectedVendorUser, [
            'business_name' => 'Bilal Home Traders',
            'display_name' => 'HomeCraft Traders',
            'slug' => 'homecraft-traders',
            'status' => VendorStatus::Rejected,
            'submitted_at' => now()->subDays(3),
            'rejection_reason' => 'Please provide a clearer store description and complete business contact details.',
            'rejected_at' => now()->subDays(2),
            'rejected_by' => $admin->id,
        ]);

        $suspendedVendorUser = $this->user('Nadia Supplies', 'suspended.vendor@example.com', UserRole::Vendor);
        $this->vendor($suspendedVendorUser, [
            'business_name' => 'Nadia Market Supplies',
            'display_name' => 'SupplyNest',
            'slug' => 'supplynest',
            'status' => VendorStatus::Suspended,
            'approved_at' => now()->subDays(10),
            'approved_by' => $admin->id,
        ]);

        $this->call(ProductSeeder::class);
        $this->call(CustomerAddressSeeder::class);
        $this->call(ShippingMethodSeeder::class);
        $this->call(InventorySeeder::class);
        $this->call(OrderLifecycleSeeder::class);
        $this->call(CommissionSeeder::class);
        $this->call(VendorPayoutSeeder::class);
        $this->call(ProductReviewSeeder::class);
        $this->call(WishlistSeeder::class);
        $this->call(CartSeeder::class);
    }

    private function user(string $name, string $email, UserRole $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->role = $role;
        $user->password = Hash::make('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function vendor(User $user, array $attributes): Vendor
    {
        $vendor = Vendor::query()->firstOrNew(['user_id' => $user->id]);
        $vendor->fill([
            'business_name' => $attributes['business_name'],
            'display_name' => $attributes['display_name'],
            'slug' => $attributes['slug'],
            'email' => $user->email,
            'phone' => '+92 300 1234567',
            'business_type' => VendorBusinessType::Company,
            'description' => 'A demonstration seller profile for marketplace onboarding and admin review workflows.',
            'country' => 'Pakistan',
            'city' => 'Karachi',
            'address_line_1' => 'Main Commercial Area',
            'postal_code' => '74000',
            'website' => 'https://example.com',
        ]);
        $vendor->forceFill([
            'status' => $attributes['status'],
            'submitted_at' => $attributes['submitted_at'] ?? now()->subDays(5),
            'approved_at' => $attributes['approved_at'] ?? null,
            'approved_by' => $attributes['approved_by'] ?? null,
            'rejection_reason' => $attributes['rejection_reason'] ?? null,
            'rejected_at' => $attributes['rejected_at'] ?? null,
            'rejected_by' => $attributes['rejected_by'] ?? null,
        ]);
        $vendor->save();

        return $vendor;
    }
}
