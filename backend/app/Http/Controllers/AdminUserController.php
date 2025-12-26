<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('roles')->orderBy('id')->paginate(50);

        return response()->json($users);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['sometimes', Rule::in(['customer', 'vendor', 'driver', 'admin'])],
        ]);

        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }

        if (array_key_exists('role', $data)) {
            $user->role = $data['role'];
            $user->syncRoles([$data['role']]);
        }

        $user->save();

        return response()->json($user->fresh()->load('roles'));
    }
}

