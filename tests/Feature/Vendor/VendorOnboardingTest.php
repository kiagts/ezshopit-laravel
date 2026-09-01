<?php

namespace Tests\Feature\Vendor;

use App\Enums\UserRole;
use App\Enums\VendorBusinessType;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_seller_landing_and_registration(): void
    {
        $this->get('/sell')->assertOk();
        $this->get('/sell/register')->assertOk();
    }

    public function test_vendor_registration_creates_vendor_user_and_draft_vendor_record(): void
    {
        $response = $this->post('/sell/register', [
            'name' => 'Ayesha Merchant',
            'email' => 'seller@example.com',
            'phone' => '+92 300 1111111',
            'business_name' => 'Ayesha Market Ltd',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
        ]);

        $user = User::query()->where('email', 'seller@example.com')->firstOrFail();
        $vendor = $user->vendor;

        $response->assertRedirect('/vendor/onboarding');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isVendor());
        $this->assertNotNull($vendor);
        $this->assertTrue($vendor->status === VendorStatus::Draft);
        $this->assertSame('ayesha-market-ltd', $vendor->slug);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_vendor_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from('/sell/register')->post('/sell/register', [
            'name' => 'Ayesha Merchant',
            'email' => 'taken@example.com',
            'phone' => '+92 300 1111111',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/sell/register');
        $response->assertSessionHasErrors('email');
    }

    public function test_logged_in_customer_is_not_converted_to_vendor(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer)
            ->post('/sell/register', [
                'name' => 'Seller',
                'email' => 'seller@example.com',
                'phone' => '+92 300 1111111',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect('/sell');

        $this->assertTrue($customer->fresh()->isCustomer());
        $this->assertNull($customer->fresh()->vendor);
    }

    public function test_vendor_can_save_draft(): void
    {
        $vendor = $this->vendor(VendorStatus::Draft);

        $this->actingAs($vendor->user)
            ->patch('/vendor/onboarding', $this->validOnboardingData([
                'display_name' => 'Updated Store',
                'status' => VendorStatus::Approved->value,
            ]))
            ->assertRedirect('/vendor/onboarding');

        $vendor->refresh();
        $this->assertSame('Updated Store', $vendor->display_name);
        $this->assertTrue($vendor->status === VendorStatus::Draft);
    }

    public function test_vendor_can_submit_valid_application(): void
    {
        $vendor = $this->vendor(VendorStatus::Draft);

        $this->actingAs($vendor->user)
            ->post('/vendor/onboarding/submit', $this->validOnboardingData())
            ->assertRedirect('/vendor/application-status');

        $vendor->refresh();
        $this->assertTrue($vendor->status === VendorStatus::Pending);
        $this->assertNotNull($vendor->submitted_at);
    }

    public function test_incomplete_application_cannot_be_submitted(): void
    {
        $vendor = $this->vendor(VendorStatus::Draft);

        $this->actingAs($vendor->user)
            ->from('/vendor/onboarding')
            ->post('/vendor/onboarding/submit', [
                'business_name' => '',
            ])
            ->assertRedirect('/vendor/onboarding')
            ->assertSessionHasErrors(['business_name', 'display_name', 'email']);

        $this->assertTrue($vendor->fresh()->status === VendorStatus::Draft);
    }

    public function test_customer_and_admin_cannot_access_vendor_onboarding(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($customer)->get('/vendor/onboarding')->assertForbidden();
        $this->actingAs($admin)->get('/vendor/onboarding')->assertForbidden();
    }

    public function test_pending_rejected_and_suspended_status_pages_render(): void
    {
        $pending = $this->vendor(VendorStatus::Pending);
        $rejected = $this->vendor(VendorStatus::Rejected, ['rejection_reason' => 'Clarify your business details.']);
        $suspended = $this->vendor(VendorStatus::Suspended);

        $this->actingAs($pending->user)->get('/vendor/application-status')->assertOk()->assertSee('Application Under Review');
        $this->actingAs($rejected->user)->get('/vendor/application-status')->assertOk()->assertSee('Clarify your business details.');
        $this->actingAs($suspended->user)->get('/vendor/application-status')->assertOk()->assertSee('Store Suspended');
    }

    public function test_rejected_vendor_can_edit_and_resubmit(): void
    {
        $vendor = $this->vendor(VendorStatus::Rejected, [
            'rejection_reason' => 'Please revise details.',
            'rejected_at' => now(),
        ]);

        $this->actingAs($vendor->user)->get('/vendor/onboarding')->assertOk()->assertSee('Please revise details.');

        $this->actingAs($vendor->user)
            ->post('/vendor/onboarding/submit', $this->validOnboardingData())
            ->assertRedirect('/vendor/application-status');

        $vendor->refresh();
        $this->assertTrue($vendor->status === VendorStatus::Pending);
        $this->assertNull($vendor->rejection_reason);
        $this->assertNull($vendor->rejected_at);
    }

    public function test_vendor_cannot_manipulate_another_vendor_or_publicly_set_status(): void
    {
        $vendor = $this->vendor(VendorStatus::Draft);
        $otherVendor = $this->vendor(VendorStatus::Draft, ['display_name' => 'Other Store']);

        $this->actingAs($vendor->user)
            ->patch('/vendor/onboarding', $this->validOnboardingData([
                'vendor_id' => $otherVendor->id,
                'display_name' => 'My Safe Store',
                'status' => VendorStatus::Approved->value,
                'approved_at' => now()->toDateTimeString(),
            ]))
            ->assertRedirect('/vendor/onboarding');

        $this->assertSame('My Safe Store', $vendor->fresh()->display_name);
        $this->assertSame('Other Store', $otherVendor->fresh()->display_name);
        $this->assertTrue($vendor->fresh()->status === VendorStatus::Draft);
    }

    public function test_logo_and_banner_uploads_are_stored_and_invalid_files_are_rejected(): void
    {
        Storage::fake('public');
        $vendor = $this->vendor(VendorStatus::Draft);

        $this->actingAs($vendor->user)
            ->patch('/vendor/onboarding', $this->validOnboardingData([
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
                'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 400),
            ]))
            ->assertRedirect('/vendor/onboarding');

        $vendor->refresh();
        Storage::disk('public')->assertExists($vendor->logo_path);
        Storage::disk('public')->assertExists($vendor->banner_path);

        $this->actingAs($vendor->user)
            ->from('/vendor/onboarding')
            ->patch('/vendor/onboarding', $this->validOnboardingData([
                'logo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ]))
            ->assertRedirect('/vendor/onboarding')
            ->assertSessionHasErrors('logo');
    }

    public function test_vendor_dashboard_requires_approved_status(): void
    {
        $approved = $this->vendor(VendorStatus::Approved);
        $draft = $this->vendor(VendorStatus::Draft);
        $pending = $this->vendor(VendorStatus::Pending);
        $rejected = $this->vendor(VendorStatus::Rejected);
        $suspended = $this->vendor(VendorStatus::Suspended);

        $this->actingAs($approved->user)->get('/vendor')->assertOk()->assertSee($approved->display_name);
        $this->actingAs($draft->user)->get('/vendor')->assertRedirect('/vendor/onboarding');
        $this->actingAs($pending->user)->get('/vendor')->assertRedirect('/vendor/application-status');
        $this->actingAs($rejected->user)->get('/vendor')->assertRedirect('/vendor/application-status');
        $this->actingAs($suspended->user)->get('/vendor')->assertRedirect('/vendor/application-status');
    }

    public function test_admin_can_manage_vendor_applications(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $pending = $this->vendor(VendorStatus::Pending);
        $approved = $this->vendor(VendorStatus::Approved);
        $suspended = $this->vendor(VendorStatus::Suspended);

        $this->actingAs($admin)->get('/admin/vendors')->assertOk()->assertSee($pending->display_name);
        $this->actingAs($admin)->get("/admin/vendors/{$pending->id}")->assertOk()->assertSee($pending->display_name);

        $this->actingAs($admin)->post("/admin/vendors/{$pending->id}/approve")->assertRedirect();
        $this->assertTrue($pending->fresh()->status === VendorStatus::Approved);

        $pendingToReject = $this->vendor(VendorStatus::Pending);
        $this->actingAs($admin)
            ->from("/admin/vendors/{$pendingToReject->id}")
            ->post("/admin/vendors/{$pendingToReject->id}/reject", ['rejection_reason' => 'Needs clearer details.'])
            ->assertRedirect();
        $this->assertTrue($pendingToReject->fresh()->status === VendorStatus::Rejected);
        $this->assertSame('Needs clearer details.', $pendingToReject->fresh()->rejection_reason);

        $this->actingAs($admin)
            ->from("/admin/vendors/{$approved->id}")
            ->post("/admin/vendors/{$approved->id}/suspend")
            ->assertRedirect();
        $this->assertTrue($approved->fresh()->status === VendorStatus::Suspended);

        $this->actingAs($admin)
            ->from("/admin/vendors/{$suspended->id}")
            ->post("/admin/vendors/{$suspended->id}/reactivate")
            ->assertRedirect();
        $this->assertTrue($suspended->fresh()->status === VendorStatus::Approved);
    }

    public function test_non_admins_cannot_list_vendors(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $vendor = $this->vendor(VendorStatus::Approved);

        $this->actingAs($customer)->get('/admin/vendors')->assertForbidden();
        $this->actingAs($vendor->user)->get('/admin/vendors')->assertForbidden();
    }

    public function test_rejection_requires_reason_and_invalid_transitions_are_prevented(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $pending = $this->vendor(VendorStatus::Pending);
        $draft = $this->vendor(VendorStatus::Draft);
        $rejected = $this->vendor(VendorStatus::Rejected);

        $this->actingAs($admin)
            ->from("/admin/vendors/{$pending->id}")
            ->post("/admin/vendors/{$pending->id}/reject", [])
            ->assertRedirect("/admin/vendors/{$pending->id}")
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)
            ->from("/admin/vendors/{$draft->id}")
            ->post("/admin/vendors/{$draft->id}/suspend")
            ->assertRedirect("/admin/vendors/{$draft->id}")
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->from("/admin/vendors/{$rejected->id}")
            ->post("/admin/vendors/{$rejected->id}/approve")
            ->assertRedirect("/admin/vendors/{$rejected->id}")
            ->assertSessionHasErrors('status');
    }

    public function test_vendor_login_redirect_respects_vendor_status(): void
    {
        foreach ([
            [VendorStatus::Approved, '/vendor'],
            [VendorStatus::Draft, '/vendor/onboarding'],
            [VendorStatus::Pending, '/vendor/application-status'],
            [VendorStatus::Rejected, '/vendor/application-status'],
            [VendorStatus::Suspended, '/vendor/application-status'],
        ] as [$status, $path]) {
            $vendor = $this->vendor($status, ['email' => "{$status->value}@example.com"]);

            $this->post('/login', ['email' => $vendor->user->email, 'password' => 'password'])->assertRedirect($path);
            auth()->logout();
        }
    }

    public function test_vendor_user_without_vendor_record_is_recovered_to_onboarding(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Vendor,
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/vendor/onboarding');
        $this->actingAs($user)->get('/vendor/onboarding')->assertOk();

        $this->assertNotNull($user->fresh()->vendor);
    }

    public function test_vendor_user_and_slug_are_unique(): void
    {
        $vendor = $this->vendor(VendorStatus::Draft, ['slug' => 'unique-store']);

        $this->expectException(QueryException::class);

        Vendor::factory()->create([
            'user_id' => $vendor->user_id,
            'slug' => 'unique-store',
        ]);
    }

    private function vendor(VendorStatus $status, array $attributes = []): Vendor
    {
        $email = $attributes['email'] ?? fake()->unique()->safeEmail();
        unset($attributes['email']);

        $user = User::factory()->create([
            'email' => $email,
            'role' => UserRole::Vendor,
            'password' => Hash::make('password'),
        ]);

        return Vendor::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => $status,
        ], $attributes));
    }

    private function validOnboardingData(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'TechVerse Commerce Ltd',
            'display_name' => 'TechVerse',
            'slug' => 'techverse-'.fake()->unique()->numberBetween(1000, 9999),
            'business_type' => VendorBusinessType::Company->value,
            'description' => 'A focused marketplace seller for tested onboarding workflows.',
            'email' => 'store'.fake()->unique()->numberBetween(1000, 9999).'@example.com',
            'phone' => '+92 300 1111111',
            'website' => 'https://example.com',
            'country' => 'Pakistan',
            'city' => 'Karachi',
            'address_line_1' => 'Main Commercial Area',
            'address_line_2' => 'Suite 4',
            'postal_code' => '74000',
            'tax_number' => 'TAX-123',
            'registration_number' => 'REG-456',
        ], $overrides);
    }
}
