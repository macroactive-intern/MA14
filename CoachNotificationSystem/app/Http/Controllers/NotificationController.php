<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\StreakNotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->streakNotifications()
            ->latest('notified_at')
            ->latest('created_at')
            ->get();

        return StreakNotificationResource::collection($notifications);
    }
}
