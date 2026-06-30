# Production-Ready Audit — CoachNotificationSystem

Assessed against: `docs/rubric.md`
Date: 2026-06-30
Result: **2 / 10 criteria pass**

---

## 1. Type Safety — FAIL

No `declare(strict_types=1)` appears in any file under `app/`. Methods in controllers, services, and jobs do declare typed parameters and return types, but strict mode is absent throughout. Without it, PHP will silently coerce mismatched scalar types at every function boundary.

**Required:** Add `declare(strict_types=1);` as the first statement after `<?php` in every file under `app/`.

---

## 2. Error Handling — FAIL

No named exception classes exist under `app/Exceptions/`. There are no business-logic exceptions — not for duplicate check-in attempts (currently handled by a `ValidationException` via Laravel's `Rule::unique`, which is acceptable), but also not for internal invariant violations such as a missing `StreakNotification` record inside the queued job. The job silently returns when the notification is not found (`if (! $notification) return;`) rather than throwing a typed exception.

**Required:** Create named exception classes for internal invariant violations (e.g. `StreakNotificationNotFoundException`). Never silently swallow an invariant failure — throw a `LogicException` subclass so the queue worker marks the job as failed and the failure is visible.

---

## 3. Observability — FAIL

No `Log::info()`, `Log::warning()`, or `Log::error()` calls appear anywhere in `app/`. The two state-changing operations — check-in creation and streak milestone notification dispatch — emit nothing to the log. An incident investigation into "why didn't user #42 receive a notification for their 7-day streak?" requires a full database query; there is no log trail.

**Required:** Emit at least one structured log entry per state transition: check-in created (user ID, date, streak), milestone detected (user ID, milestone), job dispatched (notification ID), job completed (notification ID, email address).

---

## 4. Configuration — FAIL

`MILESTONES = [7, 14, 21, 28]` is hardcoded as a private constant in `app/Services/MilestoneDetector.php`. Changing the milestone schedule requires editing tested business logic and redeploying. No `config/*.php` file exists for application-specific tunables.

**Required:** Move milestone values to `config/notifications.php` and reference them via `config('notifications.streak_milestones')`.

---

## 5. Validation — PASS

`CheckInController::store()` uses a single `Rule::unique('check_ins')->where('user_id', ...)` clause. This issues one query per request regardless of payload size. No N+1 pattern exists in the validation layer.

---

## 6. Data Integrity — FAIL

No `DB::transaction()` wraps any multi-table write, and no `lockForUpdate()` is used before any read-then-write. The milestone detection flow reads (`firstOrCreate`) and conditionally writes (`dispatch`) without a transaction. A concurrent request for the same user at the same milestone can pass the `wasRecentlyCreated` check on both paths simultaneously, dispatching duplicate jobs.

**Required:** Wrap `MilestoneDetector::checkAndNotify()` in a `DB::transaction()` and acquire a `lockForUpdate()` before the `firstOrCreate` call to serialize concurrent requests.

---

## 7. Security — FAIL

Two issues:

- `APP_DEBUG=true` in `.env.example`. A developer who copies this verbatim and deploys will serve full stack traces in HTTP 500 responses, leaking class names, file paths, and query structure to the client. The rubric requires that no endpoint returns a 500 with a stack trace.
- `CheckInController::destroy()` performs authorization with a manual `if ($checkIn->user_id !== $request->user()->id)` guard rather than a formal Laravel `Policy`. This is functionally correct for the current scope but does not satisfy the rubric's requirement that "authorization policies are applied for resource mutations."

All non-public routes are correctly gated behind `auth:sanctum`. No admin endpoints exist in this project, so the `EnsureUserIsAdmin` criterion is not applicable.

**Required:** Set `APP_DEBUG=false` in `.env.example`. Extract the ownership check into a `CheckInPolicy` registered via `Gate::policy()`.

---

## 8. API Consistency — FAIL

Two issues:

- `CheckInController::destroy()` returns HTTP `200` with a JSON body. The rubric requires `204 No Content` for deletes with no response body.
- All three controllers return raw associative arrays (`['data' => [...]]`). The rubric requires consistent use of API Resource classes (`JsonResource` / `ResourceCollection`). Mixing raw arrays with resource objects is explicitly flagged as a failure mode.

**Required:** Change `destroy()` to return `response()->json(null, 204)`. Create `CheckInResource` and `StreakNotificationResource` and use them in all responses.

---

## 9. Tests Pass — PASS

`composer test` exits with code 0. All 22 tests pass (20 feature + 2 default Pest examples). No tests are marked `->skip()` or `todo()`. Coverage includes happy paths and primary failure paths for both service classes (`StreakCalculator`, `MilestoneDetector`) and all three controller actions.

---

## 10. No Hardcoded Environment Values — FAIL

`.env.example` ships with:

```
APP_DEBUG=true
MAIL_MAILER=log
```

`APP_DEBUG=true` is explicitly called out in the rubric as a production hazard. `MAIL_MAILER=log` is a development default and acceptable, but no key is annotated with `# REQUIRED`. A developer reading the file cannot tell which keys must be changed before deployment (e.g. `APP_KEY`, `MAIL_FROM_ADDRESS`, `DB_CONNECTION`).

**Required:** Set `APP_DEBUG=false`. Annotate mandatory keys with `# REQUIRED`.

---

## Summary

| # | Criterion | Result |
|---|-----------|--------|
| 1 | Type Safety | FAIL |
| 2 | Error Handling | FAIL |
| 3 | Observability | FAIL |
| 4 | Configuration | FAIL |
| 5 | Validation | PASS |
| 6 | Data Integrity | FAIL |
| 7 | Security | FAIL |
| 8 | API Consistency | FAIL |
| 9 | Tests Pass | PASS |
| 10 | No Hardcoded Env Values | FAIL |

**Score: 2 / 10**

The test suite is green and validation is query-efficient. Every other criterion fails. The highest-priority fixes before a production deployment are criteria 7 (APP_DEBUG leaks stack traces) and 6 (race condition on milestone dispatch).
