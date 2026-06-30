<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CheckIn;
use App\Policies\CheckInPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(CheckIn::class, CheckInPolicy::class);
    }
}
