<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Services\MilestoneDetector;
use App\Services\StreakCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $detector->checkAndNotify($request->user(), $currentStreak);

        return response()->json([
            'data' => [
                'id' => $checkIn->id,
                'checked_in_date' => $checkIn->checked_in_date->toDateString(),
                'current_streak' => $currentStreak,
            ],
        ], 201);
    }

    public function destroy(Request $request, CheckIn $checkIn, StreakCalculator $calculator): JsonResponse
    {
        if ($checkIn->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $checkIn->delete();

        $currentStreak = $calculator->currentStreakFor($request->user());

        return response()->json([
            'message' => 'Check-in deleted.',
            'current_streak' => $currentStreak,
        ]);
    }
}
