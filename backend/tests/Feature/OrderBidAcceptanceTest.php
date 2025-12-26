<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderBidAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    protected function makeCustomer(): User
    {
        $user = User::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('customer');

        return $user;
    }

    protected function makeDriver(string $email): User
    {
        $user = User::create([
            'name' => 'Driver',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'driver',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('driver');

        return $user;
    }

    public function test_customer_accepts_bid_and_assigns_driver(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'status' => 'ready_for_delivery',
        ]);

        $driver1 = $this->makeDriver('d1@example.com');
        $driver2 = $this->makeDriver('d2@example.com');

        $bid1 = Bid::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver1->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $bid2 = Bid::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver2->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->postJson("/api/orders/{$order->id}/bids/{$bid1->id}/accept")
            ->assertCreated();

        $this->assertDatabaseHas('assignments', [
            'order_id' => $order->id,
            'driver_id' => $driver1->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'driver_assigned',
        ]);

        $this->assertDatabaseHas('bids', [
            'id' => $bid1->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('bids', [
            'id' => $bid2->id,
            'status' => 'declined',
        ]);
    }

    public function test_cannot_accept_expired_bid(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'ready_for_delivery',
        ]);

        $bid = Bid::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(1),
        ]);

        $this->postJson("/api/orders/{$order->id}/bids/{$bid->id}/accept")
            ->assertStatus(422);
    }
}

