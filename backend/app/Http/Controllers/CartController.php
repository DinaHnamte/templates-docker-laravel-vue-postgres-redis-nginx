<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        return response()->json($this->serializeCart($cart));
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->getOrCreateCart($request);

        /** @var Product $product */
        $product = Product::whereKey($data['product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($cart->vendor_id && $cart->vendor_id !== $product->vendor_id) {
            return response()->json([
                'message' => 'Cart supports one vendor at a time. Clear cart to switch vendors.',
            ], 422);
        }

        $cart->vendor_id = $product->vendor_id;
        $cart->save();

        $item = $cart->items()->firstOrCreate(
            ['product_id' => $product->id],
            [
                'quantity' => 0,
                'unit_price' => $product->price,
                'delivery_fee' => $this->deliveryFeeForProduct($product),
            ],
        );

        $item->quantity += $data['quantity'];
        $item->unit_price = $product->price;
        $item->delivery_fee = $this->deliveryFeeForProduct($product);
        $item->save();

        $cart->refresh();

        return response()->json($this->serializeCart($cart), 201);
    }

    public function updateItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        abort_unless($item->cart_id === $cart->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item->update(['quantity' => $data['quantity']]);

        $cart->refresh();

        return response()->json($this->serializeCart($cart));
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        abort_unless($item->cart_id === $cart->id, 404);

        $item->delete();

        $cart->refresh();

        if ($cart->items()->count() === 0) {
            $cart->vendor_id = null;
            $cart->save();
        }

        return response()->json($this->serializeCart($cart));
    }

    public function setFulfillment(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        $data = $request->validate([
            'fulfillment_type' => ['required', 'in:pickup,delivery'],
            'address_id' => ['nullable', 'exists:addresses,id'],
        ]);

        $addressId = $data['address_id'] ?? null;

        if ($data['fulfillment_type'] === 'delivery') {
            if (! $addressId) {
                return response()->json(['message' => 'Address required for delivery'], 422);
            }

            /** @var Address $address */
            $address = Address::whereKey($addressId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $cart->address()->associate($address);
        } else {
            $cart->address()->dissociate();
        }

        $cart->fulfillment_type = $data['fulfillment_type'];
        $cart->save();

        $cart->refresh();

        return response()->json($this->serializeCart($cart));
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        $cart->items()->delete();
        $cart->update([
            'vendor_id' => null,
            'address_id' => null,
        ]);

        $cart->refresh();

        return response()->json($this->serializeCart($cart));
    }

    protected function getOrCreateCart(Request $request): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'fulfillment_type' => 'delivery',
            ],
        )->load(['items.product', 'address', 'vendor']);
    }

    protected function deliveryFeeForProduct(Product $product): float
    {
        return $product->delivery_fee_override ?? optional($product->vendor)->base_delivery_fee ?? 0;
    }

    protected function serializeCart(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'address', 'vendor']);

        $subtotal = 0;
        $delivery = 0;

        foreach ($cart->items as $item) {
            $subtotal += $item->quantity * $item->unit_price;
            if ($cart->fulfillment_type === 'delivery') {
                $delivery += $item->delivery_fee ?? 0;
            }
        }

        $total = $subtotal + $delivery;

        return [
            'id' => $cart->id,
            'vendor' => $cart->vendor,
            'items' => $cart->items,
            'fulfillment_type' => $cart->fulfillment_type,
            'address' => $cart->address,
            'totals' => [
                'subtotal' => $subtotal,
                'delivery_fee' => $delivery,
                'service_fee' => 0,
                'tax' => 0,
                'discount' => 0,
                'total' => $total,
            ],
        ];
    }
}

