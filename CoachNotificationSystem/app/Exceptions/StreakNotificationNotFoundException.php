<?php

declare(strict_types=1);

namespace App\Exceptions;

class StreakNotificationNotFoundException extends \RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("StreakNotification #{$id} not found.");
    }
}
