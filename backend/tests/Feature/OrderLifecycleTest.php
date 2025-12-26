<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    protected function makeVendorUser(): User
    {
        $user = User::create([
            'name' => 'Vendor User',
            'email' => 'vendor@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('vendor');

        return $user;
    }

    public function test_vendor_confirms_and_marks_ready(): void
    {
        $vendorUser = $this->makeVendorUser();
        $vendor = Vendor::factory()->create(['owner_id' => $vendorUser->id]);

        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'pending_vendor_confirm',
        ]);

        Sanctum::actingAs($vendorUser);

        $this->patchJson("/api/orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'vendor_confirmed']);

        $this->patchJson("/api/orders/{$order->id}/ready")
            ->assertOk()
            ->assertJsonFragment(['status' => 'ready_for_delivery']);
    }

    public function test_only_owner_vendor_can_transition(): void
    {
        $owner = $this->makeVendorUser();
        $other = User::create([
            'name' => 'Other Vendor',
            'email' => 'other@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $other->assignRole('vendor');

        $vendor = Vendor::factory()->create(['owner_id' => $owner->id]);
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'pending_vendor_confirm',
        ]);

        Sanctum::actingAs($other);

        $this->patchJson("/api/orders/{$order->id}/confirm")->assertForbidden();
    }
}

