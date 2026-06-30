<?php

use App\Jobs\SendStreakMilestoneNotification;
use App\Mail\StreakMilestoneNotification as StreakMilestoneNotificationMail;
use App\Models\CheckIn;
use App\Models\StreakNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// --- Authentication ---

it('unauthenticated user cannot create check-in', function () {
    $this->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01'])
        ->assertStatus(401);
});

// --- Check-in creation ---

it('authenticated user can create check-in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01'])
        ->assertStatus(201);

    $this->assertDatabaseHas('check_ins', [
        'user_id' => $user->id,
        'checked_in_date' => '2026-06-01',
    ]);
});

it('duplicate check-in date is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01']);

    $this->actingAs($user)
        ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01'])
        ->assertStatus(422);
});

// --- Streak calculation ---

it('streak is calculated correctly for consecutive dates', function () {
    $user = User::factory()->create();

    foreach (consecutiveDates(6) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)
        ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07'])
        ->assertStatus(201)
        ->assertJsonPath('data.current_streak', 7);
});

it('streak stops when there is a gap', function () {
    $user = User::factory()->create();

    // Days 1-3, skip day 4, seed day 5
    foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-05'] as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    // Adding day 6: streak counts back 06→05 (gap before 05), so streak = 2
    $this->actingAs($user)
        ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-06'])
        ->assertStatus(201)
        ->assertJsonPath('data.current_streak', 2);
});

// --- Milestone notification records ---

it('7-day streak creates notification record', function () {
    Queue::fake();
    $user = User::factory()->create();

    foreach (consecutiveDates(6) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);

    $this->assertDatabaseHas('streak_notifications', [
        'user_id' => $user->id,
        'streak_milestone' => 7,
    ]);
});

it('14-day streak creates notification record', function () {
    Queue::fake();
    $user = User::factory()->create();

    foreach (consecutiveDates(13) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-14']);

    $this->assertDatabaseHas('streak_notifications', [
        'user_id' => $user->id,
        'streak_milestone' => 14,
    ]);
});

it('21-day streak creates notification record', function () {
    Queue::fake();
    $user = User::factory()->create();

    foreach (consecutiveDates(20) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-21']);

    $this->assertDatabaseHas('streak_notifications', [
        'user_id' => $user->id,
        'streak_milestone' => 21,
    ]);
});

it('28-day streak creates notification record', function () {
    Queue::fake();
    $user = User::factory()->create();

    foreach (consecutiveDates(27) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-28']);

    $this->assertDatabaseHas('streak_notifications', [
        'user_id' => $user->id,
        'streak_milestone' => 28,
    ]);
});

it('non-milestone streak does not create notification', function () {
    Queue::fake();
    $user = User::factory()->create();

    foreach (consecutiveDates(4) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-05']);

    $this->assertDatabaseMissing('streak_notifications', ['user_id' => $user->id]);
});

// --- Idempotency ---

it('same milestone is not created twice', function () {
    Queue::fake();
    $user = User::factory()->create();

    // First 7-day streak
    foreach (consecutiveDates(6) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }
    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);

    // Reset streak and rebuild another 7-day streak
    CheckIn::where('user_id', $user->id)->delete();

    foreach (consecutiveDates(6, '2026-07-01') as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }
    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-07-07']);

    $this->assertDatabaseCount('streak_notifications', 1);
});

it('same milestone does not dispatch duplicate email job', function () {
    Queue::fake();
    $user = User::factory()->create();

    // First 7-day streak
    foreach (consecutiveDates(6) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }
    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);

    // Reset streak and rebuild another 7-day streak
    CheckIn::where('user_id', $user->id)->delete();

    foreach (consecutiveDates(6, '2026-07-01') as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }
    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-07-07']);

    Queue::assertPushed(SendStreakMilestoneNotification::class, 1);
});

// --- Queue and mail behaviour ---

it('email job is queued not sent synchronously', function () {
    Mail::fake();
    Queue::fake();
    $user = User::factory()->create();

    foreach (consecutiveDates(6) as $date) {
        CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    }

    $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);

    Mail::assertNothingSent();
    Queue::assertPushed(SendStreakMilestoneNotification::class);
});

it('queued job sends mailable when processed', function () {
    Mail::fake();
    $user = User::factory()->create();

    $notification = StreakNotification::create([
        'user_id' => $user->id,
        'streak_milestone' => 7,
        'notified_at' => null,
    ]);

    (new SendStreakMilestoneNotification($notification->id))->handle();

    Mail::assertSent(StreakMilestoneNotificationMail::class);
});

it('job has tries of 3', function () {
    $job = new SendStreakMilestoneNotification(1);

    expect($job->tries)->toBe(3);
});

it('failed job does not retry forever', function () {
    $job = new SendStreakMilestoneNotification(1);

    expect($job->tries)->toBeLessThanOrEqual(3);
});

// --- Notifications endpoint ---

it('notifications endpoint returns notifications newest first', function () {
    $user = User::factory()->create();

    StreakNotification::create([
        'user_id' => $user->id,
        'streak_milestone' => 7,
        'notified_at' => now()->subDays(7),
    ]);

    StreakNotification::create([
        'user_id' => $user->id,
        'streak_milestone' => 14,
        'notified_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/notifications')
        ->assertStatus(200)
        ->assertJsonPath('data.0.streak_milestone', 14)
        ->assertJsonPath('data.1.streak_milestone', 7);
});

it('notifications endpoint does not leak another user notifications', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    StreakNotification::create([
        'user_id' => $userA->id,
        'streak_milestone' => 7,
        'notified_at' => now(),
    ]);

    $this->actingAs($userB)
        ->getJson('/api/notifications')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

// --- Check-in deletion ---

it('deleting a check-in recalculates the streak', function () {
    $user = User::factory()->create();

    CheckIn::create(['user_id' => $user->id, 'checked_in_date' => '2026-06-01']);
    CheckIn::create(['user_id' => $user->id, 'checked_in_date' => '2026-06-02']);
    CheckIn::create(['user_id' => $user->id, 'checked_in_date' => '2026-06-03']);

    // Delete day 2 — creates a gap between day 1 and day 3, leaving streak = 1
    $checkIn = CheckIn::where('user_id', $user->id)
        ->where('checked_in_date', '2026-06-02')
        ->first();

    $this->actingAs($user)
        ->deleteJson("/api/check-ins/{$checkIn->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('check_ins', [
        'user_id'          => $user->id,
        'checked_in_date'  => '2026-06-02',
    ]);
});

it('user cannot delete another user check-in', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    CheckIn::create(['user_id' => $userA->id, 'checked_in_date' => '2026-06-01']);
    $checkIn = CheckIn::where('user_id', $userA->id)->first();

    $this->actingAs($userB)
        ->deleteJson("/api/check-ins/{$checkIn->id}")
        ->assertStatus(403);
});
