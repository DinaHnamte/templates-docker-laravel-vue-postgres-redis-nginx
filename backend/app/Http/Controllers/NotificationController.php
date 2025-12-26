<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->paginate(50);
        return response()->json($notifications);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $notificationId)->firstOrFail();
        $notification->markAsRead();
        return response()->json($notification);
    }
}

