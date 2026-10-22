# Load test (Step 7.5)

50 people at once: 25 reservations agents booking through the wizard (with availability and the tape chart) and 25 cashiers taking
takeaway orders on the POS (lines, kitchen tickets, bill, cash, floor polling), for five minutes.

**Passes when** there are no server errors (5xx), every check passes, and 95% of requests answer within 800 ms. A room another agent
booked a moment earlier, or a full date, counts as a refusal (shown in the summary), not an error.

## Run it locally

```bash
brew install k6                      # once
php artisan migrate:fresh --seed     # the demo data (wipes the local database)
php tests/Load/prepare.php           # 25 booking users, 25 cashiers with PINs, ids → tests/Load/fixture.json
php artisan queue:work &             # broadcasts and emails are queued
k6 run tests/Load/run.js
```

Herd's PHP-FPM serves a few requests at a time by default; for 50 users raise it (Herd → PHP → `pm.max_children` 32 or more) or the
test measures the queue in front of PHP rather than the application.

Options: `USERS_PER_SCENARIO=10`, `DURATION=1m`, `P95_MS=1200`, `BASE_URL=https://rodela.staging.example`.

## On staging

Seed the demo tenant (or a copy of it), run `php tests/Load/prepare.php` there, copy `tests/Load/fixture.json` back, then run with
`BASE_URL` set. Local timings are not production timings; the staging run is the one that counts.
