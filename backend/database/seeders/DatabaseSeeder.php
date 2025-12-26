<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = collect([
            'customer',
            'vendor',
            'driver',
            'admin',
        ])->map(fn (string $name) => Role::firstOrCreate(['name' => $name]));

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([$roles->firstWhere('name', 'admin')]);

        // Bootstrap a demo customer for quick manual testing.
        $customer = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Demo Customer',
                'password' => bcrypt('password'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ],
        );

        $customer->syncRoles([$roles->firstWhere('name', 'customer')]);

        $vendorUser = User::firstOrCreate(
            ['email' => 'vendor@example.com'],
            [
                'name' => 'Demo Vendor',
                'password' => bcrypt('password'),
                'role' => 'vendor',
                'email_verified_at' => now(),
            ],
        );

        $vendorUser->syncRoles([$roles->firstWhere('name', 'vendor')]);

        $demoVendor = \App\Models\Vendor::firstOrCreate(
            ['owner_id' => $vendorUser->id],
            [
                'name' => 'Demo Pizza Place',
                'slug' => 'demo-pizza-place',
                'description' => 'Tasty demo pizzas',
                'formatted_address' => '123 Demo St',
                'lat' => 37.7749,
                'lng' => -122.4194,
                'base_delivery_fee' => 4.99,
                'min_order_total' => 15,
                'allow_cod' => true,
                'is_active' => true,
            ],
        );

        \App\Models\Product::firstOrCreate(
            ['vendor_id' => $demoVendor->id, 'name' => 'Margherita'],
            [
                'description' => 'Classic cheese pizza',
                'price' => 12.5,
                'delivery_fee_override' => null,
                'is_active' => true,
            ],
        );
    }
}
