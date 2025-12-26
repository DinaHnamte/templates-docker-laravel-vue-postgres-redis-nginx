<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AddressTest extends TestCase
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

    public function test_user_can_create_and_list_addresses(): void
    {
        $user = $this->makeCustomer();
        Sanctum::actingAs($user);

        $this->postJson('/api/addresses', [
            'formatted_address' => '123 Main',
            'lat' => 40.0,
            'lng' => -70.0,
        ])->assertCreated();

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonFragment(['formatted_address' => '123 Main']);
    }

    public function test_user_cannot_update_other_users_address(): void
    {
        $user = $this->makeCustomer();
        Sanctum::actingAs($user);

        $other = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        $other->assignRole('customer');

        $address = Address::create([
            'user_id' => $other->id,
            'formatted_address' => 'Other Address',
        ]);

        $this->patchJson("/api/addresses/{$address->id}", [
            'formatted_address' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_distance_to_vendor(): void
    {
        $user = $this->makeCustomer();
        Sanctum::actingAs($user);

        $address = Address::create([
            'user_id' => $user->id,
            'formatted_address' => '123 Main',
            'lat' => 37.7749,
            'lng' => -122.4194,
        ]);

        $vendor = Vendor::factory()->create([
            'lat' => 34.0522,
            'lng' => -118.2437,
        ]);

        $this->getJson("/api/addresses/distance/vendor/{$vendor->id}?address_id={$address->id}")
            ->assertOk()
            ->assertJsonStructure(['distance_km']);
    }
}

