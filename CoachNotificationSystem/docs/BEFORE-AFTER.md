
--------------------------------------------------------------------------------------------------------------------------------

Before

--------------------------------------------------------------------------------------------------------------------------------

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true                                                                                             0.01s  

   FAIL  Tests\Feature\CoachNotificationSystemTest
  ⨯ it unauthenticated user cannot create check-in                                                                0.29s  
  ⨯ it authenticated user can create check-in                                                                     0.05s  
  ⨯ it duplicate check-in date is rejected                                                                        0.01s  
  ⨯ it streak is calculated correctly for consecutive dates                                                       0.01s  
  ⨯ it streak stops when there is a gap                                                                           0.01s  
  ⨯ it 7-day streak creates notification record                                                                   0.02s  
  ⨯ it 14-day streak creates notification record                                                                  0.01s  
  ⨯ it 21-day streak creates notification record                                                                  0.01s  
  ⨯ it 28-day streak creates notification record                                                                  0.01s  
  ⨯ it non-milestone streak does not create notification                                                          0.01s  
  ⨯ it same milestone is not created twice                                                                        0.01s  
  ⨯ it same milestone does not dispatch duplicate email job                                                       0.01s  
  ⨯ it email job is queued not sent synchronously                                                                 0.02s  
  ⨯ it queued job sends mailable when processed                                                                   0.01s  
  ⨯ it job has tries of 3                                                                                         0.01s  
  ⨯ it failed job does not retry forever                                                                          0.01s  
  ⨯ it notifications endpoint returns notifications newest first                                                  0.01s  
  ⨯ it notifications endpoint does not leak another user notifications                                            0.01s  
  ⨯ it deleting a check-in recalculates the streak                                                                0.01s  
  ⨯ it user cannot delete another user check-in                                                                   0.01s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                 0.06s  
  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it unauthenticated user cannot create check-in                    
  Expected response status code [401] but received 404.
Failed asserting that 404 is identical to 401.

  at tests\Feature\CoachNotificationSystemTest.php:18
     14▕ // --- Authentication ---
     15▕ 
     16▕ it('unauthenticated user cannot create check-in', function () {
     17▕     $this->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01'])
  ➜  18▕         ->assertStatus(401);
     19▕ });
     20▕ 
     21▕ // --- Check-in creation ---
     22▕

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it authenticated user can create check-in                         
  Expected response status code [201] but received 404.
Failed asserting that 404 is identical to 201.

  at tests\Feature\CoachNotificationSystemTest.php:28
     24▕     $user = User::factory()->create();
     25▕ 
     26▕     $this->actingAs($user)
     27▕         ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01'])
  ➜  28▕         ->assertStatus(201);
     29▕ 
     30▕     $this->assertDatabaseHas('check_ins', [
     31▕         'user_id' => $user->id,
     32▕         'checked_in_date' => '2026-06-01',

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it duplicate check-in date is rejected                            
  Expected response status code [422] but received 404.
Failed asserting that 404 is identical to 422.

  at tests\Feature\CoachNotificationSystemTest.php:43
     39▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01']);
     40▕ 
     41▕     $this->actingAs($user)
     42▕         ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-01'])
  ➜  43▕         ->assertStatus(422);
     44▕ });
     45▕ 
     46▕ // --- Streak calculation ---
     47▕

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it streak is calculated correctly for consecutive dates   Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:52
     48▕ it('streak is calculated correctly for consecutive dates', function () {
     49▕     $user = User::factory()->create();
     50▕ 
     51▕     foreach (consecutiveDates(6) as $date) {
  ➜  52▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
     53▕     }
     54▕ 
     55▕     $this->actingAs($user)
     56▕         ->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07'])

  1   tests\Feature\CoachNotificationSystemTest.php:52

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it streak stops when there is a gap                       Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:66
     62▕     $user = User::factory()->create();
     63▕ 
     64▕     // Days 1-3, skip day 4, seed day 5
     65▕     foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-05'] as $date) {
  ➜  66▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
     67▕     }
     68▕ 
     69▕     // Adding day 6: streak counts back 06→05 (gap before 05), so streak = 2
     70▕     $this->actingAs($user)

  1   tests\Feature\CoachNotificationSystemTest.php:66

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it 7-day streak creates notification record               Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:83
     79▕     Queue::fake();
     80▕     $user = User::factory()->create();
     81▕ 
     82▕     foreach (consecutiveDates(6) as $date) {
  ➜  83▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
     84▕     }
     85▕ 
     86▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);
     87▕

  1   tests\Feature\CoachNotificationSystemTest.php:83

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it 14-day streak creates notification record              Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:99
     95▕     Queue::fake();
     96▕     $user = User::factory()->create();
     97▕ 
     98▕     foreach (consecutiveDates(13) as $date) {
  ➜  99▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    100▕     }
    101▕ 
    102▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-14']);
    103▕

  1   tests\Feature\CoachNotificationSystemTest.php:99

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it 21-day streak creates notification record              Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:115
    111▕     Queue::fake();
    112▕     $user = User::factory()->create();
    113▕ 
    114▕     foreach (consecutiveDates(20) as $date) {
  ➜ 115▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    116▕     }
    117▕ 
    118▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-21']);
    119▕

  1   tests\Feature\CoachNotificationSystemTest.php:115

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it 28-day streak creates notification record              Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:131
    127▕     Queue::fake();
    128▕     $user = User::factory()->create();
    129▕ 
    130▕     foreach (consecutiveDates(27) as $date) {
  ➜ 131▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    132▕     }
    133▕ 
    134▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-28']);
    135▕

  1   tests\Feature\CoachNotificationSystemTest.php:131

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it non-milestone streak does not create notification      Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:147
    143▕     Queue::fake();
    144▕     $user = User::factory()->create();
    145▕ 
    146▕     foreach (consecutiveDates(4) as $date) {
  ➜ 147▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    148▕     }
    149▕ 
    150▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-05']);
    151▕

  1   tests\Feature\CoachNotificationSystemTest.php:147

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it same milestone is not created twice                    Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:163
    159▕     $user = User::factory()->create();
    160▕ 
    161▕     // First 7-day streak
    162▕     foreach (consecutiveDates(6) as $date) {
  ➜ 163▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    164▕     }
    165▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);
    166▕ 
    167▕     // Reset streak and rebuild another 7-day streak

  1   tests\Feature\CoachNotificationSystemTest.php:163

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it same milestone does not dispatch duplicate email job   Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:184
    180▕     $user = User::factory()->create();
    181▕ 
    182▕     // First 7-day streak
    183▕     foreach (consecutiveDates(6) as $date) {
  ➜ 184▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    185▕     }
    186▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);
    187▕ 
    188▕     // Reset streak and rebuild another 7-day streak

  1   tests\Feature\CoachNotificationSystemTest.php:184

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it email job is queued not sent synchronously             Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:207
    203▕     Queue::fake();
    204▕     $user = User::factory()->create();
    205▕ 
    206▕     foreach (consecutiveDates(6) as $date) {
  ➜ 207▕         CheckIn::create(['user_id' => $user->id, 'checked_in_date' => $date]);
    208▕     }
    209▕ 
    210▕     $this->actingAs($user)->postJson('/api/check-ins', ['checked_in_date' => '2026-06-07']);
    211▕

  1   tests\Feature\CoachNotificationSystemTest.php:207

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it queued job sends mailable when processed               Error   
  Class "App\Models\StreakNotification" not found

  at tests\Feature\CoachNotificationSystemTest.php:220
    216▕ it('queued job sends mailable when processed', function () {
    217▕     Mail::fake();
    218▕     $user = User::factory()->create();
    219▕ 
  ➜ 220▕     $notification = StreakNotification::create([
    221▕         'user_id' => $user->id,
    222▕         'streak_milestone' => 7,
    223▕         'notified_at' => null,
    224▕     ]);

  1   tests\Feature\CoachNotificationSystemTest.php:220

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it job has tries of 3                                     Error   
  Class "App\Jobs\SendStreakMilestoneNotification" not found

  at tests\Feature\CoachNotificationSystemTest.php:232
    228▕     Mail::assertSent(StreakMilestoneNotificationMail::class);
    229▕ });
    230▕ 
    231▕ it('job has tries of 3', function () {
  ➜ 232▕     $job = new SendStreakMilestoneNotification(1);
    233▕ 
    234▕     expect($job->tries)->toBe(3);
    235▕ });
    236▕

  1   tests\Feature\CoachNotificationSystemTest.php:232

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it failed job does not retry forever                      Error   
  Class "App\Jobs\SendStreakMilestoneNotification" not found

  at tests\Feature\CoachNotificationSystemTest.php:238
    234▕     expect($job->tries)->toBe(3);
    235▕ });
    236▕ 
    237▕ it('failed job does not retry forever', function () {
  ➜ 238▕     $job = new SendStreakMilestoneNotification(1);
    239▕ 
    240▕     expect($job->tries)->toBeLessThanOrEqual(3);
    241▕ });
    242▕

  1   tests\Feature\CoachNotificationSystemTest.php:238

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it notifications endpoint returns notifications newest…   Error   
  Class "App\Models\StreakNotification" not found

  at tests\Feature\CoachNotificationSystemTest.php:248
    244▕ 
    245▕ it('notifications endpoint returns notifications newest first', function () {
    246▕     $user = User::factory()->create();
    247▕ 
  ➜ 248▕     StreakNotification::create([
    249▕         'user_id' => $user->id,
    250▕         'streak_milestone' => 7,
    251▕         'notified_at' => now()->subDays(7),
    252▕     ]);

  1   tests\Feature\CoachNotificationSystemTest.php:248

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it notifications endpoint does not leak another user no…  Error   
  Class "App\Models\StreakNotification" not found

  at tests\Feature\CoachNotificationSystemTest.php:271
    267▕ it('notifications endpoint does not leak another user notifications', function () {
    268▕     $userA = User::factory()->create();
    269▕     $userB = User::factory()->create();
    270▕ 
  ➜ 271▕     StreakNotification::create([
    272▕         'user_id' => $userA->id,
    273▕         'streak_milestone' => 7,
    274▕         'notified_at' => now(),
    275▕     ]);

  1   tests\Feature\CoachNotificationSystemTest.php:271

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it deleting a check-in recalculates the streak            Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:288
    284▕ 
    285▕ it('deleting a check-in recalculates the streak', function () {
    286▕     $user = User::factory()->create();
    287▕ 
  ➜ 288▕     CheckIn::create(['user_id' => $user->id, 'checked_in_date' => '2026-06-01']);
    289▕     CheckIn::create(['user_id' => $user->id, 'checked_in_date' => '2026-06-02']);
    290▕     CheckIn::create(['user_id' => $user->id, 'checked_in_date' => '2026-06-03']);
    291▕ 
    292▕     // Delete day 2 — creates a gap between day 1 and day 3, leaving streak = 1

  1   tests\Feature\CoachNotificationSystemTest.php:288

  ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\CoachNotificationSystemTest > it user cannot delete another user check-in               Error   
  Class "App\Models\CheckIn" not found

  at tests\Feature\CoachNotificationSystemTest.php:307
    303▕ it('user cannot delete another user check-in', function () {
    304▕     $userA = User::factory()->create();
    305▕     $userB = User::factory()->create();
    306▕ 
  ➜ 307▕     CheckIn::create(['user_id' => $userA->id, 'checked_in_date' => '2026-06-01']);
    308▕     $checkIn = CheckIn::where('user_id', $userA->id)->first();
    309▕ 
    310▕     $this->actingAs($userB)
    311▕         ->deleteJson("/api/check-ins/{$checkIn->id}")

  1   tests\Feature\CoachNotificationSystemTest.php:307


  Tests:    20 failed, 2 passed (5 assertions)
  Duration: 0.83s

--------------------------------------------------------------------------------------------------------------------------------

After

--------------------------------------------------------------------------------------------------------------------------------

 PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\CoachNotificationSystemTest
  ✓ it unauthenticated user cannot create check-in                                                                             0.22s  
  ✓ it authenticated user can create check-in                                                                                  0.04s  
  ✓ it duplicate check-in date is rejected                                                                                     0.01s  
  ✓ it streak is calculated correctly for consecutive dates                                                                    0.09s  
  ✓ it streak stops when there is a gap                                                                                        0.01s  
  ✓ it 7-day streak creates notification record                                                                                0.02s  
  ✓ it 14-day streak creates notification record                                                                               0.02s  
  ✓ it 21-day streak creates notification record                                                                               0.02s  
  ✓ it 28-day streak creates notification record                                                                               0.02s  
  ✓ it non-milestone streak does not create notification                                                                       0.01s  
  ✓ it same milestone is not created twice                                                                                     0.02s  
  ✓ it same milestone does not dispatch duplicate email job                                                                    0.02s  
  ✓ it email job is queued not sent synchronously                                                                              0.01s  
  ✓ it queued job sends mailable when processed                                                                                0.01s  
  ✓ it job has tries of 3                                                                                                      0.01s  
  ✓ it failed job does not retry forever                                                                                       0.01s  
  ✓ it notifications endpoint returns notifications newest first                                                               0.01s  
  ✓ it notifications endpoint does not leak another user notifications                                                         0.01s  
  ✓ it deleting a check-in recalculates the streak                                                                             0.01s  
  ✓ it user cannot delete another user check-in                                                                                0.01s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                              0.03s  

  Tests:    22 passed (31 assertions)
  Duration: 0.76s