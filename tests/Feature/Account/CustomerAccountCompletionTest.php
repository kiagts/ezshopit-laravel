<?php

namespace Tests\Feature\Account;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\VendorOrderStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccountCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_is_role_protected_and_noindexed(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'John Customer']);
        $admin = User::factory()->admin()->create();

        $this->get(route('account.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->get(route('account.dashboard'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('noindex,nofollow', false)
            ->assertSeeText('Hi, John');
    }

    public function test_dashboard_shows_only_current_customer_summary_data(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        Order::factory()->for($customer)->create(['order_number' => 'ORD-CUSTOMER-1']);
        Order::factory()->for($other)->create(['order_number' => 'ORD-OTHER-1']);
        WishlistItem::factory()->create(['user_id' => $customer->id]);
        WishlistItem::factory()->create(['user_id' => $other->id]);
        CustomerAddress::factory()->default()->create([
            'user_id' => $customer->id,
            'address_line_1' => '12 Customer Street',
        ]);
        $this->storedNotification($customer, 'Customer message');
        $this->storedNotification($other, 'Other message');
        $this->deliveredReviewableItem($customer);

        $this->actingAs($customer)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSeeText('ORD-CUSTOMER-1')
            ->assertDontSeeText('ORD-OTHER-1')
            ->assertSeeText('12 Customer Street')
            ->assertSeeText('Customer message')
            ->assertDontSeeText('Other message')
            ->assertSeeTextInOrder(['Orders', '1', 'Wishlist', '1', 'Awaiting Review', '1', 'Unread Messages', '1']);
    }

    public function test_order_list_filters_and_order_detail_are_customer_scoped(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $activeOrder = Order::factory()->for($customer)->create([
            'order_number' => 'ORD-ACTIVE',
            'status' => OrderStatus::Processing,
        ]);
        Order::factory()->for($customer)->create([
            'order_number' => 'ORD-COMPLETE',
            'status' => OrderStatus::Completed,
        ]);
        Order::factory()->for($other)->create(['order_number' => 'ORD-PRIVATE']);

        $this->actingAs($customer)
            ->get(route('account.orders.index', ['filter' => 'active']))
            ->assertOk()
            ->assertSeeText('ORD-ACTIVE')
            ->assertDontSeeText('ORD-COMPLETE')
            ->assertDontSeeText('ORD-PRIVATE');

        $this->actingAs($customer)
            ->get(route('account.orders.show', $activeOrder))
            ->assertOk()
            ->assertSeeText('Order Summary')
            ->assertDontSeeText('Provider Reference');

        $this->actingAs($customer)
            ->get(route('account.orders.show', Order::query()->where('order_number', 'ORD-PRIVATE')->first()))
            ->assertNotFound();
    }

    public function test_customer_can_update_profile_without_changing_role(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($customer)
            ->put(route('account.profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'role' => UserRole::Admin->value,
            ])
            ->assertRedirect(route('account.profile.edit'));

        $customer->refresh();

        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new@example.com', $customer->email);
        $this->assertSame(UserRole::Customer, $customer->role);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $customer = User::factory()->customer()->create(['email' => 'customer@example.com']);
        User::factory()->customer()->create(['email' => 'taken@example.com']);

        $this->actingAs($customer)
            ->put(route('account.profile.update'), [
                'name' => 'Customer',
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_customer_can_change_password_with_current_password(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($customer)
            ->put(route('account.security.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('account.security.edit'));

        $this->assertAuthenticatedAs($customer);
        $this->assertTrue(Hash::check('new-secure-password', $customer->refresh()->password));
    }

    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($customer)
            ->put(route('account.security.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_non_customers_cannot_access_customer_profile_or_security(): void
    {
        $vendor = User::factory()->vendor()->create();

        $this->actingAs($vendor)->get(route('account.profile.edit'))->assertForbidden();
        $this->actingAs($vendor)->get(route('account.security.edit'))->assertForbidden();
    }

    private function deliveredReviewableItem(User $customer): OrderItem
    {
        $order = Order::factory()->for($customer)->create();
        $vendorOrder = VendorOrder::factory()->for($order)->create([
            'status' => VendorOrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        return OrderItem::factory()->for($vendorOrder)->create([
            'product_name' => 'Reviewable Product',
        ]);
    }

    private function storedNotification(User $user, string $title): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'database',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'test',
                'title' => $title,
                'message' => $title.' body.',
                'action_url' => '/account',
                'severity' => 'info',
                'source_key' => 'test:'.Str::uuid(),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
