Goal

Build a Laravel API that lets an authenticated client log daily check-ins. Each time a check-in is created or deleted, the app recalculates the user's check-in streak.

When the user's current streak reaches exactly 7, 14, 21, or 28 consecutive days, the app should create a milestone notification record and dispatch a queued job to send a congratulatory email.

The email must not be sent synchronously from the controller. The HTTP response should return quickly, and the email should be processed later by the queue worker.

Main implementation decisions
1. Use a queued job instead of sending mail in the controller

I will not call Mail::to($client)->send(...) directly inside the check-in controller.

That would send the email synchronously during the HTTP request. If the mailer is slow or fails, the check-in endpoint would also be slow or unreliable.

Instead, the controller will:

create the check-in
recalculate the streak
detect whether the streak is a milestone
create a streak_notifications record if needed
dispatch a queued job
return the API response immediately

The queued job will later send the mailable when the queue worker runs.

Local processing command:

php artisan queue:work --once
2. Use database queue driver

The project will use Laravel's database queue driver because the brief says no Redis is needed.

.env:

QUEUE_CONNECTION=database

Required queue tables:

php artisan queue:table
php artisan queue:failed-table
php artisan migrate

The jobs table stores pending jobs.

The failed_jobs table stores jobs that fail after all retry attempts.

3. Use log mailer locally

For local testing, mail will be written to the Laravel log file instead of being sent through a real mail provider.

.env:

MAIL_MAILER=log

After the queue worker processes the email job, the email should appear in:

storage/logs/laravel.log
4. Idempotency strategy

The same milestone email must never be sent twice to the same user.

I will enforce this in two layers:

database-level unique constraint
application-level checks before dispatching the job

The streak_notifications table will have this unique constraint:

unique(user_id, streak_milestone)

That means the database will prevent two notification records for the same user and milestone.

The milestone trigger logic will use firstOrCreate() or equivalent logic. The job will only be dispatched if the notification record was newly created.

Example idea:

$notification = StreakNotification::firstOrCreate(
    [
        'user_id' => $user->id,
        'streak_milestone' => $currentStreak,
    ],
    [
        'notified_at' => null,
    ]
);

if ($notification->wasRecentlyCreated) {
    SendStreakMilestoneNotification::dispatch($notification->id);
}

This prevents duplicate dispatches when:

the same user reaches 7 days twice
the same request is repeated
two requests happen close together
the user loses and later regains the same milestone streak

The job itself will load the notification record by ID. If the record no longer exists, the job can safely return.

5. Job retry strategy

The email job will have this property:

public $tries = 3;

This will live inside the queued job class:

class SendStreakMilestoneNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function handle(): void
    {
        //
    }
}

$tries = 3 tells Laravel to attempt the job at most 3 times total.

If the job fails 3 times, Laravel will stop retrying it and move it to the failed_jobs table.

Failed jobs can be inspected with:

php artisan queue:failed
6. Milestone trigger strategy

The milestone list will be hard-coded because the brief only asks for four specific milestone values.

private const MILESTONES = [7, 14, 21, 28];

The system will only trigger when the streak is exactly one of those values.

It will not trigger for:

8
15
22
29
35
any other number not listed in the milestone array
Data model
check_ins table

Columns:

Column	Type	Notes
id	bigint	primary key
user_id	foreign id	references users.id
checked_in_date	date	date of the client check-in
created_at	timestamp	Laravel default
updated_at	timestamp	Laravel default

Constraints:

foreign key user_id references users(id) cascade on delete
unique(user_id, checked_in_date)

Reason for unique constraint:

A user should only be able to log one check-in per calendar date. Duplicate dates should not inflate the streak.

streak_notifications table

Columns:

Column	Type	Notes
id	bigint	primary key
user_id	foreign id	references users.id
streak_milestone	integer	7, 14, 21, or 28
notified_at	timestamp nullable	when the milestone notification is considered sent/processed
created_at	timestamp	Laravel default
updated_at	timestamp	Laravel default

Constraints:

foreign key user_id references users(id) cascade on delete
unique(user_id, streak_milestone)

Reason for unique constraint:

This is the main protection against duplicate milestone notifications.

jobs table

Created by Laravel:

php artisan queue:table

Purpose:

Stores pending queued jobs when using the database queue driver.

failed_jobs table

Created by Laravel:

php artisan queue:failed-table

Purpose:

Stores failed jobs after they exhaust their allowed attempts.

Models
CheckIn

Fields:

protected $fillable = [
    'user_id',
    'checked_in_date',
];

protected $casts = [
    'checked_in_date' => 'date',
];

Relationships:

public function user()
{
    return $this->belongsTo(User::class);
}
StreakNotification

Fields:

protected $fillable = [
    'user_id',
    'streak_milestone',
    'notified_at',
];

protected $casts = [
    'notified_at' => 'datetime',
];

Relationships:

public function user()
{
    return $this->belongsTo(User::class);
}
User

Add relationships:

public function checkIns()
{
    return $this->hasMany(CheckIn::class);
}

public function streakNotifications()
{
    return $this->hasMany(StreakNotification::class);
}
Endpoints and routes

Routes will be placed in routes/api.php.

All routes should be protected by Sanctum authentication.

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/check-ins', [CheckInController::class, 'store']);
    Route::delete('/check-ins/{checkIn}', [CheckInController::class, 'destroy']);
    Route::get('/notifications', [NotificationController::class, 'index']);
});
POST /api/check-ins

Purpose:

Create a check-in for the authenticated user.

Request body:

{
  "checked_in_date": "2026-06-29"
}

Validation:

'checked_in_date' => ['required', 'date']

Behaviour:

Get the authenticated user.
Validate the date.
Create the check-in.
Recalculate the user's current streak.
If the streak is exactly 7, 14, 21, or 28:
create a streak_notifications record if one does not already exist
dispatch SendStreakMilestoneNotification only if the record was newly created
Return JSON immediately.

Possible response:

{
  "data": {
    "id": 1,
    "checked_in_date": "2026-06-29",
    "current_streak": 7
  }
}

Possible status codes:

Status	Meaning
201	check-in created
401	unauthenticated
422	validation error or duplicate date
DELETE /api/check-ins/{id}

Purpose:

Delete one of the authenticated user's check-ins and recalculate the streak.

Behaviour:

Find the check-in.
Ensure it belongs to the authenticated user.
Delete the check-in.
Recalculate the user's current streak.
Return success response.

Possible response:

{
  "message": "Check-in deleted.",
  "current_streak": 6
}

Possible status codes:

Status	Meaning
200	deleted successfully
401	unauthenticated
403	user tried to delete another user's check-in
404	check-in not found

Important decision:

Deleting a check-in should not delete old streak_notifications records.

Reason:

The notification table is a history of milestones already sent. If those records were deleted, the same user could receive the same milestone email again later, which would break the duplicate-prevention rule.

GET /api/notifications

Purpose:

Return the authenticated user's milestone notification history.

Behaviour:

Get authenticated user.
Query only that user's streak_notifications.
Order by newest first.
Return JSON.

Query:

StreakNotification::where('user_id', auth()->id())
    ->latest('notified_at')
    ->latest('created_at')
    ->get();

Possible response:

{
  "data": [
    {
      "id": 3,
      "streak_milestone": 21,
      "notified_at": "2026-06-29T10:00:00.000000Z"
    },
    {
      "id": 2,
      "streak_milestone": 14,
      "notified_at": "2026-06-22T10:00:00.000000Z"
    },
    {
      "id": 1,
      "streak_milestone": 7,
      "notified_at": "2026-06-15T10:00:00.000000Z"
    }
  ]
}

Possible status codes:

Status	Meaning
200	notifications returned
401	unauthenticated
Streak calculation

I will create a small service class for streak calculation instead of burying all logic inside the controller.

Possible class:

app/Services/StreakCalculator.php

Possible method:

public function currentStreakFor(User $user): int

Algorithm:

Get all check-in dates for the user.
Sort dates from newest to oldest.
Start from the most recent check-in date.
Count backwards one calendar day at a time.
Stop when a date is missing.
Return the count.

Example:

2026-06-29
2026-06-28
2026-06-27
2026-06-26
2026-06-25
2026-06-24
2026-06-23

Result:

7

Example with a gap:

2026-06-29
2026-06-28
2026-06-26
2026-06-25

Result:

2

Because 2026-06-27 is missing.

Important decision:

The streak will be calculated from the user's latest check-in date, not necessarily from today's real date.

Reason:

The brief says the streak is recalculated when check-ins are logged. Using the latest logged check-in makes the behaviour easier to test and avoids tests depending on the current date.

Milestone notification flow
On check-in creation

Flow:

POST /api/check-ins
        |
        v
create check-in
        |
        v
calculate current streak
        |
        v
is streak in [7, 14, 21, 28]?
        |
        | no
        v
return response
        |
        | yes
        v
create streak_notifications record if missing
        |
        v
was record newly created?
        |
        | no
        v
return response
        |
        | yes
        v
dispatch queued email job
        |
        v
return response

The controller returns after dispatching the job. It does not wait for the email to be sent.

Queued job design

Job name:

SendStreakMilestoneNotification

Responsibilities:

Load the StreakNotification record.
Load the related user.
Send StreakMilestoneNotification mailable.
Update notified_at when appropriate.
Retry at most 3 times if it fails.

The job should receive a notification ID instead of the full user model.

Reason:

Passing IDs into queued jobs is safer and avoids stale serialized models.

Possible constructor:

public function __construct(
    public int $streakNotificationId
) {
}

Possible handle flow:

public function handle(): void
{
    $notification = StreakNotification::with('user')->find($this->streakNotificationId);

    if (! $notification) {
        return;
    }

    Mail::to($notification->user)->send(
        new StreakMilestoneNotification($notification->streak_milestone)
    );

    $notification->update([
        'notified_at' => now(),
    ]);
}

The controller still does not send mail directly. The mail send happens inside the queued job.

Mailable design

Mailable name:

StreakMilestoneNotification

Create with:

php artisan make:mail StreakMilestoneNotification

The mailable should accept the milestone number.

Example content:

Congratulations! You reached a 7-day check-in streak.

Possible subject:

Congratulations on your 7-day streak!

The email view can be simple because the brief only requires a congratulatory email and local log testing.

Libraries and packages
Laravel

Used as the main framework.

Provides:

routing
controllers
validation
migrations
Eloquent models
queues
mail
tests
Laravel Sanctum

Used for API authentication.

Reason:

The setup instructions specifically require Sanctum.

SQLite

Used for local development and tests.

Reason:

The setup instructions say to configure .env for SQLite.

Laravel database queue

Used for queued jobs.

Reason:

The brief says to use the database queue driver and no Redis is needed.

Laravel Mail

Used for the mailable and log mailer.

Reason:

The brief asks for a Laravel Mailable class and local MAIL_MAILER=log testing.

Pest or PHPUnit

Tests can be written with Pest if installed, or PHPUnit if using Laravel defaults.

I will use Pest if the project setup includes it. Otherwise, Laravel's default PHPUnit feature tests are acceptable.

Testing approach

I will write feature tests first before implementing the code.

Important tests:

Authentication tests
unauthenticated user cannot create a check-in
unauthenticated user cannot delete a check-in
unauthenticated user cannot list notifications
Check-in tests
authenticated user can create a check-in
duplicate check-in date is rejected
user cannot delete another user's check-in
deleting a check-in recalculates the streak
Streak calculation tests
one check-in gives streak of 1
consecutive dates calculate correctly
gap in dates stops the streak
streak is calculated from latest check-in date
Milestone tests
7-day streak creates a notification
14-day streak creates a notification
21-day streak creates a notification
28-day streak creates a notification
8-day streak does not create a new notification
35-day streak does not create a notification
non-milestone streak does not create a notification
Queue tests
milestone check-in dispatches SendStreakMilestoneNotification
email is not sent synchronously in the controller
queued job sends the mailable when processed
job has $tries = 3
Idempotency tests
same milestone notification is not created twice
same milestone job is not dispatched twice
same milestone email cannot be triggered twice after the user loses and regains the streak
notifications are scoped to the authenticated user
Notification endpoint tests
notifications are returned newest first
one user's notifications do not leak to another user
Manual testing approach

After automated tests pass, I will prove the queue behaviour manually.

Steps:

Set queue and mail configuration:
QUEUE_CONNECTION=database
MAIL_MAILER=log
Create a user and authenticate.
Log 7 consecutive check-ins using POST /api/check-ins.
Confirm a notification record exists in streak_notifications.
Confirm a pending job exists in the jobs table.
Run:
php artisan queue:work --once
Confirm the job leaves the jobs table.
Confirm the email appears in:
storage/logs/laravel.log
Paste the before/after output into BEFORE-AFTER.md.
Edge cases
Duplicate check-in date

A user should not be able to check in twice for the same date.

Handled by:

unique(user_id, checked_in_date)

Expected result:

422 validation error
Gap in check-in dates

If the user has a missing date, the streak should stop at the gap.

Example:

2026-06-29
2026-06-28
2026-06-26

Current streak:

2
Streak reaches 8 after a 7-day milestone

The 7-day email should be sent at exactly 7.

The 8th day should not send another email.

Handled by:

in_array($currentStreak, [7, 14, 21, 28])
User reaches 7 days twice

The user should not receive the 7-day email again.

Handled by:

unique(user_id, streak_milestone)
Two users reach the same milestone

Both users should be able to receive their own 7-day notification.

The unique constraint is per user and milestone, not just milestone.

Correct:

unique(user_id, streak_milestone)

Incorrect:

unique(streak_milestone)
User deletes a check-in after receiving a notification

Deleting the check-in should recalculate the streak.

It should not delete the old notification record.

Reason:

The notification record proves that milestone was already sent. Deleting it could allow duplicate milestone emails later.

User tries to delete someone else's check-in

The API should reject this.

Handled by:

route model lookup plus ownership check
return 403 if the check-in belongs to another user
Job fails

The job can retry up to 3 times because the job class contains:

public $tries = 3;

After 3 failed attempts, Laravel moves the job to the failed_jobs table.

Inspect with:

php artisan queue:failed
Job record points to deleted notification

If the notification record is deleted before the job runs, the job should safely return.

This prevents errors from missing data.

User record deleted before job runs

If the user no longer exists before the job runs, the job should safely return or fail cleanly.

Because streak_notifications.user_id uses cascade delete, deleting the user should delete their notification records too.

Ambiguities and decisions
Are there coach roles?

The task name says "Coach Notification System", but the endpoints are all based on the authenticated user's check-ins and notifications.

Decision:

I will treat the authenticated user as the client receiving the streak notification. I will not add coach-specific role logic unless the project workflow requires it.

Should the streak be calculated from today or latest check-in?

The brief does not explicitly say.

Decision:

Calculate from the latest check-in date.

Reason:

This makes the API deterministic and easier to test.

Should duplicate check-ins be allowed?

The brief does not explicitly say.

Decision:

No. A user can only check in once per calendar date.

Reason:

Streaks are day-based, so duplicate dates should not increase the streak.

Should notifications be deleted when check-ins are deleted?

The brief does not say.

Decision:

No.

Reason:

Notification records are used to prevent duplicate milestone emails. Deleting them would make duplicate emails possible.

Should milestones after 28 be supported?

The brief only lists 7, 14, 21, and 28.

Decision:

Only those four milestones will trigger notifications.

Should I use Mail::queue() or a custom queued job?

The brief asks for:

emails sent via queued job
$tries = 3
failed jobs after retries

Decision:

Use a custom job class.

Reason:

A custom job makes the retry limit and idempotency behaviour explicit and testable.

Files/classes I expect to create
Migrations
database/migrations/xxxx_xx_xx_create_check_ins_table.php
database/migrations/xxxx_xx_xx_create_streak_notifications_table.php

Queue migrations are created by:

php artisan queue:table
php artisan queue:failed-table
Models
app/Models/CheckIn.php
app/Models/StreakNotification.php

Update:

app/Models/User.php
Controllers
app/Http/Controllers/CheckInController.php
app/Http/Controllers/NotificationController.php
Services
app/Services/StreakCalculator.php

Optional, but preferred to keep controller logic clean.

Jobs
app/Jobs/SendStreakMilestoneNotification.php
Mail
app/Mail/StreakMilestoneNotification.php
resources/views/emails/streak-milestone-notification.blade.php
Routes
routes/api.php
Tests

Possible test file:

tests/Feature/CoachNotificationSystemTest.php

or with Pest:

tests/Feature/CoachNotificationSystemTest.php
Final implementation order
Finish Laravel/Sanctum/SQLite/queue setup.
Write feature tests first.
Create migrations.
Create models and relationships.
Build StreakCalculator.
Build milestone detection logic.
Build mailable.
Build queued job with $tries = 3.
Build controllers.
Register routes.
Run tests and fix failures.
Manually process a queued job with php artisan queue:work --once.
Confirm email appears in storage/logs/laravel.log.
Fill in BEFORE-AFTER.md with pasted command output.
Definition of done

This task is done when:

POST /api/check-ins creates a check-in and returns without sending email synchronously.
A queued job is dispatched when the streak reaches exactly 7, 14, 21, or 28.
The same milestone notification cannot be created twice for the same user.
The same milestone email cannot be triggered twice for the same user.
The job has $tries = 3.
Failed jobs move to failed_jobs after exhausting retries.
GET /api/notifications returns the authenticated user's notifications newest first.
DELETE /api/check-ins/{id} deletes a check-in and recalculates the streak.
Running php artisan queue:work --once processes a pending email job.
The email appears in storage/logs/laravel.log.
Tests pass.
BEFORE-AFTER.md contains proof of the queue job processing.