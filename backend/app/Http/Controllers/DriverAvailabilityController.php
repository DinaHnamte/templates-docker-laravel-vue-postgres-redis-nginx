<?php

namespace App\Http\Controllers;

use App\Models\DriverProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverAvailabilityController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:offline,online,busy'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $profile = DriverProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
        );

        $profile->fill([
            'availability_status' => $data['status'],
            'current_lat' => $data['lat'] ?? $profile->current_lat,
            'current_lng' => $data['lng'] ?? $profile->current_lng,
        ])->save();

        return response()->json($profile);
    }
}

