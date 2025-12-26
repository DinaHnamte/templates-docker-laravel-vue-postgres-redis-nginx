<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendor($request, $vendor);

        $data = $this->validatedData($request);

        $product = $vendor->products()->create($data);

        return response()->json($product, 201);
    }

    public function update(Request $request, Vendor $vendor, Product $product): JsonResponse
    {
        $this->authorizeVendor($request, $vendor, $product);

        $data = $this->validatedData($request, update: true);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Request $request, Vendor $vendor, Product $product): JsonResponse
    {
        $this->authorizeVendor($request, $vendor, $product);

        $product->delete();

        return response()->json(status: 204);
    }

    public function listByVendor(Vendor $vendor): JsonResponse
    {
        $products = $vendor->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function showPublic(Vendor $vendor, Product $product): JsonResponse
    {
        abort_unless($product->vendor_id === $vendor->id && $product->is_active, 404);

        return response()->json($product);
    }

    protected function authorizeVendor(Request $request, Vendor $vendor, ?Product $product = null): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->hasRole('vendor') && $vendor->owner_id === $user->id, 403);

        if ($product && $product->vendor_id !== $vendor->id) {
            abort(404);
        }
    }

    protected function validatedData(Request $request, bool $update = false): array
    {
        $rules = [
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => [$update ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'delivery_fee_override' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}

