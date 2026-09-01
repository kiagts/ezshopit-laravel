<?php

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_address_and_first_address_becomes_default(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer)
            ->post(route('account.addresses.store'), $this->addressData(['country_code' => 'us']))
            ->assertRedirect(route('account.addresses.index'));

        $address = CustomerAddress::query()->firstOrFail();

        $this->assertSame($customer->id, $address->user_id);
        $this->assertTrue($address->is_default);
        $this->assertSame('US', $address->country_code);
    }

    public function test_multiple_addresses_keep_only_one_default_and_explicit_default_is_atomic(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $home = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'label' => 'Home']);
        $office = CustomerAddress::factory()->create(['user_id' => $customer->id, 'label' => 'Office']);

        $this->actingAs($customer)
            ->put(route('account.addresses.default', $office))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertFalse($home->refresh()->is_default);
        $this->assertTrue($office->refresh()->is_default);
        $this->assertSame(1, CustomerAddress::query()->where('user_id', $customer->id)->where('is_default', true)->count());
    }

    public function test_deleting_default_promotes_recent_remaining_address_and_last_delete_leaves_none(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $home = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'label' => 'Home', 'updated_at' => now()->subDay()]);
        $office = CustomerAddress::factory()->create(['user_id' => $customer->id, 'label' => 'Office', 'updated_at' => now()]);

        $this->actingAs($customer)
            ->delete(route('account.addresses.destroy', $home))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertTrue($office->refresh()->is_default);

        $this->actingAs($customer)
            ->delete(route('account.addresses.destroy', $office))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertSame(0, CustomerAddress::query()->where('user_id', $customer->id)->where('is_default', true)->count());
    }

    public function test_customer_cannot_manage_another_customers_address(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $other = User::factory()->create(['role' => UserRole::Customer]);
        $address = CustomerAddress::factory()->create(['user_id' => $other->id]);

        $this->actingAs($customer)->get(route('account.addresses.edit', $address))->assertNotFound();
        $this->actingAs($customer)->put(route('account.addresses.update', $address), $this->addressData())->assertNotFound();
        $this->actingAs($customer)->put(route('account.addresses.default', $address))->assertNotFound();
        $this->actingAs($customer)->delete(route('account.addresses.destroy', $address))->assertNotFound();
    }

    public function test_guest_vendor_and_admin_cannot_access_customer_addresses(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::Vendor]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->get(route('account.addresses.index'))->assertRedirect(route('login'));
        $this->actingAs($vendor)->get(route('account.addresses.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('account.addresses.store'), $this->addressData())->assertForbidden();
    }

    public function test_address_limit_country_validation_and_international_addresses(): void
    {
        config(['marketplace.checkout.max_customer_addresses' => 1]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        CustomerAddress::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->post(route('account.addresses.store'), $this->addressData(['label' => 'Overflow']))
            ->assertSessionHasErrors('label');

        CustomerAddress::query()->delete();

        $this->actingAs($customer)
            ->post(route('account.addresses.store'), $this->addressData(['country_code' => 'ZZ']))
            ->assertSessionHasErrors('country_code');

        $this->actingAs($customer)
            ->post(route('account.addresses.store'), $this->addressData([
                'label' => 'Canada',
                'country_code' => 'CA',
                'state_region' => 'Ontario',
                'postal_code' => 'M5V 1A1',
            ]))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id,
            'country_code' => 'CA',
            'state_region' => 'Ontario',
        ]);
    }

    public function test_customer_address_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $customer = User::query()->where('email', 'customer@example.com')->firstOrFail();

        $this->assertSame(2, $customer->addresses()->count());
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
        $this->assertDatabaseHas('customer_addresses', ['user_id' => $customer->id, 'label' => 'Home', 'is_default' => true]);
        $this->assertDatabaseHas('customer_addresses', ['user_id' => $customer->id, 'label' => 'Office', 'is_default' => false]);
    }

    private function addressData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'first_name' => 'Jane',
            'last_name' => 'Customer',
            'phone' => '+1 555 0100',
            'address_line_1' => '123 Example Street',
            'address_line_2' => 'Apt 4',
            'city' => 'Example City',
            'state_region' => 'California',
            'postal_code' => '90210',
            'country_code' => 'US',
            'is_default' => '1',
        ], $overrides);
    }
}
