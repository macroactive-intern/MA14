<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\StreakNotificationNotFoundException;
use App\Mail\StreakMilestoneNotification as StreakMilestoneNotificationMail;
use App\Models\StreakNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendStreakMilestoneNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $streakNotificationId
    ) {}

    public function handle(): void
    {
        $notification = StreakNotification::with('user')->find($this->streakNotificationId);

        if (! $notification) {
            throw new StreakNotificationNotFoundException($this->streakNotificationId);
        }

        Mail::to($notification->user)->send(
            new StreakMilestoneNotificationMail($notification->streak_milestone)
        );

        $notification->update(['notified_at' => now()]);

        Log::info('streak_notification.sent', [
            'notification_id' => $notification->id,
            'user_id'         => $notification->user_id,
            'milestone'       => $notification->streak_milestone,
        ]);
    }
}
