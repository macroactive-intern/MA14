What is the task asking me to build?

This task is asking me to build a Laravel API that logs client check-ins and sends congratulatory milestone emails when a client's current check-in streak reaches specific milestones.

The milestone streaks are:

                            7 consecutive days
                            14 consecutive days
                            21 consecutive days
                            28 consecutive days

The email must not be sent directly inside the HTTP request. It must be handled through Laravel's queue system using the database queue driver.

The system also needs to record which milestone notifications have already been sent so the same user never receives the same milestone email twice.

The main feature is not just sending an email. The important parts are:

                                                                    - recalculating the streak after a check-in is created or deleted
                                                                    - detecting exact milestone streaks
                                                                    - dispatching email work to a queue instead of blocking the request
                                                                    - preventing duplicate milestone emails
                                                                    - limiting failed job retries to 3 attempts
                                                                    - storing notification history for the authenticated user

---------------------------------------------------------------------------------------------------------------------------------------------

Inputs and outputs

POST /api/check-ins

    This endpoint lets an authenticated client log a check-in.

        Expected input:

                        {
                        "checked_in_date": "2026-06-29"
                        }

Expected behaviour:
                    - Validate the check-in date.
                    - Create the check-in for the authenticated user.
                    - Recalculate the user's current streak.
                    - If the streak is exactly 7, 14, 21, or 28 days, trigger a queued milestone notification.
                    - Return the response immediately without waiting for the email to send.

-------------------------------------------------------

DELETE /api/check-ins/{id}

This endpoint removes one of the authenticated user's check-ins.

Expected behaviour:

                    - Only allow the owner of the check-in to delete it.
                    - Delete the check-in.
                    - Recalculate the user's streak after deletion.
                    - Return a success response with the updated streak.

-------------------------------------------------------

GET /api/notifications

This endpoint lists the authenticated user's streak milestone notification history.

Expected behaviour:

                    - Return only the authenticated user's notifications.
                    - Order notifications in reverse chronological order.
                    - Include the milestone and notified time.

-------------------------------------------------------

Tables

check_ins

Required columns:
                - id
                - user_id
                - checked_in_date
                - created_at
                - updated_at

I will also add a unique constraint on user_id and checked_in_date so a client cannot log the same date twice.

-----------------------------------------

streak_notifications

Required columns:

                - id
                - user_id
                - streak_milestone
                - notified_at
                - created_at
                - updated_at

I will add a unique constraint on user_id and streak_milestone.

That unique constraint is important because it prevents duplicate milestone records at the database level, even if two requests or jobs happen close together.

-----------------------------------------

jobs

Created by:

php artisan queue:table

This stores pending queued jobs when using:

QUEUE_CONNECTION=database

-----------------------------------------

failed_jobs

Created by:

php artisan queue:failed-table

This stores jobs that fail after using all allowed retry attempts.

---------------------------------------------------------------------------------------------------------------------------------------------

Response to the previous dev's note

The previous dev said:

                        "Sending the email is straightforward — just call Mail::to($client)->send(new StreakCongratulations()) inside the check-in controller after you calculate the streak."

I do not agree with doing it this way for this task.

Calling Mail::send() inside the controller sends the email synchronously during the HTTP request. That means the user has to wait for the email-sending process before the API response finishes. If the mail server is slow or fails, the check-in request can become slow or unreliable.

The better approach is:

                        - The controller logs the check-in.
                        - The controller recalculates the streak.
                        - If the streak is a milestone, the controller dispatches a queued job.
                        - The HTTP response returns immediately.
                        - The queue worker later processes the job and sends the email.

---------------------------------------------------------------------------------------------------------------------------------------------

Difference between Mail::send() and queued mail

Mail::send() sends the email immediately in the current request.

That means:

            - The HTTP response waits for the email to finish
            - Mail failures can affect the request
            - It does not use the queue by default

-----------------------------------------

Mail::queue() pushes the email onto the queue instead of sending it immediately.

That means:

            - The HTTP response can return faster
            - The email is processed later by a queue worker
            - Failures are handled by the queue system

---------------------------------------------------------------------------------------------------------------------------------------------

How idempotency is enforced

The system must never send the same milestone email twice to the same user.

I will enforce this with a database-level unique constraint:
                                                            unique(user_id, streak_milestone)

This means the database itself will reject duplicate milestone records for the same user and milestone.

I will also check whether the milestone notification already exists before dispatching or sending the job.

The safest approach for this task is:

                - When a user reaches a milestone, attempt to create one streak_notifications record for that user and milestone
                - Only dispatch the email job if that record was newly created
                - If the record already exists, do not dispatch another job

This prevents duplicate emails when:

                                    - The user reaches 7 days more than once
                                    - The same check-in request is repeated
                                    - The job is retried
                                    - Two processes try to create the same notification at nearly the same time

---------------------------------------------------------------------------------------------------------------------------------------------

What $tries = 3 does and where it lives

The queued job class will include:

public $tries = 3;

This belongs inside the job class, for example:

class SendStreakMilestoneNotification implements ShouldQueue
{
    public $tries = 3;

    public function handle(): void
    {
        //
    }
}

$tries = 3 tells Laravel that the job can be attempted up to 3 times total.

If the job fails on the first attempt, Laravel may retry it.

If it fails again, Laravel may retry it again.

If it still fails after the third attempt, Laravel stops retrying it and moves it to the failed_jobs table.

---------------------------------------------------------------------------------------------------------------------------------------------

What happens when a job fails all 3 retries?

If a job fails all 3 attempts, Laravel records it in the failed_jobs table.

I can inspect failed jobs with:

php artisan queue:failed

During local testing, the failed job records can also be viewed directly in the SQLite database.

The job should not retry forever because $tries = 3 limits the number of attempts.

---------------------------------------------------------------------------------------------------------------------------------------------

How the queue is processed locally

Because the task uses the database queue driver, jobs are stored in the jobs table until a queue worker processes them.

To process one queued job locally, run:

                                        php artisan queue:work --once

That should process one pending job.

For this task, after logging a milestone check-in, I should be able to run:

                                                                            php artisan queue:work --once

Then the email should be written to:

                                    storage/logs/laravel.log

because local mail should use:

                                MAIL_MAILER=log

---------------------------------------------------------------------------------------------------------------------------------------------

Should users have roles?

I will treat all authenticated users as clients for this task unless the starter workflow requires roles. I will not add coach-only permissions unless needed.

-------------------------------------------------------

Should duplicate check-ins for the same date be allowed?

A user should only have one check-in per calendar date.

Reason: Streaks are based on consecutive days, so duplicate dates should not increase the streak.

I will enforce this with:

unique(user_id, checked_in_date)

-------------------------------------------------------

What date does the streak count from?

The current streak should be calculated backwards from the user's most recent check-in date.

Reason: This makes the logic easier to test and avoids tests breaking because the real current date changes.

-------------------------------------------------------

Should deleting a check-in remove old notification records?

Deleting a check-in should not delete existing streak_notifications records.

Reason: The notification history records that a milestone email was already sent. If I deleted the notification record, the user could later receive the same milestone email again, which would violate the "only send once per milestone per user" requirement.

-------------------------------------------------------

Should a user be able to receive the 7-day email again if their streak resets and later reaches 7 again?

Each milestone can only ever be emailed once per user.

So a user can receive:

one 7-day email
one 14-day email
one 21-day email
one 28-day email

They cannot receive another 7-day email later.

-------------------------------------------------------

Should milestones above 28 send emails?

No emails should be sent for 35, 42, 49, or other later streaks.

The milestone list will be hard-coded as:

[7, 14, 21, 28]

-------------------------------------------------------

What should the email content say?

The email can be simple and include the milestone number.

Example:

Congratulations! You reached a 7-day check-in streak.

-------------------------------------------------------

Should notified_at be nullable?

I will make notified_at nullable or set it when the notification is claimed/sent, depending on the final implementation.

The important part is that the notification record exists and the unique constraint prevents duplicates.

-------------------------------------------------------

Should the app use Mail::queue() directly or a custom queued job?

I will create a dedicated job class, probably named SendStreakMilestoneNotification.

The job will be responsible for sending the StreakMilestoneNotification mailable.

-------------------------------------------------------