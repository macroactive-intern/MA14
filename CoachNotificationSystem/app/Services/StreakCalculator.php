<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class StreakCalculator
{
    public function currentStreakFor(User $user): int
    {
        $dates = $user->checkIns()
            ->orderBy('checked_in_date', 'desc')
            ->pluck('checked_in_date');

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 1;
        $current = Carbon::parse($dates->first());

        foreach ($dates->slice(1) as $date) {
            $previous = Carbon::parse($date);

            // Compare date strings to avoid Carbon 3 signed-diff behaviour
            if ($previous->toDateString() === $current->copy()->subDay()->toDateString()) {
                $streak++;
                $current = $previous;
            } else {
                break;
            }
        }

        return $streak;
    }
}
