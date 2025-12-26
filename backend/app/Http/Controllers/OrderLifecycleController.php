<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class OrderLifecycleController extends Controller
{
    public function vendorConfirm(Request $request, Order $order): JsonResponse
    {
        $this->authorizeVendor($request, $order);

        if ($order->status !== 'pending_vendor_confirm') {
            return response()->json(['message' => 'Order not in confirmable state'], 422);
        }

        $order->update([
            'status' => 'vendor_confirmed',
        ]);

        $order->statusEvents()->create([
            'status' => 'vendor_confirmed',
            'caused_by' => $request->user()->id,
        ]);

        return response()->json($order->fresh());
    }

    public function markReady(Request $request, Order $order): JsonResponse
    {
        $this->authorizeVendor($request, $order);

        if (! in_array($order->status, ['vendor_confirmed', 'pending_vendor_confirm'], true)) {
            return response()->json(['message' => 'Order not readyable'], 422);
        }

        $order->update([
            'status' => 'ready_for_delivery',
            'locked_at' => now(),
        ]);

        $order->statusEvents()->create([
            'status' => 'ready_for_delivery',
            'caused_by' => $request->user()->id,
        ]);

        // Notify customer that order is ready
        $order->customer?->notify(new GenericNotification(
            title: 'Order ready',
            body: "Your order #{$order->id} is ready for delivery.",
            data: ['order_id' => $order->id],
        ));

        return response()->json($order->fresh()->load('items'));
    }

    protected function authorizeVendor(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->hasRole('vendor') && $order->vendor?->owner_id === $user->id, 403);
    }
}

