<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        collect(['customer', 'vendor', 'driver', 'admin'])
            ->each(fn ($name) => Role::firstOrCreate(['name' => $name]));
    }

    protected function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        return $user;
    }

    public function test_customer_sees_bid_notification_and_can_mark_read(): void
    {
        $customer = $this->makeUser('customer', 'c@example.com');
        $driver = $this->makeUser('driver', 'd@example.com');

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'ready_for_delivery',
        ]);

        Sanctum::actingAs($driver);
        $this->postJson("/api/orders/{$order->id}/bids", [
            'amount' => 12.0,
        ])->assertCreated();

        Sanctum::actingAs($customer);

        $list = $this->getJson('/api/notifications')->assertOk()->json();
        $this->assertNotEmpty($list['data']);
        $notificationId = $list['data'][0]['id'];

        $this->postJson("/api/notifications/{$notificationId}/read")->assertOk();
    }
}

