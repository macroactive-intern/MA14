<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->streakNotifications()
            ->latest('notified_at')
            ->latest('created_at')
            ->get(['id', 'streak_milestone', 'notified_at']);

        return response()->json(['data' => $notifications]);
    }
}
