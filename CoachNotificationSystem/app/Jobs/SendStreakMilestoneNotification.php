<?php

namespace App\Jobs;

use App\Mail\StreakMilestoneNotification as StreakMilestoneNotificationMail;
use App\Models\StreakNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
            return;
        }

        Mail::to($notification->user)->send(
            new StreakMilestoneNotificationMail($notification->streak_milestone)
        );

        $notification->update(['notified_at' => now()]);
    }
}
