<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CheckIn;
use App\Models\User;

class CheckInPolicy
{
    public function delete(User $user, CheckIn $checkIn): bool
    {
        return $user->id === $checkIn->user_id;
    }
}
