<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist for the tests.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    public function test_register_creates_customer_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_login_returns_token_and_user_payload(): void
    {
        $user = User::create([
            'name' => 'Customer One',
            'email' => 'customer1@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer1@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::create([
            'name' => 'Inactive',
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_admin_ping_requires_admin_role(): void
    {
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        $customer->assignRole('customer');

        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/ping')->assertForbidden();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/ping')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }
}

