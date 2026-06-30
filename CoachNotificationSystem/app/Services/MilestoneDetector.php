<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendStreakMilestoneNotification;
use App\Models\StreakNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MilestoneDetector
{
    public function checkAndNotify(User $user, int $currentStreak): void
    {
        $milestones = config('notifications.streak_milestones');

        if (! in_array($currentStreak, $milestones, strict: true)) {
            return;
        }

        DB::transaction(function () use ($user, $currentStreak): void {
            $existing = StreakNotification::where('user_id', $user->id)
                ->where('streak_milestone', $currentStreak)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return;
            }

            $notification = StreakNotification::create([
                'user_id'          => $user->id,
                'streak_milestone' => $currentStreak,
                'notified_at'      => null,
            ]);

            Log::info('milestone.detected', [
                'user_id'         => $user->id,
                'milestone'       => $currentStreak,
                'notification_id' => $notification->id,
            ]);

            SendStreakMilestoneNotification::dispatch($notification->id);
        });
    }
}
