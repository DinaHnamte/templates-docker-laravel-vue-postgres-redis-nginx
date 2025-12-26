<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole(['vendor', 'admin']), 403);

        $data = $this->validatedData($request);

        $vendor = Vendor::create([
            ...$data,
            'owner_id' => $user->id,
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        return response()->json($vendor, 201);
    }

    public function showMine(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $vendor = Vendor::where('owner_id', $user->id)->first();

        abort_if(! $vendor, 404, 'Vendor profile not found');

        return response()->json($vendor);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendor($request, $vendor);

        $data = $this->validatedData($request, update: true);

        if (isset($data['name']) && $data['name'] !== $vendor->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $vendor->id);
        }

        $vendor->update($data);

        return response()->json($vendor);
    }

    public function indexPublic(): JsonResponse
    {
        $vendors = Vendor::query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json($vendors);
    }

    protected function validatedData(Request $request, bool $update = false): array
    {
        $rules = [
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'formatted_address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'base_delivery_fee' => ['required', 'numeric', 'min:0'],
            'min_order_total' => ['required', 'numeric', 'min:0'],
            'allow_cod' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    protected function authorizeVendor(Request $request, Vendor $vendor): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->id === $vendor->owner_id && $user->hasRole('vendor'), 403);
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Vendor::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

