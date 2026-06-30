<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendStreakMilestoneNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $streakNotificationId
    ) {}

    public function handle(): void
    {
        // Implemented in Step 11
    }
}
