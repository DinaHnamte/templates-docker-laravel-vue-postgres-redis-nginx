<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTrackingController extends Controller
{
    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeViewer($request, $assignment);

        $assignment->load(['trackingPoints' => function ($q) {
            $q->latest('captured_at')->limit(20);
        }, 'order.address', 'order.vendor']);

        $latest = $assignment->trackingPoints->first();
        $etaMinutes = null;

        if ($latest && $assignment->order->address?->lat !== null && $assignment->order->vendor?->lat !== null) {
            $etaMinutes = $this->etaMinutes(
                $latest->lat,
                $latest->lng,
                $assignment->order->address->lat ?? $assignment->order->vendor->lat,
                $assignment->order->address->lng ?? $assignment->order->vendor->lng,
            );
        }

        return response()->json([
            'status' => $assignment->order->status,
            'latest_point' => $latest,
            'trail' => $assignment->trackingPoints,
            'eta_minutes' => $etaMinutes,
        ]);
    }

    protected function authorizeViewer(Request $request, Assignment $assignment): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('customer') && $assignment->order->customer_id === $user->id) {
            return;
        }

        if ($user->hasRole('vendor') && $assignment->order->vendor?->owner_id === $user->id) {
            return;
        }

        abort(403);
    }

    protected function etaMinutes(float $fromLat, float $fromLng, float $toLat, float $toLng): int
    {
        $distanceKm = $this->haversineKm($fromLat, $fromLng, $toLat, $toLng);
        $speedKmh = 30; // simple assumption
        $hours = $distanceKm / $speedKmh;
        return (int) ceil($hours * 60);
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

