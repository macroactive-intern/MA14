<?php

namespace App\Services;

use App\Jobs\SendStreakMilestoneNotification;
use App\Models\StreakNotification;
use App\Models\User;

class MilestoneDetector
{
    private const MILESTONES = [7, 14, 21, 28];

    public function checkAndNotify(User $user, int $currentStreak): void
    {
        if (! in_array($currentStreak, self::MILESTONES, strict: true)) {
            return;
        }

        $notification = StreakNotification::firstOrCreate(
            [
                'user_id' => $user->id,
                'streak_milestone' => $currentStreak,
            ],
            [
                'notified_at' => null,
            ]
        );

        if ($notification->wasRecentlyCreated) {
            SendStreakMilestoneNotification::dispatch($notification->id);
        }
    }
}
