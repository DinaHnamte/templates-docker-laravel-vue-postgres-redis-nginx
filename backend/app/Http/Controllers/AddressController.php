<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $address = Address::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($address, 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $this->authorizeOwner($request, $address);

        $data = $this->validated($request, update: true);

        $address->update($data);

        return response()->json($address);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorizeOwner($request, $address);

        $address->delete();

        return response()->json(status: 204);
    }

    public function distanceToVendor(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'exists:addresses,id'],
        ]);

        $address = Address::whereKey($data['address_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($address->lat === null || $address->lng === null || $vendor->lat === null || $vendor->lng === null) {
            return response()->json([
                'message' => 'Missing coordinates for distance calculation',
            ], 422);
        }

        $distanceKm = $this->haversineKm(
            $vendor->lat,
            $vendor->lng,
            $address->lat,
            $address->lng,
        );

        return response()->json([
            'distance_km' => round($distanceKm, 2),
        ]);
    }

    protected function validated(Request $request, bool $update = false): array
    {
        return $request->validate([
            'label' => [$update ? 'sometimes' : 'nullable', 'string', 'max:100'],
            'formatted_address' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'line1' => [$update ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }

    protected function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
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

