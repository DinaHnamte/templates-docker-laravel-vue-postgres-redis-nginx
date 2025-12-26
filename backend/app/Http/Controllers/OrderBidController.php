<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Order;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OrderBidController extends Controller
{
    public function listBids(Request $request, Order $order): JsonResponse
    {
        $this->authorizeCustomer($request, $order);

        $bids = $order->bids()->get()->map(function (Bid $bid) {
            if ($bid->status === 'pending' && $bid->expires_at && $bid->expires_at->isPast()) {
                $bid->update(['status' => 'expired']);
            }
            return $bid;
        });

        return response()->json($bids);
    }

    public function accept(Request $request, Order $order, Bid $bid): JsonResponse
    {
        $this->authorizeCustomer($request, $order);

        if ($order->id !== $bid->order_id) {
            abort(404);
        }

        if ($order->assignment()->exists()) {
            return response()->json(['message' => 'Order already assigned'], 422);
        }

        if ($order->status !== 'ready_for_delivery') {
            return response()->json(['message' => 'Order not ready for assignment'], 422);
        }

        if ($bid->status !== 'pending' || ($bid->expires_at && $bid->expires_at->isPast())) {
            return response()->json(['message' => 'Bid is not available'], 422);
        }

        $assignment = DB::transaction(function () use ($order, $bid) {
            $order->update(['status' => 'driver_assigned']);

            $order->statusEvents()->create([
                'status' => 'driver_assigned',
                'caused_by' => $order->customer_id,
            ]);

            $order->assignment()->create([
                'driver_id' => $bid->driver_id,
                'accepted_at' => now(),
            ]);

            $bid->update(['status' => 'accepted']);

            Bid::where('order_id', $order->id)
                ->where('id', '!=', $bid->id)
                ->where('status', 'pending')
                ->update(['status' => 'declined']);

            // Notify assigned driver
            $bid->driver?->notify(new GenericNotification(
                title: 'Bid accepted',
                body: "Your bid for order #{$order->id} was accepted.",
                data: ['order_id' => $order->id, 'bid_id' => $bid->id],
            ));

            return $order->assignment;
        });

        return response()->json($assignment->load('driver'), 201);
    }

    protected function authorizeCustomer(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($order->customer_id === $user->id, 403);
    }
}

