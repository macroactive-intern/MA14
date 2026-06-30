<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CheckIn extends Model
{
    protected $fillable = [
        'user_id',
        'checked_in_date',
    ];

    // Store as Y-m-d string; Eloquent's 'date' cast writes Y-m-d H:i:s in SQLite
    protected function checkedInDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
