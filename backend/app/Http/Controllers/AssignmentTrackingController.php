<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\TrackingPoint;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AssignmentTrackingController extends Controller
{
    public function updateLocation(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $point = $assignment->trackingPoints()->create([
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'captured_at' => now(),
        ]);

        return response()->json($point, 201);
    }

    public function markPickedUp(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        if ($assignment->picked_up_at) {
            return response()->json($assignment, 200);
        }

        $assignment->update(['picked_up_at' => now()]);
        $assignment->order->update(['status' => 'en_route']);
        $assignment->order->statusEvents()->create([
            'status' => 'en_route',
            'caused_by' => $request->user()->id,
        ]);

        return response()->json($assignment->fresh(), 200);
    }

    public function markDelivered(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        if ($assignment->delivered_at) {
            return response()->json($assignment, 200);
        }

        DB::transaction(function () use ($assignment, $request) {
            $assignment->update(['delivered_at' => now()]);
            $assignment->order->update(['status' => 'delivered']);
            $assignment->order->statusEvents()->create([
                'status' => 'delivered',
                'caused_by' => $request->user()->id,
            ]);

            // Notify customer and vendor about delivery completion
            $assignment->order->customer?->notify(new GenericNotification(
                title: 'Order delivered',
                body: "Order #{$assignment->order_id} has been delivered.",
                data: ['order_id' => $assignment->order_id],
            ));

            $assignment->order->vendor?->owner?->notify(new GenericNotification(
                title: 'Order delivered',
                body: "Order #{$assignment->order_id} for your vendor has been delivered.",
                data: ['order_id' => $assignment->order_id],
            ));
        });

        return response()->json($assignment->fresh(), 200);
    }

    public function navigationLinks(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeDriver($request, $assignment);

        $vendor = $assignment->order->vendor;
        $address = $assignment->order->address;

        $pickupUrl = ($vendor && $vendor->lat !== null && $vendor->lng !== null)
            ? $this->mapsUrl($vendor->lat, $vendor->lng, $vendor->name)
            : null;

        $dropoffUrl = ($address && $address->lat !== null && $address->lng !== null)
            ? $this->mapsUrl($address->lat, $address->lng, $address->formatted_address ?? 'Dropoff')
            : null;

        return response()->json([
            'pickup_url' => $pickupUrl,
            'dropoff_url' => $dropoffUrl,
        ]);
    }

    protected function authorizeDriver(Request $request, Assignment $assignment): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->hasRole('driver') && $assignment->driver_id === $user->id, 403);
    }

    protected function mapsUrl(float $lat, float $lng, string $label): string
    {
        $query = urlencode($label);
        return "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}&destination_place_id=&travelmode=driving&query={$query}";
    }
}

