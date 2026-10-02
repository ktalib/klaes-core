# Auto Logout & Queue Operations

## Required Artisan Commands

1. `php artisan activity:auto-logout --dry-run`
   - Simulates the auto-logout cycle without changing records.
   - Use this first whenever you tweak shift settings to confirm the evaluation summary.

2. `php artisan activity:auto-logout`
   - Applies the enforcement logic and closes any sessions that exceed their work window.
   - Optional flags:
     - `--limit=25` keeps the scope narrow while testing.
     - `--now="2026-01-10 18:00"` replays the logic against a specific timestamp.

3. `php artisan schedule:run`
   - Manually triggers the scheduler pipeline once. Helpful after deployment to ensure the job is registered correctly.

## Scheduler / Cron Setup

- The command is already scheduled inside `App\Console\Kernel` to run every 10 minutes.
- Production still needs a system-level scheduler:
  - **Linux Cron:** `* * * * * cd /var/www/klaes && php artisan schedule:run >> /var/log/klaes-schedule.log 2>&1`
  - **Windows Task Scheduler:** Create a task that runs every minute and executes `php artisan schedule:run` from the project root.
- Once the system scheduler fires every minute, Laravel will in turn execute `activity:auto-logout` on the defined cadence.

## Queue Processing Overview

- Default queue driver comes from `QUEUE_CONNECTION` (see `config/queue.php`). Set it to `database` in `.env` for production stability.
- Ensure the jobs table exists: `php artisan queue:table` followed by `php artisan migrate` (run once).
- Start workers with `php artisan queue:work --queue=default` (or use `supervisor`/Task Scheduler for persistence).
- Commands like `php artisan attendance:process --queue` will dispatch jobs to the queue instead of doing work inline, keeping long-running tasks off the web request cycle.
- Monitor the `jobs` and `failed_jobs` tables to troubleshoot stuck or failing tasks.
