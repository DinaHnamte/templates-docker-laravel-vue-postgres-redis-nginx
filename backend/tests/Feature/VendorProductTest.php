<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendorProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    protected function createVendorUser(): User
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

    public function test_vendor_can_create_profile_with_location(): void
    {
        $user = $this->createVendorUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/vendors', [
            'name' => 'Test Vendor',
            'description' => 'Great food',
            'formatted_address' => '123 Main St',
            'lat' => 40.0,
            'lng' => -70.0,
            'base_delivery_fee' => 5.25,
            'min_order_total' => 10,
            'allow_cod' => true,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('vendors', [
            'owner_id' => $user->id,
            'name' => 'Test Vendor',
            'formatted_address' => '123 Main St',
            'lat' => 40.0,
            'lng' => -70.0,
        ]);
    }

    public function test_vendor_can_add_product_with_delivery_override(): void
    {
        $user = $this->createVendorUser();
        $vendor = Vendor::factory()->create([
            'owner_id' => $user->id,
            'base_delivery_fee' => 4.0,
            'min_order_total' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/vendors/{$vendor->id}/products", [
            'name' => 'Burger',
            'description' => 'Tasty burger',
            'price' => 8.5,
            'delivery_fee_override' => 2.5,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'vendor_id' => $vendor->id,
            'name' => 'Burger',
            'delivery_fee_override' => 2.5,
        ]);
    }

    public function test_vendor_cannot_manage_other_vendors_products(): void
    {
        $userA = $this->createVendorUser();
        $vendorA = Vendor::factory()->create(['owner_id' => $userA->id]);

        $userB = User::create([
            'name' => 'Other Vendor',
            'email' => 'other@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $userB->assignRole('vendor');

        $product = Product::factory()->create(['vendor_id' => $vendorA->id]);

        Sanctum::actingAs($userB);

        $this->patchJson("/api/vendors/{$vendorA->id}/products/{$product->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();

        $this->deleteJson("/api/vendors/{$vendorA->id}/products/{$product->id}")
            ->assertForbidden();
    }

    public function test_customer_can_view_public_catalog(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'is_active' => true,
            'name' => 'Public Item',
        ]);

        $this->getJson('/api/catalog/vendors')
            ->assertOk()
            ->assertJsonFragment(['name' => $vendor->name]);

        $this->getJson("/api/catalog/vendors/{$vendor->id}/products")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Public Item']);
    }
}

