Step 1

    Project set up
                1. Start new Laravel project
                2. connect to Github repo
                                                                                                    10 mins

----------------------------------------------------------------------------------------------------------------

Step 2

    Documentation
                1. Write out the Understand.md
                2. Write out the Time Estimate.md
                3. Add the Ai Time estimate to the Estimate.md
                4. Write out the Aproach.md
                                                                                                        120 mins

----------------------------------------------------------------------------------------------------------------

Step 3

    Finish Project set up
                1. Install dependencies
                2. Install Sanctum
                3. Install Pest
                4. Confirm API/auth setup
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 4

    Feature tests first

                1. Create test file
                                    - Unauthenticated user cannot create check-in
                                    - Authenticated user can create check-in
                                    - Duplicate check-in date is rejected
                                    - Streak is calculated correctly for consecutive dates
                                    - Streak stops when there is a gap
                                    - 7-day streak creates notification record
                                    - 14-day streak creates notification record
                                    - 21-day streak creates notification record
                                    - 28-day streak creates notification record
                                    - Non-milestone streak does not create notification
                                    - Same milestone is not created twice
                                    - Same milestone does not dispatch duplicate email job
                                    - Email job is queued, not sent synchronously
                                    - Queued job sends mailable when processed
                                    - Job has $tries = 3
                                    - Failed job does not retry forever
                                    - Notifications endpoint returns user’s notifications newest first
                                    - Notifications endpoint does not leak another user’s notifications
                                    - Deleting a check-in recalculates the streak
                                    - User cannot delete another user’s check-in
                                                                                                    120 mins

----------------------------------------------------------------------------------------------------------------

Step 5 

    Database migrations

                1. Create check_ins migration.
                2. Add columns:
                                - id
                                - user_id
                                - checked_in_date
                                - timestamps
                3. Add foreign key to users
                4. Add unique constraint:
                                        unique(user_id, checked_in_date)
                5. Create streak_notifications migration.
                6. Add columns:
                                - id
                                - user_id
                                - streak_milestone
                                - notified_at
                                - timestamps
                7. Add foreign key to users
                8. Add unique constraint:
                                        unique(user_id, streak_milestone)
                9. Confirm jobs table exists.
                10. Confirm failed_jobs table exists.
                                                                                                    25 mins

----------------------------------------------------------------------------------------------------------------

Step 6

    Models
                1. Create CheckIn model.
                2. Add fillable fields:
                                        - user_id
                                        - checked_in_date
                3. Cast checked_in_date as date.
                4. Create StreakNotification model.
                5. Add fillable fields:
                                        - user_id
                                        - streak_milestone
                                        - notified_at
                6. Cast notified_at as datetime.
                7. Add relationships to User:
                                        - checkIns()
                                        - streakNotifications()
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 7

    Streak calculation logic

                1. Create a streak calculation service or private controller method.
                2. Fetch authenticated user’s check-ins ordered by date.
                3. Calculate consecutive days from the user’s latest check-in date.
                4. Ignore duplicate dates because database constraint should prevent them.
                5. Stop counting when a date gap is found.
                6. Return current streak count.
    
        Edge cases to test
                - No check-ins.
                - One check-in.
                - Consecutive 7-day streak.
                - Gap in check-ins.
                - Duplicate date attempt.
                - Deleting a check-in that breaks the streak.
                                                                                                    40 mins

----------------------------------------------------------------------------------------------------------------

Step 8

    Milestone detection

                1. Define milestone list:
                                        [7, 14, 21, 28]
                2. After creating a check-in, recalculate streak.
                3. Check whether streak is exactly one of the milestone values.
                4. Do not trigger for:
                                        8
                                        15
                                        22
                                        29
                                        35+
                5. Check whether the user already has a streak_notifications record for that milestone.
                6. If not, create one and dispatch the queued job.
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Idempotency / duplicate prevention

                1. Enforce duplicate prevention at database level:
                                                                    unique(user_id, streak_milestone)
                2. In application logic, check for existing notification before dispatching.
                3. Use firstOrCreate() or equivalent safe creation logic.
                4. Only dispatch the job if the notification was newly created.
                5. Make sure job retries do not create duplicate notification records.
                6. Make sure the same milestone cannot email twice even if the user loses and regains the streak.
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 10 

    Mailable

                1. Create mailable:
                                    php artisan make:mail StreakMilestoneNotification
                2. Include user and milestone data.
                3. Create a simple email view.
                4. Email should say something like:
                                    Congratulations! You reached a 7-day check-in streak.
                5. Confirm it writes to storage/logs/laravel.log with MAIL_MAILER=log.
                                                                                                    35 mins

----------------------------------------------------------------------------------------------------------------

Step 11

    Queued job

                1. Create job:
                                    php artisan make:job SendStreakMilestoneNotification
                2.  Make job implement queue behaviour.
                3. Add retry limit inside the job:
                                                public $tries = 3;
                4. Job should receive:
                                        user ID
                                        milestone
                                        notification ID, if needed
                5. Job should send the StreakMilestoneNotification mailable.
                6. Job should not create duplicate notification records.
                7. Decide whether notified_at is set before dispatch or after successful send.
                8. Prefer setting/confirming notified_at safely so notification history is recorded.
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 12

    Controllers

                1. Create CheckInController.
                2. Add store() method for POST /api/check-ins.
                3. Validate request:
                                    checked_in_date: required|date
                4. Create check-in for authenticated user.
                5. Recalculate streak.
                6. Trigger milestone job if applicable.
                7. Return JSON response immediately.
                8. Add destroy() method for DELETE /api/check-ins/{id}.
                9. Ensure user can only delete their own check-in.
                10. Delete check-in.
                11. Recalculate streak.
                12. Return updated streak.
                13. Create NotificationController.
                14. Add index() method for GET /api/notifications.
                15. Return authenticated user’s notifications newest first.
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 13

    Routes

                1. Add routes in routes/api.php.
                2. Protect routes with Sanctum auth:
                                        Route::middleware('auth:sanctum')->group(function () {
                                            Route::post('/check-ins', [CheckInController::class, 'store']);
                                            Route::delete('/check-ins/{checkIn}', [CheckInController::class, 'destroy']);
                                            Route::get('/notifications', [NotificationController::class, 'index']);
                                        });
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Run Tests
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 10

    Fix any failing tests
                                                                                                    25 mins

----------------------------------------------------------------------------------------------------------------

Step 11

    Manual test
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 12 

    BEFORE-AFTER.md
                                                                                                    30 mins
----------------------------------------------------------------------------------------------------------------

                                                                                                    11.5 hrs

---------------------------------------------------------------------------------------------------------------- 

My recommended estimate
Step	Task	Estimate
1	Project setup + GitHub repo	10 min
2	Documentation: UNDERSTANDING.md, ESTIMATE.md, AI estimate, APPROACH.md	120 min
3	Finish setup: dependencies, Sanctum, Pest, auth/API setup	25 min
4	Feature tests first	120 min
5	Database migrations	25 min
6	Models and relationships	25 min
7	Streak calculation logic	45 min
8	Milestone detection	30 min
9	Idempotency / duplicate prevention	35 min
10	Mailable	30 min
11	Queued job	45 min
12	Controllers	45 min
13	Routes	15 min
14	Run tests	20 min
15	Fix failing tests	45 min
16	Manual queue test	45 min
17	BEFORE-AFTER.md	30 min
Total

710 minutes
11 hours 50 minutes

I’d round this to:

Estimated total: 12 hours

Safer quoted estimate

Because queues, idempotency, and retry testing can be fiddly, I would quote:

Manual estimate: 11.5–12.5 hours
Recommended quoted estimate: 12 hours