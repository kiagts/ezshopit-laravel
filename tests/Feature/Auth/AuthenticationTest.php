<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_as_customer_and_is_authenticated(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $response->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isCustomer());
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_registration_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Jane Customer',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
    }

    public function test_registration_password_validation_works(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_role_cannot_be_injected(): void
    {
        $this->post('/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
        ])->assertRedirect('/account');

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue($user->isCustomer());
    }

    public function test_valid_user_can_login(): void
    {
        $user = $this->user(UserRole::Customer);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_fails_login(): void
    {
        $user = $this->user(UserRole::Customer);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->user(UserRole::Customer);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_admin_access_rules(): void
    {
        $admin = $this->user(UserRole::Admin);
        $vendor = $this->vendorUser(VendorStatus::Approved);
        $customer = $this->user(UserRole::Customer);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($vendor)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/admin')->assertForbidden();

        auth()->logout();
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_vendor_access_rules(): void
    {
        $admin = $this->user(UserRole::Admin);
        $vendor = $this->vendorUser(VendorStatus::Approved);
        $customer = $this->user(UserRole::Customer);

        $this->actingAs($vendor)->get('/vendor')->assertOk();
        $this->actingAs($admin)->get('/vendor')->assertForbidden();
        $this->actingAs($customer)->get('/vendor')->assertForbidden();

        auth()->logout();
        $this->get('/vendor')->assertRedirect('/login');
    }

    public function test_customer_account_access_rules(): void
    {
        $admin = $this->user(UserRole::Admin);
        $vendor = $this->user(UserRole::Vendor);
        $customer = $this->user(UserRole::Customer);

        $this->actingAs($customer)->get('/account')->assertOk();
        $this->actingAs($admin)->get('/account')->assertForbidden();
        $this->actingAs($vendor)->get('/account')->assertForbidden();

        auth()->logout();
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_login_redirects_users_to_their_role_dashboard(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@example.com');
        $vendor = $this->vendorUser(VendorStatus::Approved, 'vendor@example.com');
        $customer = $this->user(UserRole::Customer, 'customer@example.com');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        auth()->logout();

        $this->post('/login', ['email' => $vendor->email, 'password' => 'password'])->assertRedirect('/vendor');
        auth()->logout();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect('/account');
    }

    public function test_authenticated_users_are_redirected_away_from_guest_auth_pages(): void
    {
        $admin = $this->user(UserRole::Admin);
        $vendor = $this->vendorUser(VendorStatus::Approved);
        $customer = $this->user(UserRole::Customer);

        $this->actingAs($admin)->get('/login')->assertRedirect('/admin');
        $this->actingAs($vendor)->get('/register')->assertRedirect('/vendor');
        $this->actingAs($customer)->get('/login')->assertRedirect('/account');
    }

    public function test_auth_pages_render_for_guests(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    private function user(UserRole $role, ?string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role' => $role,
            'password' => Hash::make('password'),
        ]);
    }

    private function vendorUser(VendorStatus $status, ?string $email = null): User
    {
        $user = $this->user(UserRole::Vendor, $email);

        Vendor::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        return $user;
    }
}
