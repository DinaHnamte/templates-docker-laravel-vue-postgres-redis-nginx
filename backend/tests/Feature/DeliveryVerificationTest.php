<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Order;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeliveryVerificationTest extends TestCase
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

    public function test_generate_otp_and_verify_with_proximity(): void
    {
        $customer = $this->makeCustomer();
        $driver = $this->makeDriver();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'driver_assigned',
        ]);
        $address = $order->address()->create([
            'formatted_address' => '123 Main',
            'lat' => 37.0,
            'lng' => -122.0,
        ]);
        $order->update(['address_id' => $address->id]);

        $assignment = Assignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($customer);

        $resp = $this->postJson("/api/orders/{$order->id}/verification/otp")
            ->assertCreated()
            ->json();

        $code = $resp['code'];

        Sanctum::actingAs($driver);

        $this->postJson("/api/assignments/{$assignment->id}/verify", [
            'code' => $code,
            'lat' => 37.0005,
            'lng' => -122.0005,
        ])->assertOk();

        $this->assertNotNull(Verification::where('order_id', $order->id)->whereNotNull('verified_at')->first());
        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_verify_fails_when_far(): void
    {
        $customer = $this->makeCustomer();
        $driver = $this->makeDriver();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'driver_assigned',
        ]);
        $order->address()->create([
            'formatted_address' => '123 Main',
            'lat' => 37.0,
            'lng' => -122.0,
        ]);

        $assignment = Assignment::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        Verification::create([
            'order_id' => $order->id,
            'type' => 'otp',
            'code' => '999999',
        ]);

        Sanctum::actingAs($driver);

        $this->postJson("/api/assignments/{$assignment->id}/verify", [
            'code' => '999999',
            'lat' => 37.5,
            'lng' => -122.5,
        ])->assertStatus(422);
    }
}

