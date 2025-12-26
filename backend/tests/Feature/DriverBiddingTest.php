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

class DriverBiddingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    protected function makeDriver(): User
    {
        $user = User::create([
            'name' => 'Driver',
            'email' => 'driver@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'driver',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('driver');

        return $user;
    }

    public function test_open_orders_only_ready_and_unassigned(): void
    {
        $vendor = Vendor::factory()->create();
        $ready = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'ready_for_delivery',
        ]);
        $pending = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'pending_vendor_confirm',
        ]);

        Sanctum::actingAs($this->makeDriver());

        $this->getJson('/api/driver/open-orders')
            ->assertOk()
            ->assertJsonFragment(['id' => $ready->id])
            ->assertJsonMissing(['id' => $pending->id]);
    }

    public function test_eligibility_checks_status_and_existing_bid(): void
    {
        $driver = $this->makeDriver();
        Sanctum::actingAs($driver);

        $order = Order::factory()->create(['status' => 'ready_for_delivery']);

        $this->getJson("/api/orders/{$order->id}/bidding/eligibility")
            ->assertOk()
            ->assertJson(['eligible' => true]);

        Bid::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'amount' => 10,
            'status' => 'pending',
        ]);

        $this->getJson("/api/orders/{$order->id}/bidding/eligibility")
            ->assertOk()
            ->assertJson(['eligible' => false]);
    }

    public function test_driver_can_submit_bid_and_prevent_duplicate(): void
    {
        $driver = $this->makeDriver();
        Sanctum::actingAs($driver);

        $order = Order::factory()->create(['status' => 'ready_for_delivery']);

        $this->postJson("/api/orders/{$order->id}/bids", [
            'amount' => 12.5,
            'eta_minutes' => 15,
        ])->assertCreated();

        $this->postJson("/api/orders/{$order->id}/bids", [
            'amount' => 10,
            'eta_minutes' => 10,
        ])->assertStatus(422);
    }
}

