<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Order;
use App\Models\Vendor;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DriverBiddingController extends Controller
{
    public function openOrders(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('status', 'ready_for_delivery')
            ->whereDoesntHave('assignment')
            ->with(['vendor', 'address'])
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'vendor' => $order->vendor,
                    'address' => $order->address,
                    'distance_km' => $this->distanceVendorToAddress($order),
                    'destination' => [
                        'lat' => $order->address?->lat,
                        'lng' => $order->address?->lng,
                    ],
                    'pickup' => [
                        'lat' => $order->vendor?->lat,
                        'lng' => $order->vendor?->lng,
                    ],
                    'total' => $order->total,
                ];
            });

        return response()->json($orders);
    }

    public function eligibility(Request $request, Order $order): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->hasRole('driver') && ! $driver->hasRole('admin')) {
            return response()->json(['eligible' => false, 'reason' => 'Not a driver'], 403);
        }

        if ($order->status !== 'ready_for_delivery') {
            return response()->json(['eligible' => false, 'reason' => 'Order not ready'], 200);
        }

        if ($order->assignment()->exists()) {
            return response()->json(['eligible' => false, 'reason' => 'Already assigned'], 200);
        }

        $alreadyBid = Bid::where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->exists();

        if ($alreadyBid) {
            return response()->json(['eligible' => false, 'reason' => 'Already bid'], 200);
        }

        return response()->json(['eligible' => true]);
    }

    public function submitBid(Request $request, Order $order): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->hasRole('driver') && ! $driver->hasRole('admin')) {
            return response()->json(['message' => 'Only drivers can bid'], 403);
        }

        if ($order->status !== 'ready_for_delivery' || $order->assignment()->exists()) {
            return response()->json(['message' => 'Order not available for bidding'], 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'eta_minutes' => ['nullable', 'integer', 'min:1'],
            'expires_in_minutes' => ['nullable', 'integer', 'between:5,120'],
        ]);

        $alreadyBid = Bid::where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->first();

        if ($alreadyBid && $alreadyBid->status === 'pending' && (! $alreadyBid->expires_at || $alreadyBid->expires_at->isFuture())) {
            return response()->json(['message' => 'Already bid'], 422);
        }

        $expiresAt = now()->addMinutes($data['expires_in_minutes'] ?? 30);

        $bid = Bid::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'amount' => $data['amount'],
            'eta_minutes' => $data['eta_minutes'] ?? null,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        // Notify customer about new bid
        if ($order->customer) {
            $order->customer->notify(new GenericNotification(
                title: 'New delivery bid',
                body: "A driver offered to deliver your order #{$order->id} for {$bid->amount}",
                data: [
                    'order_id' => $order->id,
                    'bid_id' => $bid->id,
                ],
            ));
        }

        return response()->json($bid, 201);
    }

    protected function distanceVendorToAddress(Order $order): ?float
    {
        if (! $order->vendor || $order->vendor->lat === null || ! $order->address || $order->address->lat === null) {
            return null;
        }

        return round($this->haversineKm(
            $order->vendor->lat,
            $order->vendor->lng,
            $order->address->lat,
            $order->address->lng,
        ), 2);
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

