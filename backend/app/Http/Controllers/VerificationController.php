<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Order;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function createOtp(Request $request, Order $order): JsonResponse
    {
        $this->authorizeCustomer($request, $order);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = Verification::updateOrCreate(
            ['order_id' => $order->id, 'type' => 'otp'],
            [
                'driver_id' => null,
                'code' => $code,
                'verified_at' => null,
            ],
        );

        return response()->json([
            'code' => $verification->code,
        ], 201);
    }

    public function verify(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $order = $assignment->order()->with(['address', 'vendor', 'payments'])->first();

        $verification = Verification::where('order_id', $order->id)
            ->where('type', 'otp')
            ->latest()
            ->first();

        if (! $verification || $verification->verified_at) {
            throw ValidationException::withMessages(['code' => ['No pending verification found']]);
        }

        if (hash_equals($verification->code, $data['code']) === false) {
            throw ValidationException::withMessages(['code' => ['Invalid code']]);
        }

        if (! $order->address || $order->address->lat === null || $order->address->lng === null) {
            return response()->json(['message' => 'Missing dropoff coordinates'], 422);
        }

        $distanceKm = $this->haversineKm(
            $data['lat'],
            $data['lng'],
            $order->address->lat,
            $order->address->lng,
        );

        if ($distanceKm > 0.3) { // 300m threshold
            throw ValidationException::withMessages(['location' => ['Too far from dropoff']]);
        }

        DB::transaction(function () use ($verification, $assignment, $order) {
            $verification->update([
                'driver_id' => $assignment->driver_id,
                'verified_at' => now(),
            ]);

            $assignment->update(['delivered_at' => now()]);
            $order->update(['status' => 'delivered']);
            $order->statusEvents()->create([
                'status' => 'delivered',
                'caused_by' => $assignment->driver_id,
            ]);

            foreach ($order->payments as $payment) {
                if ($payment->method === 'cod' && $payment->status !== 'paid') {
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }
            }
        });

        return response()->json(['status' => 'verified']);
    }

    protected function authorizeCustomer(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->hasRole('customer') && $order->customer_id === $user->id, 403);
    }

    protected function authorizeDriver(Request $request, Assignment $assignment): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->hasRole('driver') && $assignment->driver_id === $user->id, 403);
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

