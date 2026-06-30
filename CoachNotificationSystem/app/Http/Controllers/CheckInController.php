<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CheckInResource;
use App\Models\CheckIn;
use App\Services\MilestoneDetector;
use App\Services\StreakCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CheckInController extends Controller
{
    public function store(Request $request, StreakCalculator $calculator, MilestoneDetector $detector): JsonResponse
    {
        $validated = $request->validate([
            'checked_in_date' => [
                'required',
                'date',
                Rule::unique('check_ins')->where('user_id', $request->user()->id),
            ],
        ]);

        $checkIn = $request->user()->checkIns()->create($validated);

        $currentStreak = $calculator->currentStreakFor($request->user());

        Log::info('check_in.created', [
            'user_id' => $request->user()->id,
            'date'    => $validated['checked_in_date'],
            'streak'  => $currentStreak,
        ]);

        $detector->checkAndNotify($request->user(), $currentStreak);

        $checkIn->setAttribute('current_streak', $currentStreak);

        return (new CheckInResource($checkIn))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, CheckIn $checkIn, StreakCalculator $calculator): JsonResponse
    {
        Gate::authorize('delete', $checkIn);

        $checkIn->delete();

        return response()->json(null, 204);
    }
}
