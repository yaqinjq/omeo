# OMEO Sport — Implementation Notes (MVP Slice 1 + Slice 2)

Status: **implemented, not yet deployed/pushed/committed**. Isolated module
added to the existing OMEO (HRD/Finance) application. Slice 1 (event
lifecycle/registration/cancellation/attendance) was built per the original
MVP spec dated 20 Oktober 2026 and reviewed across 5 rounds (see sections
below, mostly unchanged since). **Slice 2** was built per **Spesifikasi MVP
OMEO Sport v1.1, juga tanggal 20 Oktober 2026** (reporting/export, import
historis, Non-MKO, draft reward badminton, backdate Admin Finance, share
link) — see "## Slice 2 (v1.1)" near the end of this file for its own
compliance mapping against that document's Section 12/13 numbering. Additive
entries were made to `config/rbac.php` and
`resources/views/partials/hr_suite_menu.blade.php`. One unrelated,
pre-existing migration bug was also found and fixed during audit — see
"Pre-existing issues found" below; **that fix is a logically separate change
and should be committed in its own commit, not bundled with the Sport
feature files**, when this is eventually committed.

**MVP is NOT "selesai".** Several Section 13 Open Decisions in v1.1 remain
genuinely undecided by the owner (reward threshold ambiguity, backdate max
days default, quarter kalender vs fiskal, cancellation SLA/timeout, event
publish approval, waitlist). Slice 2 code reflects this honestly: the
features that depend on those decisions are either disabled-by-default
(backdate, reward activation) or implemented with an explicitly-documented,
reversible default (calendar quarter) — never a silent guess.

## What's implemented in this slice

The core lifecycle from the spec's Section F/Section J acceptance criteria:

- **Event CRUD** by a Koordinator (`app/Http/Controllers/Sport/EventController.php`):
  draft → publish → cancel. Coach-led events require positive capacity before
  publish; events without a coach have no participant cap.
- **Registration** by employees (`SportController::register`): rejected once
  the event has started; duplicate registration rejected (unique DB constraint
  + explicit check, so double-click/retry is idempotent). Capacity check +
  insert are wrapped in `DB::transaction()` with `lockForUpdate()` on the
  event row (see "Concurrency / capacity locking" below for what this does
  and does not prove).
- **Cancellation request/decision**: see "Cancellation decision rule" below
  — this is an intentional fallback policy, not a bug.
- **Attendance confirmation**: open only from the event's `end_at` until
  `end_at + 60 minutes` (server clock — see "Timezone" section below for an
  important caveat on what "server clock" actually means here). One
  attendance record per employee+event (DB unique constraint), confirming
  twice is a no-op, not an error that loses data.
- **Authorization**: checked server-side in every controller action
  (`authorizeCoordinator()`, `authorizeOwnRegistration()`), not just hidden in
  the UI. New `koordinator` DB role + 3 new permissions
  (`sport.events.manage`, `sport.cancellations.decide`, `sport.roster.view`).
- Sport event attendance **never writes to any HRD attendance/payroll table**
  (verified by reading `SportController`/`Attendance` — no reference to the
  `attendances` or `employee_salary_histories` tables anywhere in Sport code).
  The accompanying test only proves this weakly — see "Test limitations" below.

## Cancellation decision rule (confirmed policy — not a scope violation)

A cancellation request can be decided by **either**:
1. The **Koordinator assigned** to that event (primary), **or**
2. **Admin/Finance**, as a **fallback**, but **only while the request is
   still `pending`** (i.e. the assigned Koordinator hasn't acted on it yet).

Either decision is sufficient — there is no required sequence (Koordinator
first, then Admin/Finance, or vice versa). Once a request is decided
(`approved` or `rejected`), **no further decision is possible** — a second
attempt by anyone, including the original decider, is rejected with no
change to the record (no silent overwrite, no duplicate row).

This matches the code exactly as implemented:
- `routes/sport.php:27` — `role:koordinator,admin,finance` on the whole
  Koordinator route group (including the two decision routes).
- `EventController.php:183-192` (`authorizeCoordinator()` /
  `isSportAdmin()`) — allows the assigned Koordinator OR a user with
  `role` in `['admin', 'finance']`, for **any** event, not just one they
  coordinate (this is intentional for the fallback — Admin/Finance couldn't
  act as a fallback if they were restricted to events they coordinate).
- `EventController.php:149` / `:168` — `if ($cancellationRequest->status !==
  CancellationRequest::STATUS_PENDING) { return back()->with('error', ...); }`
  — this is what makes a second decision a no-op regardless of who attempts it.

**No timeout/SLA exists for "the Koordinator hasn't responded yet"** — per
your instruction, this was not invented. `pending` status is the only and
sufficient condition that currently gates the Admin/Finance fallback. A
time-based SLA (e.g. "fallback only kicks in after 24h of no response") can
be added later as a separate, explicitly-decided policy; it is **not**
implemented now, and nothing currently blocks Admin/Finance from deciding a
`pending` request immediately, even one second after it was raised.

Covered by 4 tests in `SportEventLifecycleTest.php` (see "How to test" below):
assigned Koordinator decides; Admin/Finance decides a still-pending request
as fallback; a second decision on an already-decided request is a no-op
(status/actor/timestamp unchanged, no duplicate row); a regular employee or
an unassigned Koordinator cannot decide at all (403).

## Concurrency / capacity locking

`SportController::register()` wraps the "already registered?" +
"is it full?" + `Registration::create()` sequence in `DB::transaction()`
with `Event::where('id', $event->id)->lockForUpdate()->first()`. On MySQL/
InnoDB (what production OMEO runs), this serializes concurrent requests for
the same event — the second request's `lockForUpdate()` read blocks until
the first transaction commits or rolls back, so it will see the
already-incremented registration count before deciding whether the event is
full.

**SQLite test limitation (unchanged from round 2)**: the PHPUnit suite runs
against SQLite `:memory:` on a single connection/process, so it cannot
simulate two genuinely simultaneous requests. `test_coach_led_event_registration_is_rejected_once_capacity_is_reached`
only proves the sequential logic (fill to capacity, next attempt rejected).

**Round 3 — attempted real-MySQL verification, did not complete (honest
report, not a claim of success)**: per your request to verify the locking
against OMEO's actual DB engine in an isolated test/staging environment, I
started the local XAMPP MySQL instance (the same one documented as
flaky in this project's working notes) pointed at a brand-new, throwaway
database (`sport_concurrency_test` — never `omeo_hr`, nothing from this repo
was migrated into it). **The MySQL/MariaDB server crashed during its own
startup table-discovery phase** (`mysqld.exe` aborted with a null-pointer
style crash while scanning pre-existing, already-corrupted InnoDB
tablespaces unrelated to Sport — `laravel.*` and `omeo_hr.positions`, a
known pre-existing local corruption issue, not caused by this session) —
**before** my `CREATE DATABASE sport_concurrency_test` command ever reached
it. I did not retry starting it, did not attempt any repair operation on
`F:\xampp\mysql\data`, and did not touch `omeo_hr` — stopping rather than
risk compounding damage to an already-unstable shared data directory that
also holds real local HR data.

**Net result: the row-lock fix remains unverified against a real MySQL
engine.** What *is* true, stated precisely without overclaiming:
- The code uses Laravel's documented, standard `lockForUpdate()` API inside
  `DB::transaction()`, which is Laravel's thin wrapper over MySQL/InnoDB's
  `SELECT ... FOR UPDATE` — a well-established mechanism for exactly this
  "check-then-insert" race, not a novel or custom locking scheme.
- This has **not** been exercised against a running MySQL instance with two
  real concurrent connections in this session. No test, local or otherwise,
  has demonstrated the lock actually blocks a second connection in practice
  here.
- To actually close this out: either (a) a healthy MySQL/MariaDB instance
  needs to be made available (fixing the local XAMPP corruption is a
  separate, pre-existing infrastructure issue outside this patch's scope —
  it predates Sport and affects unrelated tables), or (b) a dedicated
  staging DB connection you can point me to, after which a small two-process
  concurrency script (two real PHP processes hitting `register()` for the
  same capacity-1 event at the same time) can be run and reported honestly.

## Timezone — fixed in Sport only (round 3); other modules NOT touched

**Round 2 audit (unchanged, still accurate as a description of the rest of
the app)** found that the underlying WIB→UTC input-conversion pattern Sport
needed did not exist anywhere reusable in this codebase, and that Walkin has
the identical unaddressed gap on its own event form. Per your explicit
instruction this round ("jangan ubah modul lain tanpa persetujuan"), **Walkin
was not touched, and `config/app.php`'s global timezone was not changed.**
Instead, the fix is self-contained to Sport's own files:

- `app/Models/Sport/Event.php` — new constant `Event::BUSINESS_TIMEZONE =
  'Asia/Jakarta'` (same value already used as a fallback elsewhere in this
  app — not a new/guessed value, just scoped to Sport).
- `app/Http/Controllers/Sport/EventController.php` — new private
  `jakartaInputToAppTimezone()`, called on `start_at`/`end_at` in
  `validated()` *after* Laravel's `date`/`after:start_at` checks (which
  still compare the two raw naive strings correctly, since both are
  unconverted at that point) and *before* the model is created/updated.
  It does `Carbon::parse($value, Event::BUSINESS_TIMEZONE)->setTimezone(config('app.timezone'))`
  — explicitly interprets the Koordinator's `datetime-local` input as
  Jakarta wall-clock, then converts to whatever the app's storage timezone
  is (UTC today), so stored values compare correctly against `Carbon::now()`
  everywhere else in `Event` (`isRegistrationOpen()`, `isAttendanceWindowOpen()`).
- Display side (so the Koordinator/employee see the time they typed, not a
  shifted one): `resources/views/sport/coordinator/_form.blade.php` (edit
  pre-fill), `resources/views/sport/index.blade.php`,
  `resources/views/sport/show.blade.php`,
  `resources/views/sport/coordinator/show.blade.php` — all now do
  `->copy()->setTimezone(Event::BUSINESS_TIMEZONE)` before `->format()`,
  labeled "WIB" in the UI, matching the existing Walkin/Attendance display
  convention (reusing the *pattern*, not editing those modules' files).
- `event_date` (a plain `date`-cast field, not an instant) and the
  server-generated-only timestamps (`registered_at`, `cancelled_at`,
  `decided_at`) were **not** touched for business-logic purposes — only the
  two already-flagged attendance-confirmation display spots
  (`confirmed_at`) got the same WIB display treatment for readability, since
  nearby code was already being edited; no behavior depends on their
  timezone, only `start_at`/`end_at` do.

**Proven with an end-to-end test, including a deliberate negative control**
(not just asserted): `test_coordinator_datetime_local_input_is_interpreted_as_wib_not_literal_utc`
freezes "now" at a specific UTC instant via `Carbon::setTestNow()`, submits a
real HTTP POST to `sport.coordinator.store` with a `start_at` string
("07:00") that — if wrongly treated as literal UTC — would still appear to
be in the future (bug masked), but which is actually 5 minutes in the past
once correctly interpreted as WIB. Before trusting this test, I temporarily
reverted the fix, re-ran the test, and confirmed it fails with exactly the
predicted 7-hour discrepancy (`Expected '2026-10-21 00:00:00', Actual
'2026-10-21 07:00:00'`), then restored the fix and confirmed it passes again
— ruling out a vacuously-passing test.

**Scope boundary, stated explicitly**: this makes Sport internally correct
and consistent with the rest of OMEO's *display* convention, but it does
**not** fix Walkin's matching input gap (out of scope per your instruction)
and does **not** change the shared `config/app.php` timezone. If Walkin's
own event scheduling has the same real-world 7-hour bug, that remains
unresolved there, to be addressed separately if and when approved.

**Round 4 — edit-path round-trip proven too, not just create**: added
`test_editing_an_event_round_trips_wib_time_correctly_in_form_and_in_boundary_checks`,
which (1) GETs the edit form after creating an event at "07:00" WIB and
asserts the HTML contains `2026-10-21T07:00` (the original WIB value) and
does **not** contain `2026-10-21T00:00` (the raw stored UTC value) — proving
the edit form redisplays the Koordinator's original intended time, not a
shifted one; (2) submits a real `PUT` to `sport.coordinator.update` changing
the time to "08:30" WIB and verifies the stored UTC value (`01:30:00`) and
that `isRegistrationOpen()` behaves correctly against a frozen clock
afterward — proving `update()` goes through the same conversion as
`store()`; (3) re-opens the edit form and confirms it now shows the *new*
WIB time. Verified with a negative control the same way as the round-3
test: temporarily reverted just the edit-form's display conversion,
re-ran, confirmed it failed on exactly the "must not contain the raw UTC
value" assertion, then restored and confirmed it passes again.

---

Findings from reading the actual code (not assumption) — kept here for
reference, this is the round-2 audit that led to the fix above:
- `config/app.php:68` → `'timezone' => 'UTC'`. The whole Laravel app's
  default timezone — and therefore every bare `now()`/`Carbon::now()` call,
  including `Event::isRegistrationOpen()` and `Event::isAttendanceWindowOpen()`
  — operates in UTC, not Asia/Jakarta.
- The established OMEO-wide pattern for anything timezone-sensitive is to
  **store timestamps as UTC internally and convert explicitly to
  `Asia/Jakarta` only at the display boundary** — confirmed in multiple
  places: `app/Services/AttendanceService.php` (`outletTimezone()`,
  `localDate()`, `recomputeSession()` all take an explicit `$timezone` and
  `->setTimezone()` before comparing/displaying), `app/Models/Walkin/Event.php:56-62`
  (`$tz = $this->timezone ?: 'Asia/Jakarta'; ... Carbon::parse(...)->setTimezone($tz)`),
  `app/Http/Controllers/Employee/AttendanceExportController.php:19,184-187`,
  and consistent `_utc`-suffixed column names (`scanned_at_utc`,
  `first_in_at_utc`) plus `Carbon::...->setTimezone('Asia/Jakarta')` calls at
  every display site (`resources/views/attendance/**`,
  `resources/views/recruitment/walkin/**`, `resources/views/landing.blade.php`).
- **However**, the *input* side of that same pattern has a gap that already
  exists in at least one other module, not just Sport:
  `app/Http/Controllers/Recruitment/WalkinController.php:42-43` validates
  `event_start_datetime`/`event_end_datetime` with a bare `'date'` rule — no
  explicit "parse this as Asia/Jakarta wall-clock, then convert to UTC"
  step — even though its own create form
  (`resources/views/recruitment/walkin/create.blade.php:48,53`) is explicitly
  labeled "(WIB)" for the Koordinator/HRD filling it in, and its model cast
  (`app/Models/Walkin/Event.php:29` — `'event_start_datetime' => 'datetime'`)
  is a plain, non-timezone-aware cast.
- **Sport's `EventController::validated()` (lines 194-215) has the exact
  same gap**: a plain `'date'` rule on `start_at`/`end_at`, fed from a
  `datetime-local` HTML input (`resources/views/sport/coordinator/_form.blade.php`),
  with no explicit WIB→UTC conversion before storing, and no
  `Asia/Jakarta`-aware cast on the model (`Event.php` casts them as plain
  `datetime`).

**This was the gap, as it stood at the end of round 2.** Round 3 (above, see
"Timezone — fixed in Sport only") closes it for Sport specifically: the
7-hour discrepancy described here no longer occurs for Sport's own
`start_at`/`end_at`, verified by the negative-control test described above.
Walkin's identical gap is unchanged/untouched, per instruction.

## What's deliberately NOT built yet (needs owner decision — Section L)

Unchanged from the first pass — still not built, still no guessed defaults:

1. Event approval workflow before publish/share.
2. Waitlist when a coach-led event is full (currently: registration is
   simply rejected with "Kuota event ini sudah penuh").
3. Cancellation reason requirement / deadline (`reason` is accepted but
   nullable; no cutoff time enforced).
4. Badminton reward calculation (Section G) — no table, no UI.
5. Historical import from Zoho/other sources (Section H).
6. Backdate / audit-trail correction flow (Section H) — schema columns
   reserved (`is_backdated`, `backdated_by_user_id`, `backdate_reason`) but
   no controller action writes to them.
7. Reporting (monthly/per-event/quarterly Owner summary, Excel export).
8. Non-MKO (external participant) recording.
9. WhatsApp share link — `sport_events.share_token` column exists
   (generated, unused) but no route/controller reads it; cannot currently
   authenticate or expose anything since nothing consumes it.

## Roles & permissions

- New DB role `koordinator` (migration
  `2026_10_20_090100_create_sport_koordinator_role.php`).
- Reused existing `admin`/`finance` roles for the "Admin Finance" persona,
  per the confirmed fallback rule above.
- Reused existing `employee`/`probation` roles for participants; access is
  scoped to the logged-in user's own `employee_id`/registrations in every
  controller method, not by role alone.
- `config/rbac.php`: 3 new permission codes under a new `Sport` group. Note:
  `legacy_role_permissions['finance']` does **not** list these 3 codes —
  Finance's access to Sport routes comes entirely from the `role:` middleware
  string (`koordinator,admin,finance`) and `isSportAdmin()`'s role check, not
  from the permission system. This is consistent with the confirmed fallback
  rule (Finance needs access to *any* event's pending cancellations, not just
  ones tied to a permission grant per event).

## Files changed in rounds 3 and 4 (since the last committed-style review)

**Modified:**
- `app/Models/Sport/Event.php` — added `BUSINESS_TIMEZONE` constant.
- `app/Http/Controllers/Sport/EventController.php` — added
  `jakartaInputToAppTimezone()`, applied to `start_at`/`end_at` in
  `validated()` (see "Timezone" above; shared by both `store()` and
  `update()`).
- `resources/views/sport/coordinator/_form.blade.php`,
  `resources/views/sport/index.blade.php`, `resources/views/sport/show.blade.php`,
  `resources/views/sport/coordinator/show.blade.php` — display times
  converted to WIB, labeled accordingly.
- `tests/Feature/Sport/SportEventLifecycleTest.php` — added 3 tests (WIB/UTC
  negative-control test for create; edit-path round-trip test for update +
  form redisplay; backdate-injection-is-ignored test for Section J's
  backdate acceptance criterion) and a `tearDown()` resetting
  `Carbon::setTestNow()`.
- `README_SPORT_MODULE.md` (this file) — documented the timezone fix, the
  edit-path proof, the backdate-injection test, the full Section B/J/L
  compliance mapping, and the MySQL concurrency-verification attempt (which
  did not complete — see "Concurrency" above).

**Not touched this round or any previous round**: any Walkin file,
`config/app.php`, and (per instruction) `database/migrations/2026_06_11_000001_create_finance_role_and_permissions.php`
— its round-1 fix is unchanged, remains a separate, independent change.
**Not touched, not staged, not referenced in any git command, this round or
any previous round**:
`database/migrations/2026_08_26_090000_add_unique_evaluator_constraint_to_appraisals.php`
— unrelated untracked file from a different, already-closed topic.

## Files touched across all three rounds (full list)

**New:**
- `database/migrations/2026_10_20_090000_create_sport_tables.php`
- `database/migrations/2026_10_20_090100_create_sport_koordinator_role.php`
- `app/Models/Sport/{Event,Registration,CancellationRequest,Attendance}.php`
- `app/Http/Controllers/Sport/{EventController,SportController}.php`
- `routes/sport.php`
- `resources/views/sport/**` (8 Blade files)
- `tests/Feature/Sport/SportEventLifecycleTest.php`
- `README_SPORT_MODULE.md`

**Modified (additive only):**
- `routes/web.php` — one new `require base_path('routes/sport.php')` line.
- `config/rbac.php` — new permission codes + role defaults + route mappings.
- `resources/views/partials/hr_suite_menu.blade.php` — 2 new menu entries.
- `database/migrations/2026_06_11_000001_create_finance_role_and_permissions.php`
  — **this is a separate, independent bugfix, not a Sport feature change.**
  It should be committed on its own, separate from every other file in this
  list, when this work is eventually committed. See "Pre-existing issues
  found" below for what it fixes and why.

**Nothing in `app/Http/Controllers/Finance/`, `app/Http/Controllers/Hrd/`,
or any existing HRD/Finance Blade view was touched.**

## Pre-existing issues found during the required read-only audit

**Two issues were found. One was fixed. One was not.** (Correcting the
previous round's summary wording, which said "two fixes" — that was
imprecise; only one file was actually changed, as `git diff --stat`
confirms.)

1. **`2026_06_11_000001_create_finance_role_and_permissions.php` crashes on a
   fresh migrate.** It `insertGetId()`s role/permission rows that
   `2026_03_10_230000_create_dynamic_rbac_tables.php` already inserts from
   `config('rbac.permissions')` (which has included `finance.*` for a
   while), causing a UNIQUE constraint violation on `migrate:fresh`. Never
   surfaced in production (incremental `migrate` only; this migration's row
   was already recorded there long ago). **Fixed** — made idempotent, same
   pattern as `2026_06_12_000010_seed_master_advanced_permissions.php`
   elsewhere in this codebase. No-op on production; only changes
   from-scratch build behavior. **This is the only file edited for this
   issue — not two.**
2. **No migration in this repository actually `Schema::create`s the
   `outlets` table**, yet `2026_06_12_000004_add_region_legal_entity_to_outlets_table.php`
   unconditionally `Schema::table('outlets', ...)`s it, throwing
   `no such table: outlets` on a fresh `migrate:fresh`. **Not fixed** —
   reconstructing the missing original creation migration is a separate
   archaeology task outside this module's scope. This is why
   `SportEventLifecycleTest` cannot use `RefreshDatabase` (see below).

**Baseline evidence this is pre-existing, not introduced by Sport**:
reproduced directly, before any Sport code existed, by running
`php vendor/bin/phpunit tests/Feature/AppraisalAccessVisibilityTest.php`
against `main`. First run failed with the issue #1 UNIQUE-constraint error
above; after fixing #1, re-running the same command failed with the issue #2
`no such table: outlets` `PDOException` instead (stack trace pointed at
`2026_06_12_000004_add_region_legal_entity_to_outlets_table.php:11`). Both
errors were observed directly in this session's terminal output, not
inferred.

Because of #2, `tests/Feature/Sport/SportEventLifecycleTest.php` does **not**
use `RefreshDatabase`. It uses `DatabaseTransactions` plus a manually-created
minimal schema (`users`, `employees`), then the real Sport migration file
`require`d and `->up()`'d directly — the same workaround pattern already
established in this codebase by
`tests/Feature/AppraisalAccessVisibilityTest::ensureSupportTables()`.

## How to test

```
php vendor/bin/phpunit tests/Feature/Sport/SportEventLifecycleTest.php --testdox
```

**22/22 tests pass** as of this writing (16 of these are in
`SportEventLifecycleTest`; the other 6 — `ExampleTest` + `tests/Unit` — are
unrelated pre-existing tests re-run to confirm no regression):

- Coach-led event cannot be published without positive capacity
- No-coach event can be published without capacity
- **(round 3)** Koordinator's `datetime-local` input is interpreted as
  WIB, not literal UTC — end-to-end HTTP test with a frozen clock; verified
  with a negative control (fails with the predicted 7-hour offset when the
  fix is reverted, passes when restored)
- **(round 4, new)** Editing an event round-trips WIB time correctly: the
  edit form redisplays the original WIB value (not raw UTC), updating via
  `PUT` re-converts correctly, and the boundary check (`isRegistrationOpen()`)
  reflects the edited time correctly — verified with its own negative
  control (reverting just the edit-form's display conversion makes the
  "must not show raw UTC" assertion fail, as expected)
- Employee can register before event starts; duplicate registration rejected
- Registration is rejected after event starts
- Only the assigned Koordinator can decide a cancellation request (original
  pairwise case); registration row is never deleted
- **(new)** Admin or Finance can decide a still-pending cancellation as fallback
- **(new)** A second decision on an already-decided cancellation is a no-op
  (status/actor/timestamp unchanged, no duplicate row)
- **(new)** A regular employee and an unassigned Koordinator cannot decide a
  cancellation (403)
- **(new)** An employee cannot access any Koordinator-only route (403 on
  index/create/show/edit/publish/cancel)
- **(new)** An employee cannot request cancellation or confirm attendance on
  another employee's registration (403), and the event-detail view never
  exposes another employee's registration via `myRegistration`
- **(new)** A coach-led event with capacity reached rejects the next
  registration — **sequential test only, see "Concurrency" above for why
  this doesn't prove the race condition is closed**
- Attendance confirmation is accepted only within 1 hour after event end;
  double-confirmation doesn't create a second record
- **(new)** Attendance confirmation ignores any `confirmed_at`/`is_backdated`/
  `backdated_by_user_id`/`backdate_reason` fields injected into the request
  payload — proves Section J's "backdate tanpa permission ditolak" holds
  even against a direct API attempt, not just "the feature happens to not
  exist"
- Sport attendance never touches any work-attendance/payroll table
  (code-review-verified; test itself is weak — see below)

```
php vendor/bin/phpunit tests/Feature/ExampleTest.php tests/Unit
```
**6/6 pass** — re-confirmed nothing outside Sport regressed in this round.

**Test limitations, stated plainly:**
- The capacity test is sequential, not concurrent — see "Concurrency" section.
- `test_sport_attendance_does_not_touch_employee_timesheet_or_payroll_tables`
  asserts `Schema::hasTable('attendances')` is `false` — which is trivially
  true because the minimal test schema never creates an `attendances` table
  at all, not because Sport code was exercised against a real one. The "Sport
  never touches HRD attendance" claim is verified by reading the Sport
  source code (no reference to `attendances`/`employee_salary_histories`
  anywhere), not by this test's runtime behavior against the real schema.
- **Not run**: the full existing Feature test suite end-to-end — still
  blocked by pre-existing issue #2 above for any test using `RefreshDatabase`
  against the complete migration history. No claim of full regression
  coverage is made.
- **Not completed**: real-MySQL concurrency verification of the capacity
  lock. Attempted this round against an isolated throwaway database
  (`sport_concurrency_test`, never `omeo_hr`); the local MySQL/MariaDB
  instance crashed during its own startup (pre-existing local data
  corruption, unrelated to Sport) before any Sport-related query reached
  it. Not retried, to avoid stressing an already-unstable shared data
  directory. See "Concurrency" above for the full account and what would be
  needed to actually close this out.

## Full compliance mapping against the original MVP brief

Requested as a final check against the complete spec (Sections B/J/L), after
four rounds of implementation + review. Nothing new was built for this
mapping except the one backdate-injection test above — this is a status
report, not new scope.

### Section B — MVP scope items

| # | Item | Status |
|---|---|---|
| 1 | Sport nav module inside OMEO | ✅ Done — menu entries in `hr_suite_menu.blade.php` |
| 2 | Multi-sport event create/manage by Koordinator | ✅ Done |
| 3 | Event list + employee registration | ✅ Done |
| 4 | Cancellation request + Koordinator decision | ✅ Done, **plus** the confirmed Admin/Finance pending-fallback rule |
| 5 | Post-event attendance confirmation (1h window) | ✅ Done, server-clock-based, WIB-correct |
| 6 | Mandatory capacity for coach-led events; no cap otherwise | ✅ Done |
| 7 | Attendance/reward policy settings, badminton distinguishable | ❌ Not built — Section G is entirely deferred (threshold ambiguity unresolved, per instruction not to guess) |
| 8 | Monthly / per-event / quarterly-Owner reports, Excel export | ❌ Not built |
| 9 | Historical import (preview/mapping/dedup) | ❌ Not built |
| 10 | Limited, audited backdate | 🟡 Schema columns reserved only; no write path exists at all — now *proven* disabled against injection (new test), not just absent |
| 11 | Non-MKO recording within existing OMEO capability | ❌ Not built — no decision on the mechanism, per Section L |

### Section J — Required acceptance tests

| # | Criterion | Status | Test |
|---|---|---|---|
| 1 | Coach-led event can't publish without positive capacity | ✅ Pass | `test_coach_led_event_cannot_be_published_without_positive_capacity` |
| 2 | No-coach event publishes without a cap | ✅ Pass | `test_no_coach_event_can_be_published_without_capacity` |
| 3 | Koordinator manages own events; regular user cannot | ✅ Pass | `test_employee_cannot_access_coordinator_only_routes` |
| 4 | MKO registers before start; rejected after | ✅ Pass | `test_employee_can_register_before_event_starts_and_duplicate_is_rejected`, `test_registration_is_rejected_after_event_starts` |
| 5 | Duplicate registration rejected, retry idempotent | ✅ Pass | same as #4 |
| 6 | Cancellation request created; only assigned Coordinator (+confirmed Admin/Finance fallback) decides; record not deleted | ✅ Pass | `test_only_assigned_coordinator_can_manage_cancellation_and_registration_is_not_deleted`, `test_admin_or_finance_can_decide_a_still_pending_cancellation_as_fallback`, `test_regular_employee_and_unassigned_coordinator_cannot_decide_cancellation` |
| 7 | Registration/attendance timing uses server timezone | ✅ Pass | `test_coordinator_datetime_local_input_is_interpreted_as_wib_not_literal_utc`, `test_editing_an_event_round_trips_wib_time_correctly_in_form_and_in_boundary_checks` |
| 8 | Attendance accepted in-window, rejected after 1h | ✅ Pass | `test_attendance_confirmation_only_accepted_within_one_hour_after_event_ends` |
| 9 | Double confirmation doesn't duplicate attendance | ✅ Pass | same as #8 |
| 10 | Employee sees/changes only own data; Admin/Koordinator don't get broad HRD access implicitly | ✅ Pass | `test_employee_cannot_view_or_modify_another_employees_registration` + RBAC audit in round 1 (no HRD permission was granted to the new `koordinator` role or to Finance beyond the 3 new Sport-scoped permissions) |
| 11 | Import preview/dedup/no-duplicate-on-replay/no-auto-paid | ⚪ N/A — feature not built (correctly deferred, Section H undecided) |
| 12 | Backdate rejected without permission/reason; disabled means no UI/API path | ✅ Pass (by both absence *and* now a direct test) | `test_attendance_confirmation_ignores_any_backdate_fields_in_the_request_payload` |
| 13 | Reward threshold ambiguity → no default payout; non-badminton sports get no cash reward | ⚪ N/A — feature not built (correctly deferred, Section G undecided) |
| 14 | Sport attendance never writes to work attendance/payroll | 🟡 Pass, weak test | `test_sport_attendance_does_not_touch_employee_timesheet_or_payroll_tables` — see "Test limitations": the assertion is trivially true in the minimal test schema; the real guarantee comes from reading Sport's source code, which references no HRD attendance/payroll table anywhere |
| 15 | Existing HRD/Finance tests pass; failures recorded, not hidden | 🟡 Partial | Full suite can't run to completion (pre-existing `outlets` migration gap, documented, reproduced before any Sport code existed, not hidden). What *could* run (`ExampleTest`, `tests/Unit`) passes — 6/6, re-confirmed every round |

**Legend**: ✅ built and passing · 🟡 partially verified/caveated · ⚪ not applicable (feature correctly out of scope) · ❌ not built

### Section L — Owner decisions still outstanding (none guessed)

| Question | Status |
|---|---|
| Event approval before publish/share? | Not decided — Koordinator publishes directly, no gate added |
| Waitlist when coach-led event is full? | Not decided — registration simply rejected, no waitlist table |
| Cancellation deadline/reason requirement, slot release timing? | Not decided — `reason` optional, slot releases only on Koordinator/Admin-Finance approval (confirmed this round), no deadline enforced |
| Who besides Koordinator can fix attendance; is roster visible to all employees? | Not decided — only Koordinator/Admin/Finance see rosters; no attendance-correction UI exists at all |
| Backdate window/evidence/approval? | Not decided — feature entirely absent, now provably un-exploitable rather than just unbuilt |
| Import file format/columns; Non-MKO historical handling? | Not decided — import not built |
| Badminton reward threshold (`>=4` vs `>4`), nominal, period, approver? | Not decided — reward not built, no config UI, no guessed default |
| Quarterly report: calendar vs fiscal quarter? | Not decided — reporting not built |

None of these were resolved by guessing; each remains either absent or explicitly gated, consistent with every prior round's reports.

## Slice 2 (v1.1) — reporting, Non-MKO, import historis, draft reward, backdate, share

Built per **Spesifikasi MVP OMEO Sport v1.1 (20 Oktober 2026)**, Section 12
"Acceptance criteria" dan Section 13 "Open decisions". Fully additive on top
of Slice 1 — no Slice 1 file listed in previous sections was modified except
`resources/views/sport/coordinator/show.blade.php` (added a WhatsApp
share-link button and a per-registration "Backdate" mini-form, both
conditionally admin/finance-only) and `app/Models/Sport/Event.php` (added
`nonMkoAttendances()`/`importBatches()` relations only).

### New files

- `database/migrations/2026_10_20_090200_create_sport_phase2_tables.php` —
  `sport_participants`, `sport_non_mko_attendances`, `sport_import_batches`,
  `sport_import_rows`, `sport_reward_configs`, `sport_backdate_settings`,
  `sport_backdate_audits`. Not run against any real database — only via the
  SQLite test schema (see "How to test").
- `app/Models/Sport/{Participant,NonMkoAttendance,ImportBatch,ImportRow,RewardConfig,BackdateSetting,BackdateAudit}.php`
- `app/Services/Sport/ImportService.php` — all parsing/matching/commit logic
  for historical import, kept out of the controller for testability.
- `app/Http/Controllers/Sport/Admin/{ParticipantController,NonMkoAttendanceController,ReportController,ReportExportController,ImportController,RewardConfigController,BackdateController}.php`
- `resources/views/sport/admin/**` (reports, participants, reward, backdate,
  import create/map/preview) and `resources/views/sport/admin/events/non_mko.blade.php`.
- New routes under `routes/sport.php`, prefix `sport-admin`, all gated by
  `role:admin,finance` (same literal-role middleware pattern as Slice 1's
  `sport.coordinator.*` group — Koordinator has **no** access to any of these).
- `config/rbac.php`: 5 new permission codes (`sport.reports.view`,
  `sport.participants.manage`, `sport.import.manage`, `sport.reward.manage`,
  `sport.backdate.manage`) added to `legacy_role_permissions['finance']` and
  5 new `route_permissions` wildcard entries — **only** so the sidebar menu
  (`$routeItem()` helper) correctly hides these links from non-admin/finance
  users; the routes themselves are already protected independently by
  `role:admin,finance` middleware regardless of this config.

### Non-MKO (Section 2 & 9)

Deliberate design choice: Non-MKO participants are **not** stored anywhere
near `employees`. `sport_participants` is a wholly separate table, and their
event participation lives in `sport_non_mko_attendances` — a separate table
from `sport_attendances` (which stays MKO-only, FK'd to `employees`). There
is no self-service/registration flow for Non-MKO at all; every
`sport_non_mko_attendances` row is created by an Admin/Finance action
(`NonMkoAttendanceController`), never by the participant. No login account,
no portal — confirmed by test
`test_admin_can_record_non_mko_participation_without_creating_an_employee_record`,
which asserts `Employee::count()` is unchanged after recording a Non-MKO
attendance. `test_koordinator_cannot_record_non_mko_participation` proves
Koordinator (who *can* reach the regular roster page) gets 403 on the
Non-MKO route.

### Reporting & export (Section 8)

`ReportController` builds: summary cards (peserta unik MKO/Non-MKO,
attendance sessions "orang-sesi" MKO/Non-MKO — **always shown as separate
labelled numbers, never summed into one figure**, per acceptance criteria
#7), monthly breakdown, per-event breakdown, and quarterly breakdown.
`ReportExportController` renders the identical filtered data to an `.xlsx`
with a dedicated "Reward (Draft)" sheet stamped `DRAFT — BELUM DISETUJUI —
TIDAK DIBAYARKAN` in red, so a report reader can never mistake the draft
config for an approved/paid figure.

**Quarter definition decision (documented, not guessed):** quarterly
grouping uses the calendar quarter (Q1 Jan-Mar … Q4 Oct-Dec). This is stated
explicitly in both the on-screen report and the exported sheet as a
*default, reversible* choice — Section 13 Open Decision #7 (kalender vs
fiskal) is still owner's to make; nothing here silently assumes fiscal.

Test `test_report_distinguishes_unique_participants_from_attendance_sessions`
proves the core distinction with concrete numbers: one employee attending 2
different events yields `unique_participants_mko = 1` but
`attendance_sessions_mko = 2`.

### Import historis (Section 9)

Scoped deliberately to **one target event per import batch** (chosen before
upload) — the spec does not describe automatic event creation from a
spreadsheet, so this was not invented. Flow: upload (`.csv`/`.xlsx`) → column
mapping (admin explicitly assigns which spreadsheet column is the stable
identifier, name, participant-type flag, attended-at) → preview (every row
computed to `valid`/`duplicate`/`invalid`/`unmatched` with a human-readable
reason) → explicit commit.

Hard rules enforced in `ImportService` (not relaxed anywhere):
- **Never matches by name alone.** MKO identity is resolved only via
  `employees.employee_number`, `employees.nik`, or `employees.external_id`.
  An unmatched identifier is reported as `unmatched`, never silently created
  as a new Non-MKO participant.
- **Non-MKO classification requires an explicit signal** in the mapped
  "participant type" column (e.g. the literal text "Non-MKO"). A blank
  identifier with no such signal is `invalid`, not auto-classified.
- **Known, honest limitation**: an MKO employee match with no linked `User`
  account (`users.employee_id`) is reported `invalid` with that exact reason
  — `sport_registrations.user_id` is `NOT NULL` (unchanged Slice-1 schema),
  so the import cannot fabricate a user to satisfy the FK. This is a real
  constraint, surfaced to the admin rather than worked around with fake
  data. Proven by `test_import_matches_only_by_stable_employee_identifier_and_flags_unmatched_and_invalid_rows`.
- **Idempotent at two levels**: (1) file-level — re-uploading a byte-identical
  file (sha256 hash) to the same event after it was already committed is
  rejected at upload time, before any row is even parsed; (2) row-level —
  committing the same batch twice, or a different file whose rows resolve to
  an attendance that already exists, skips those rows as `duplicate` rather
  than creating a second record. Proven by
  `test_import_is_idempotent_for_committing_the_same_file_twice_to_the_same_event`.
- **Never touches `sport_reward_configs`.** Proven by
  `test_import_never_creates_or_activates_reward_config`.
- No Zoho API call anywhere — upload is a plain `multipart/form-data` file
  input (`.csv`/`.xlsx`), parsed locally with PhpSpreadsheet/`fgetcsv`.

### Draft reward badminton (Section 7 & 13)

`RewardConfigController::store()` **forces** `status = 'draft'` on every
save, discarding whatever the request actually sends for that field — proven
by `test_reward_config_is_always_saved_as_draft_regardless_of_submitted_status`,
which deliberately posts `status => 'active'` and asserts the stored row is
still `draft`. There is no activate/approve/payout action anywhere in this
controller, model, or any other code path — `RewardConfig` has no method
that computes a payout. The Tier 3 `>=4` vs `>4` ambiguity (Section 7/13) is
stored as an explicit, nullable `tier3_comparison` enum (`gte`/`gt`) that the
form leaves unselected by default — never defaulted to either value.

### Backdate (Section 6, 9 & 13)

Scope decision, documented clearly to avoid confusion with Non-MKO entry
above: "backdate" here means **correcting/creating an MKO attendance
confirmation after its normal 60-minute self-confirm window has closed**.
Non-MKO attendance entry (above) is not "backdate" — it is Non-MKO's only,
always-on recording mechanism, since they never had a self-confirm window to
miss.

`sport_backdate_settings` is a single-row config; `max_backdate_days = NULL`
(its default, never set by the migration) means the feature is **rejected
for every role, including Admin Finance** — `BackdateController::store()`
checks `BackdateSetting::isEnabled()` before anything else, and returns an
error without creating or modifying any `sport_attendances` row. This was
the specific stronger test the review asked for, and it's there:
`test_backdate_is_rejected_for_admin_finance_when_setting_is_empty`.

Once an Admin Finance explicitly sets a `max_backdate_days` value (own
action, own audit entry on the settings table), backdate becomes available
*only within that many days of the event's end*, *only* via
`role:admin,finance` routes, *only* with a mandatory free-text `reason`, and
every call writes a `sport_backdate_audits` row with actor, action
(`create`/`update`), reason, and before/after `confirmed_at` — proven by
`test_backdate_succeeds_for_admin_finance_once_setting_enabled_and_writes_audit_trail`.
Koordinator and regular employees get a 403 at the route-middleware layer
regardless of the setting — proven by
`test_backdate_route_rejects_koordinator_and_employee_regardless_of_setting`.
No default/fallback day-limit was invented anywhere in code.

### Share link (Section 10)

No new "deep link" mechanism was built. The Koordinator roster page now has
a "Bagikan via WhatsApp (manual)" button that opens `wa.me/?text=...` with
the plain, already-existing **authenticated** `route('sport.show', $event)`
URL embedded as text — opened manually by the Koordinator, never auto-sent.
Clicking that link still requires a normal OMEO login (`auth` middleware +
`requireEmployeeId()`), so it cannot function as a bearer token or
auto-login mechanism. The pre-existing `sport_events.share_token` column
(from Slice 1) is **intentionally left unused** — wiring it into a
token-based bypass URL would have violated the explicit "tidak boleh menjadi
bearer token... auto-login" rule, so it was not used rather than
repurposed unsafely. Proven by
`test_share_link_points_to_authenticated_event_route_and_guest_cannot_bypass_login`,
which asserts an unauthenticated request to the event URL redirects to
`login` (no bypass), then asserts the Koordinator's page actually contains
that same authenticated URL (url-encoded) in the WhatsApp link.

### Gap matrix — Spesifikasi v1.1 Section 12 (Acceptance criteria)

| # | Acceptance criteria (ringkas) | Status |
|---|---|---|
| 1 | Event WIB create/edit/show konsisten | Done — Slice 1, unchanged |
| 2 | Coach-led wajib kapasitas; no-coach bebas | Done — Slice 1, unchanged |
| 3 | Registrasi tutup saat mulai; no duplikat; kuota tidak terlampaui | Done — Slice 1 (SQLite sequential only; MySQL concurrency unverified, see below) |
| 4 | Cancellation: Koordinator utama, Admin/Finance fallback pending, 1 keputusan menang | Done — Slice 1, unchanged |
| 5 | Attendance window +60 menit, unik per karyawan/event, tidak sentuh payroll | Done — Slice 1, unchanged |
| 6 | Admin Finance lihat MKO+Non-MKO, Non-MKO manual tanpa akun/portal | **Done — Slice 2** (`ParticipantController`, `NonMkoAttendanceController`) |
| 7 | Laporan bulanan/per-event/triwulanan, unik vs orang-sesi dibedakan, export Excel konsisten filter | **Done — Slice 2** (`ReportController`, `ReportExportController`) |
| 8 | Import Excel/CSV: mapping/preview, error/duplikat, confirm eksplisit, idempotent, tidak bayar reward | **Done — Slice 2** (`ImportService`, `ImportController`) |
| 9 | Draft reward tersimpan, payout tidak aktif sampai disetujui | **Done — Slice 2** (`RewardConfigController` forces `status=draft`) |
| 10 | Backdate Admin Finance saja, reason wajib, audit lengkap, disabled sebelum batas diisi | **Done — Slice 2** (`BackdateController`, `sport_backdate_settings`) |
| 11 | Share manual/deep-link, tidak auto-login, tidak auto-send | **Done — Slice 2** (WhatsApp button, authenticated URL only) |
| 12 | Tests: role isolation, cross-user, WIB, cancellation fallback, retry/idempotency, import duplicates, laporan/export, backdate guards, pemisahan HRD/payroll | **Done** — see test list below; MySQL-concurrency and full `RefreshDatabase` suite remain blocked (pre-existing, unrelated — see "Pre-existing issues") |

### Open Decisions (Section 13) — explicitly still unresolved, not guessed

| # | Decision | Where this is reflected in code |
|---|---|---|
| 1 | Approval Admin Finance sebelum publish/share event | Not implemented — Koordinator still publishes directly (Slice 1 behavior, unchanged) |
| 2 | Waitlist saat coach-led penuh | Not implemented — registration rejected outright when full (Slice 1, unchanged) |
| 3 | Batas waktu pengajuan cancellation & alasan wajib/tidak | Not implemented — `reason` stays `nullable` on `sport_cancellation_requests` (Slice 1, unchanged) |
| 4 | Siapa boleh koreksi attendance & roster visibility detail | Backdate restricted to Admin Finance only (narrower than "siapa saja" — a safe default, not the final policy) |
| 5 | Backdate max days | `sport_backdate_settings.max_backdate_days` NULL by default — feature fully disabled until an owner sets a value (no fallback invented) |
| 6 | Threshold/tarif/periode reward final (termasuk >=4 vs >4) | `sport_reward_configs` stores draft fields only; `tier3_comparison` left NULL; `status` hard-forced to `draft` everywhere |
| 7 | Definisi quarter Owner (kalender/fiskal) | Calendar quarter used as an explicitly-labelled default in both UI and Excel export; not silently fiscal |
| 8 | Format kolom Excel & data minimum Non-MKO | Import mapping is fully manual/admin-chosen per batch (no fixed template assumed); Non-MKO fields kept to name/phone/external_reference/notes only |
| 9 | Autentikasi/SSO & kemampuan WhatsApp deep-link di repo | Confirmed: `auth` middleware is the only gate used; WhatsApp integration is `wa.me` manual-click only (no API, no official integration found in repo) |

### How to test (Slice 2 additions)

Same command as Slice 1 (`php artisan test --filter=SportEventLifecycleTest`),
same SQLite `:memory:` + `DatabaseTransactions` + hand-rolled schema strategy
(now also loading `2026_10_20_090200_create_sport_phase2_tables.php`
directly from file, exactly like Slice 1's migration). 28 tests total in
this file now pass (16 Slice 1 + 12 Slice 2), 0 failures:

```
php artisan test --filter=SportEventLifecycleTest
Tests: 28 passed (118 assertions)
```

Full-suite run (`php artisan test`, no filter) still fails on the
pre-existing `outlets` migration gap documented below — **confirmed this is
not a Slice 2 regression**: the failures are `QueryException: no such table:
outlets` across completely unrelated files (`Auth\AuthenticationTest`,
`AppSettingsSmtpTest`, etc.) that use `RefreshDatabase` and have nothing to
do with Sport. This module continues to avoid `RefreshDatabase` for exactly
this reason.

**Not independently verified in this round** (explicitly, so this isn't
over-claimed):
- Real MySQL/InnoDB row-locking for the Slice 1 capacity race condition —
  unchanged limitation from earlier rounds, still blocked by the local
  crashed MySQL data directory, which was **not** retried this round either.
- The Excel export's actual rendered file (sheets/cell values) was not
  opened and visually inspected — only that `ReportExportController::export()`
  is wired to the same filtered data as the on-screen report. If an exact
  spreadsheet-layout check is wanted, that would need a follow-up test
  opening the generated `.xlsx` with PhpSpreadsheet and asserting cell
  values.
- The `.xlsx` upload path of the importer (vs `.csv`, which is what the
  tests exercise) was not exercised by an automated test in this round —
  the PhpSpreadsheet `IOFactory::load()` code path for `.xlsx` is the same
  library already used elsewhere in this codebase (`AnnualSummaryExportController`
  et al.) but reading `.xlsx` specifically was not independently proven here.

**Update (next round): the `.xlsx` upload gap above is now closed** — see
"Slice 2 — gap closures" below, `test_import_accepts_a_real_xlsx_upload_not_just_csv`.

## Slice 2 — gap closures (group filter, fiscal quarter, retention, risky test)

Four follow-up items, all driven by newer, more specific user decisions that
**supersede** some of the defaults this file previously documented
(explicitly: the calendar-quarter default and the cascade-delete FK actions
below are no longer current — superseded, not still true).

### 1. Group filter (`group=all|mko|non_mko`) now actually filters

Previously accepted but ignored. Now wired through
`ReportController::includeMko()/includeNonMko()`, applied consistently in
`buildSummary()`, `buildMonthly()`, `buildPerEvent()`, `buildQuarterly()`,
and `ReportExportController` (same instance, same methods — no duplicated
logic). Rule implemented exactly as decided: the filtered-out group's
numbers become `null` ("n/a (difilter grup)" in the UI/export, never `0`,
since `0` would wrongly imply confirmed zero participation), while
`total_events` is **always** computed from period+sport only and never
changes with the group filter. MKO-only concepts (`cancellations_approved`,
`not_confirmed`, `registrations`) are hidden under `group=non_mko` since
Non-MKO has no registration/cancellation flow at all.

Tests: `test_group_filter_all_shows_mko_and_non_mko_separately_and_total_events_unaffected`,
`test_group_filter_mko_hides_non_mko_numbers_but_keeps_total_events`,
`test_group_filter_non_mko_hides_mko_numbers_but_keeps_total_events`,
`test_group_filter_export_matches_on_screen_report_numbers`.

**Known gap, not yet built**: there's no "roster rows" (individual
participant list) view in the report yet, only aggregate counts — the
group filter correctly restricts the aggregate numbers, but there was no
existing participant-listing table to filter row-by-row beyond `Per Event`'s
event-level counts.

### 2. Fiscal quarter — `sport_report_settings.fiscal_year_start_month`, NULL by default

**This supersedes the calendar-quarter default documented earlier in this
file.** New table `sport_report_settings` (migration
`2026_10_20_090300_create_sport_report_settings_table.php`), single row,
`fiscal_year_start_month` nullable 1-12, **no default value written by any
migration or code path** — not January, not August (the fact that current
seed/demo data happens to span Aug-Oct is not read as a fiscal-year
decision anywhere).

While unconfigured: `ReportController::buildQuarterly()` returns
`type => 'unconfigured'` with a single "periode kustom" row covering
whatever `from`/`to` filter is active (or "seluruh data" if none), and the
UI/export both show an explicit banner — never a `Q1`-`Q4` label, never the
word "fiskal" applied to the result. Admin Finance sets the value via
`GET/POST sport-admin/reports/fiscal-settings` (`role:admin,finance`).
Once set, `fiscalLabel(Carbon $date, int $startMonth)` maps dates to
`FY{year}-Q{1-4}`, with the fiscal year named by the calendar year **in
which it starts** — correctly keeps e.g. December and the following
January in the same fiscal year when they're both before/after the
configured start month, rather than splitting them by calendar year.

Tests: `test_fiscal_quarter_report_shows_unconfigured_message_and_never_calendar_default`,
`test_fiscal_year_start_month_rejects_out_of_range_values` (0, 13, -1),
`test_koordinator_cannot_set_fiscal_year_start_month`,
`test_fiscal_label_maps_quarters_correctly_at_boundaries_and_across_calendar_year`
(exact boundary dates: 30 Jun vs 1 Jul; cross-year 31 Dec 2025 vs 1 Jan
2026; fiscal-year start date itself),
`test_fiscal_quarterly_report_groups_cross_calendar_year_events_into_same_fiscal_year_once_configured`
(end-to-end HTTP test with real events dated Dec 2025 and Jan 2026).

### 3. Retention — cascade-delete removed from every history-bearing FK; one hard-delete path found and removed

**This supersedes the FK actions originally written in both Sport
migrations.** Audited every FK and every `->delete()`/`forceDelete()`/
`destroy()` call across the whole Sport module. Found exactly **one**
hard-delete code path: `NonMkoAttendanceController::destroy()` (DELETE
route `sport-admin/non-mko-attendances/{id}`), which hard-deleted a
Non-MKO attendance row — the **only** attendance record Non-MKO
participants ever get, since they have no self-service. **Removed
entirely** (method + route + the now-dead `route_permissions` entry) —
there is no replacement; corrections are made by adding a new record with
a note, not deleting the old one.

No `EventController::destroy()` ever existed — `cancel()` (status update +
actor + timestamp + reason, present since Slice 1) was already the
"archive, don't delete" mechanism this decision asks for; nothing new was
needed there, just confirmed and tested.

FK actions changed from `cascadeOnDelete()` to `restrictOnDelete()` on
every edge that pointed at history (both migration files edited in-place —
confirmed safe because neither has ever been run via `php artisan migrate`
against any database; see "Deployment status"):
- `2026_10_20_090000_create_sport_tables.php`: `sport_registrations.sport_event_id`,
  `sport_cancellation_requests.sport_registration_id`,
  `sport_attendances.sport_event_id`, `sport_attendances.sport_registration_id`.
- `2026_10_20_090200_create_sport_phase2_tables.php`: `sport_non_mko_attendances.sport_event_id`,
  `sport_import_batches.sport_event_id`, `sport_backdate_audits.sport_event_id`,
  `sport_backdate_audits.sport_attendance_id` (was `nullOnDelete()`).
  `sport_import_rows.sport_import_batch_id` was deliberately **left**
  `cascadeOnDelete()` — safe, because `resulting_attendance_id`/
  `resulting_non_mko_attendance_id` on that table are plain integer columns
  with no FK constraint at all, so deleting import bookkeeping rows never
  touches the actual `Attendance`/`NonMkoAttendance` rows they produced.

Since `Event` already uses `SoftDeletes`, a plain `$event->delete()` was
never actually hard-deleting anything — this FK change specifically closes
the `forceDelete()`/raw-SQL path, which is the realistic way history could
otherwise disappear silently.

Tests: `test_cancelling_an_event_preserves_registrations_attendance_and_import_history`,
`test_hard_deleting_event_with_registration_is_blocked_by_foreign_key`,
`test_hard_deleting_event_with_non_mko_attendance_is_blocked_by_foreign_key`,
`test_hard_deleting_event_with_import_batch_is_blocked_by_foreign_key`,
`test_hard_deleting_event_with_backdate_audit_is_blocked_by_foreign_key`,
`test_no_hard_delete_route_exists_for_non_mko_attendance_history` (regression
guard against the removed route ever coming back).

**Stated limitation**: these FK-restrict tests run against SQLite with
`foreign_key_constraints` enabled (confirmed in `config/database.php`,
not overridden in `phpunit.xml`), which does enforce the constraint and
throw `QueryException` — but SQLite's exact restrict-error behavior under
concurrent writers/complex cascading scenarios has not been separately
verified against real MySQL/InnoDB this round, consistent with every other
concurrency caveat already documented in this file.

### 4. Export test risky-flag — fixed at the root cause, not suppressed

`ReportExportController::export()`'s defensive `while (ob_get_level()) {
ob_end_clean(); }` (copied from the existing `AnnualSummaryExportController`
pattern) was draining PHPUnit's own output buffer along with everything
else, since it always drained to level 0 regardless of what level was
already open before the method ran. Fixed by snapshotting
`ob_get_level()` at method entry and only draining buffers opened **after**
that point — functionally identical protection against stray output in
this method (which never renders a view, so there's nothing to drain in
practice) without ever touching buffers that existed before the method was
called. No other export controller was touched; no PHPUnit risky-detection
setting was disabled; no assertions were removed.

```
php artisan test --filter=test_report_export_xlsx_contains_correct_sheets_and_matches_report_numbers
Tests: 1 passed (21 assertions)      — 0 risky
```

### Full Sport suite after this round

```
php artisan test --filter=SportEventLifecycleTest
Tests: 47 passed (192+ assertions), 0 risky, 0 failed
```

```
php artisan test tests/Unit tests/Feature/ExampleTest.php
Tests: 6 passed (16 assertions)
```

### Migration-environment check (read-only, no real DB touched)

To verify a real `php artisan serve` session is actually bootable locally
(not just the PHPUnit hand-rolled schema), a **brand-new, throwaway SQLite
file** was created this round (never touching `.env`, never touching
`omeo_hr`) and `php artisan migrate --force` was run against **only that
isolated file** via an inline `DB_DATABASE` override. Result: it fails on
the exact same pre-existing bug documented earlier in this file —
`SQLSTATE[HY000]: General error: 1 no such table: outlets` — part-way
through the **unrelated, pre-Sport** migration history (fails at
`2026_06_12_000004_add_region_legal_entity_to_outlets_table.php`, before
reaching any Sport migration at all). The throwaway file was deleted
immediately after. **This confirms, freshly and directly (not from memory
of earlier rounds), that no amount of Sport-side work can produce a
bootable local browser session until this unrelated migration gap is fixed
— and fixing it is explicitly out of scope for this task.** See "How to
test locally" below for what's actually usable right now instead.

## How to test locally — safe instructions, with the server-startup blocker stated plainly

**`.env` in this repo points to `DB_CONNECTION=mysql`, `DB_DATABASE=omeo_hr`,
`DB_HOST=127.0.0.1`** — the real local HR database, with a documented
history of MySQL data-directory corruption/crashes from earlier rounds.
Per the safety rules for this task, **no server was started and no
migration was run against this connection.**

**A genuinely isolated alternative (fresh SQLite file) was tried and is
also blocked** — not by anything fragile about the DB itself, but because
the full `php artisan migrate` needed to boot the *entire* app (menus,
auth, dashboard — not just Sport) fails on the pre-existing `outlets`
migration gap before it ever reaches Sport's own migrations (see check
above). **Conclusion: a real browser click-through of the Sport module is
not currently possible in this environment, independent of anything in
this patch.** This is a pre-existing, out-of-scope blocker, not something
introduced or left unfixed by the Sport work itself.

**What is safe and already proven right now** is the automated HTTP-level
test suite — it exercises real routes, real controllers, real middleware,
and a real (if minimal, hand-built) schema, not mocks. Every scenario
requested maps to a specific passing test:

| Scenario | Test |
|---|---|
| Buat event coach-led / no-coach | `test_coach_led_event_cannot_be_published_without_positive_capacity`, `test_no_coach_event_can_be_published_without_capacity` |
| Daftar event | `test_employee_can_register_before_event_starts_and_duplicate_is_rejected` |
| Cancellation — Koordinator / Admin-Finance fallback | `test_only_assigned_coordinator_can_manage_cancellation_and_registration_is_not_deleted`, `test_admin_or_finance_can_decide_a_still_pending_cancellation_as_fallback` |
| Konfirmasi hadir, WIB | `test_attendance_confirmation_only_accepted_within_one_hour_after_event_ends`, `test_coordinator_datetime_local_input_is_interpreted_as_wib_not_literal_utc` |
| Input Non-MKO | `test_admin_can_record_non_mko_participation_without_creating_an_employee_record` |
| Filter MKO/Non-MKO pada laporan | `test_group_filter_mko_hides_non_mko_numbers_but_keeps_total_events`, `test_group_filter_non_mko_hides_mko_numbers_but_keeps_total_events` |
| Report/export | `test_report_export_xlsx_contains_correct_sheets_and_matches_report_numbers`, `test_group_filter_export_matches_on_screen_report_numbers` |
| Import dry-run/preview | `test_import_matches_only_by_stable_employee_identifier_and_flags_unmatched_and_invalid_rows` (preview step, before commit) |
| Reward draft | `test_reward_config_is_always_saved_as_draft_regardless_of_submitted_status` |
| Backdate disabled | `test_backdate_is_rejected_for_admin_finance_when_setting_is_empty` |
| Cancel/archive retention | `test_cancelling_an_event_preserves_registrations_attendance_and_import_history` |

Run all of them:
```
php artisan test --filter=SportEventLifecycleTest
```

**If/when the `outlets` gap is fixed (separately, with its own
authorization)**, a real manual session would look like: run
`php artisan migrate` against a **dedicated, fresh, non-`omeo_hr` database**
(e.g. a new local MySQL schema or SQLite file, bound only via an env
override the same way this round's check did — never edit the real
`.env`), bind `php artisan serve --host=127.0.0.1` (never `0.0.0.0`), then
create synthetic accounts with `php artisan tinker`:
`User::factory()->create(['role' => 'admin'])` /
`['role' => 'finance']` / `['role' => 'koordinator']` /
`['role' => 'employee', 'employee_id' => Employee::factory()->create()->id]`.
Not done this round — stated as the path forward, not executed, since the
blocker above makes it non-functional today.

## Post-deploy fixes and additions (after Slice 2 went live)

Two migration fixes shipped directly to `main` after the first production
deploy surfaced them (both documented in their own commit messages):

1. **MySQL identifier length violation** — the auto-generated unique
   constraint name for `sport_non_mko_attendances` (68 chars) exceeded
   MySQL's 64-char limit (SQLite has no such limit, so local tests never
   caught it). Fixed with an explicit short constraint name.
2. **Self-healing migration** — production's first deploy attempt failed
   mid-migration after that error, leaving `sport_participants` and
   `sport_non_mko_attendances` partially created. `2026_10_20_090200_create_sport_phase2_tables.php`
   now wraps every `Schema::create()` in a `Schema::hasTable()` check (and
   the unique constraint in a `Schema::hasIndex()` check), so `php artisan
   migrate` is safe to re-run regardless of which tables already exist —
   verified locally by reproducing production's exact partial state in an
   isolated SQLite file before shipping the fix.

### Selfie (live camera) required for attendance confirmation

New requirement: employee attendance confirmation (`SportController::confirmAttendance()`)
now requires a selfie captured live from the camera, following the same
pattern already used by the work-attendance system (`AttendanceController::storeBase64EvidencePhoto()`)
but simplified — **one photo only**, no environment photo, no geolocation,
since this is sports-event attendance, not daily work attendance.

- `capture_mode` must be exactly `live_camera`; any other value (e.g. a
  plain file upload) is rejected — mirrors the work-attendance system's
  anti-upload rule exactly.
- `selfie_photo_data` must be a `data:image/{png,jpeg,jpg,webp};base64,...`
  URI, max 4MB decoded — same validation and size limit as the work-
  attendance photos, reusing the identical regex/limit rather than
  inventing new ones.
- Stored under `storage/app/public/sport-attendance/{event_id}/{employee_id}/...`
  — a separate path from `attendance/` (work attendance photos), so the
  two are never mixed on disk.
- New nullable column `sport_attendances.selfie_photo_path` (migration
  `2026_10_21_100000_add_selfie_photo_to_sport_attendances.php` — added as
  a new migration, not an edit to the already-deployed table-creation
  migration). NULL for attendance rows created via backdate (Admin Finance
  correction, not a self-service camera capture) or historical import
  (no photo exists for old data) — both remain valid without a photo.
- Koordinator roster (`sport/coordinator/show.blade.php`) now shows a
  "lihat selfie" link next to any confirmed attendance that has one.

Tests: `test_attendance_confirmation_requires_a_live_camera_selfie` (no
photo rejected, non-live-camera capture_mode rejected, malformed data-URI
rejected with a clear error not a 500, valid payload succeeds and the file
is actually written to the fake storage disk, Koordinator roster page
renders the new link without error) plus the 4 pre-existing confirm-
attendance tests updated to send a fake-but-valid photo payload (via a new
`fakeSelfiePayload()` test helper) since the behavior they exercise now
requires one. `Storage::fake('public')` added to `setUp()` so no test ever
writes a real file to disk.

**Not done**: displaying/moderating selfies in bulk (e.g. an admin gallery
view across all events) — only the per-registration "lihat selfie" link in
the existing Koordinator roster was added, since that wasn't asked for.

## Deployment status

**Nothing has been pushed, merged, committed, or deployed. No branch was
created.** All changes are local, uncommitted working-tree changes, per
explicit instruction. No migration has been run against any real/shared
database (local `omeo_hr` or production) — only exercised via the isolated
SQLite test above, plus one aborted attempt at an isolated throwaway MySQL
database that never reached the point of running any migration (the server
crashed first, during its own unrelated startup routine, before my
`CREATE DATABASE` command was received). `git add .`, `commit`, `push`,
`git branch`, and `bash deploy.sh` were **not** run this round.

**Before this is committed**, recommend committing in at least two separate
commits reflecting the two logically independent changes: (1) the Finance
migration idempotency fix, (2) the Sport module itself — per the instruction
not to mix the two in one diff.
