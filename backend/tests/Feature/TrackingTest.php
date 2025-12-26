<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Order;
use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    protected function makeDriver(string $email = 'driver@example.com'): User
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

    public function test_driver_posts_location_and_customer_can_view(): void
    {
        $customer = $this->makeCustomer();
        $driver = $this->makeDriver();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'driver_assigned',
        ]);

        $assignment = Assignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($driver);

        $this->postJson("/api/assignments/{$assignment->id}/location", [
            'lat' => 37.0,
            'lng' => -122.0,
        ])->assertCreated();

        Sanctum::actingAs($customer);

        $resp = $this->getJson("/api/assignments/{$assignment->id}/tracking")
            ->assertOk()
            ->json();

        $this->assertEquals('driver_assigned', $resp['status']);
        $this->assertNotEmpty($resp['latest_point']);
    }

    public function test_driver_can_mark_picked_up_and_delivered(): void
    {
        $driver = $this->makeDriver();

        $order = Order::factory()->create([
            'status' => 'driver_assigned',
        ]);

        $assignment = Assignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($driver);

        $this->postJson("/api/assignments/{$assignment->id}/picked-up")
            ->assertOk();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
        ]);

        $this->postJson("/api/assignments/{$assignment->id}/delivered")
            ->assertOk();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);
    }
}

