<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function placeOrder(Request $request): JsonResponse
    {
        /** @var Cart $cart */
        $cart = Cart::with(['items.product.vendor', 'address', 'vendor'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $validator = Validator::make($cart->toArray(), [
            'fulfillment_type' => ['required', 'in:pickup,delivery'],
            'address_id' => ['required_if:fulfillment_type,delivery'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid cart state', 'errors' => $validator->errors()], 422);
        }

        if (! $cart->vendor) {
            return response()->json(['message' => 'Vendor is required'], 422);
        }

        $totals = $this->calculateTotals($cart);

        $order = DB::transaction(function () use ($cart, $totals, $request) {
            $order = Order::create([
                'customer_id' => $request->user()->id,
                'vendor_id' => $cart->vendor_id,
                'address_id' => $cart->fulfillment_type === 'delivery' ? $cart->address_id : null,
                'fulfillment_type' => $cart->fulfillment_type,
                'status' => 'pending_vendor_confirm',
                'subtotal' => $totals['subtotal'],
                'delivery_fee' => $totals['delivery_fee'],
                'service_fee' => $totals['service_fee'],
                'tax' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? 'Item',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'delivery_fee' => $cart->fulfillment_type === 'delivery' ? $item->delivery_fee : 0,
                ]);
            }

            $cart->items()->delete();
            $cart->update(['vendor_id' => null, 'address_id' => null]);

            return $order->load('items', 'vendor');
        });

        return response()->json($order, 201);
    }

    protected function calculateTotals(Cart $cart): array
    {
        $subtotal = 0;
        $delivery = 0;

        foreach ($cart->items as $item) {
            $subtotal += $item->quantity * $item->unit_price;
            if ($cart->fulfillment_type === 'delivery') {
                $delivery += $item->delivery_fee ?? 0;
            }
        }

        $service = 0;
        $tax = 0;
        $discount = 0;
        $total = $subtotal + $delivery + $service + $tax - $discount;

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery,
            'service_fee' => $service,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
        ];
    }
}

