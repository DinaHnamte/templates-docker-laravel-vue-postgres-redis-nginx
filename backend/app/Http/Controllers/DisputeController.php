<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorizeParticipant($request, $order);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'opened_by' => $request->user()->id,
            'status' => 'open',
            'reason' => $data['reason'],
        ]);

        return response()->json($dispute, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $disputes = Dispute::with(['order', 'opener'])->latest()->paginate(50);

        return response()->json($disputes);
    }

    public function update(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_review', 'resolved'])],
            'resolution_note' => ['nullable', 'string'],
        ]);

        $dispute->update($data);

        return response()->json($dispute->fresh());
    }

    protected function authorizeParticipant(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('customer') && $order->customer_id === $user->id) {
            return;
        }

        if ($user->hasRole('vendor') && $order->vendor?->owner_id === $user->id) {
            return;
        }

        if ($user->hasRole('driver') && $order->assignment?->driver_id === $user->id) {
            return;
        }

        abort(403);
    }

    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }
}

