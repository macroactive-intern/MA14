<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checked_in_date' => $this->checked_in_date->toDateString(),
            'current_streak' => $this->current_streak,
        ];
    }
}
