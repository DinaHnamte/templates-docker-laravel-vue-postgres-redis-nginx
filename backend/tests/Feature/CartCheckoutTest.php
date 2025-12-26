<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
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

    public function test_add_item_enforces_single_vendor(): void
    {
        $user = $this->makeCustomer();
        Sanctum::actingAs($user);

        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        $productA = Product::factory()->create(['vendor_id' => $vendorA->id]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id]);

        $this->postJson('/api/cart/items', [
            'product_id' => $productA->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/cart/items', [
            'product_id' => $productB->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_set_delivery_requires_address(): void
    {
        $user = $this->makeCustomer();
        Sanctum::actingAs($user);

        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/cart/fulfillment', [
            'fulfillment_type' => 'delivery',
        ])->assertStatus(422);

        $address = Address::create([
            'user_id' => $user->id,
            'formatted_address' => '123 Main',
        ]);

        $this->postJson('/api/cart/fulfillment', [
            'fulfillment_type' => 'delivery',
            'address_id' => $address->id,
        ])->assertOk()
            ->assertJsonFragment(['fulfillment_type' => 'delivery']);
    }

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        $user = $this->makeCustomer();
        Sanctum::actingAs($user);

        $vendor = Vendor::factory()->create(['base_delivery_fee' => 3.5]);
        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'price' => 10,
            'delivery_fee_override' => null,
        ]);

        $address = Address::create([
            'user_id' => $user->id,
            'formatted_address' => '123 Main',
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/cart/fulfillment', [
            'fulfillment_type' => 'delivery',
            'address_id' => $address->id,
        ])->assertOk();

        $response = $this->postJson('/api/checkout')->assertCreated();

        $response->assertJsonFragment([
            'vendor_id' => $vendor->id,
            'customer_id' => $user->id,
            'subtotal' => 20.0,
            'delivery_fee' => 3.5,
            'total' => 23.5,
        ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total' => 23.5,
        ]);

        // Cart should be cleared
        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonFragment(['items' => []]);
    }
}

