# Resort365 — Operations Runbook

How to run Resort365 in production: provisioning a server, deploying, rolling back, backups and restores, monitoring, and the
scheduled jobs. Written for the person on call. Built in Step 7.5; the design is in [ARCHITECTURE.md](ARCHITECTURE.md) §9 and §14.

## 1. What runs where

| Process | How it runs | Notes |
|---|---|---|
| Web (Nginx + PHP-FPM 8.4) | systemd | `deploy/nginx.conf`: the central domain and `*.` tenant subdomains on one wildcard certificate |
| Queues (Horizon) | Supervisor, `deploy/supervisor.conf` | Two supervisors: `default` (60 s jobs) and `long` (exports, up to 30 min) |
| Real time (Reverb) | Supervisor | On 127.0.0.1:8080, proxied by Nginx on `/app` |
| Pulse ingest | Supervisor (`pulse:work`) | With `PULSE_INGEST_DRIVER=redis` |
| Scheduler | cron every minute, `deploy/crontab` | Every task is `onOneServer()`, so several app servers may run cron |
| MySQL 8.4 | systemd | Binary logs on for point-in-time recovery: `deploy/mysql-binlog.cnf` |
| Redis 7 | systemd | Sessions, cache, queues, locks |
| Files | S3-compatible bucket (`ATTACHMENTS_DISK=s3`) | Private, versioning on |
| Backups | `spatie/laravel-backup` → a separate bucket (`BACKUP_DISKS=backups-s3`) | Encrypted archives |

## 2. Provision a server (Ubuntu 24.04)

1. Create the `deploy` user with SSH key access and `sudo` for `systemctl reload php8.4-fpm` only.
2. Install Nginx, PHP 8.4 (fpm, cli, bcmath, intl, mysql, redis, zip, gd, mbstring, xml, curl), MySQL 8.4, Redis 7, Supervisor,
   unzip and the MySQL client.
3. DNS: `resort365.app` and `*.resort365.app` to the server. Wildcard certificate with Certbot's DNS challenge.
4. MySQL: create the database and user; copy `deploy/mysql-binlog.cnf` to `/etc/mysql/mysql.conf.d/zz-resort365.cnf`; restart.
5. Copy `deploy/nginx.conf` (with your domain) to `/etc/nginx/sites-available/resort365`, enable it, `nginx -t`, reload.
6. `mkdir -p /var/www/resort365/shared` and put the production `.env` there, from `.env.production.example` (fill every `<…>`).
   Generate `APP_KEY` once and keep it in the password manager: **encrypted fields cannot be read without it.**
7. First deploy (section 3), then `php artisan permissions:sync` and `php artisan platform:seed-plans` and
   `php artisan platform:create-admin you@example.com "Your Name"` in `/var/www/resort365/current`. At the first sign-in the
   console asks every platform admin to set up two-factor sign-in (required in production); a locked-out admin is reset with
   `php artisan platform:reset-two-factor <email>`.
8. Copy `deploy/supervisor.conf` to `/etc/supervisor/conf.d/resort365.conf`; `supervisorctl reread && supervisorctl update`.
9. `crontab -u deploy deploy/crontab`.
10. Point the uptime monitor at `https://resort365.app/up` (fails when the database or Redis does not answer).

## 3. Deploy

Deploys are zero-downtime (Deployer, `deploy.php`): each release goes to `releases/<n>`, shares `.env` and `storage`, runs its
migrations, then the `current` symlink switches at once, PHP-FPM reloads, and Horizon, Pulse and Reverb restart on the new code.

- **From GitHub:** Actions → *Deploy* → choose staging or production. Pushing a tag `v*` deploys to production. CI (lint, analyse,
  tests) runs first; a red build never deploys. Secrets: `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_PRODUCTION_HOST`,
  `DEPLOY_STAGING_HOST`, `DEPLOY_REPOSITORY`.
- **From a laptop:** `DEPLOY_STAGING_HOST=… vendor/bin/dep deploy staging`.
- **Migrations** must be backward compatible for one release (add columns before using them, drop them a release later), because
  the old release serves requests until the switch.

### Roll back

`vendor/bin/dep rollback production` switches back to the previous release and restarts the processes. It does **not** undo
migrations; if the bad release changed the schema, restore or fix forward.

## 4. Backups

- **Nightly** at 01:15 (`backup:run`): a consistent MySQL dump (single transaction, no table locks) and, when files are local,
  the attachments, zipped and encrypted with `BACKUP_ARCHIVE_PASSWORD`, to the backup bucket. `backup:clean` at 01:00 keeps every
  backup for 7 days, one a day for 30 days, one a week for 8 weeks, one a month for a year.
- **Point-in-time:** MySQL's binary logs (kept 30 days) hold every change after the dump.
- **Checks:** `backup:monitor` at 07:00 mails `BACKUP_NOTIFY_EMAIL` when the newest backup is older than a day or the storage is
  over budget; a failed backup mails at once.
- **Files on S3** are not in the archive; keep versioning on the attachments bucket.

## 5. Restore

### The monthly drill

`php artisan backup:restore-drill` restores the newest backup into the scratch database `resort365_restore_drill`, checks that
every migration and tenant is there and that encrypted guest fields still decrypt with this `APP_KEY`, prints row counts next to
the live ones, and drops the scratch database (`--keep` keeps it). If the app's database user may not create databases, create an empty
`resort365_restore_drill` once and grant the user all rights on it; the drill then fills it and empties it again. Run it on the first Monday of each month, on staging with
production's newest backup copied over, and note the result.

### A real restore

1. `php artisan down` (or point the load balancer at a maintenance page).
2. Create an empty database, then
   `DB_USERNAME=… DB_PASSWORD=… scripts/restore.sh <backup.zip> <database> [--files storage/app/private/attachments]`.
3. **Point in time** (to just before an accident): find the dump's binlog position in the dump header, then
   `mysqlbinlog --start-position=<pos> --stop-datetime="2026-11-03 14:05:00" /var/log/mysql/mysql-bin.0* | mysql <database>`.
4. Set `DB_DATABASE` to the restored database in `shared/.env`, `php artisan migrate --force`, `php artisan optimize`,
   `php artisan up`.

## 6. Monitoring

- **Errors:** Sentry (`SENTRY_LARAVEL_DSN`). Events carry the tenant slug and the user's id, never names, emails or request bodies.
- **Performance:** Pulse at `https://resort365.app/platform/pulse` (slow requests, queries and jobs over 1 s, exceptions, queues,
  servers). Platform admins only.
- **Queues:** Horizon at `https://resort365.app/platform/horizon`. Platform admins only. Failed jobs are listed there; retry or
  forget them.
- **Uptime:** `/up`.
- **Logs:** `shared/storage/logs/laravel-YYYY-MM-DD.log` at warning and above.

## 7. Scheduled jobs

| When | Command | What |
|---|---|---|
| every 5 min | `reservation:expire-holds` | Cancels tentative bookings whose deposit is overdue |
| every 5 min | `frontoffice:night-audit` | Runs each property's night audit at its time |
| every 5 min | `horizon:snapshot` | Queue metrics |
| 00:30 | `platform:billing` | Ends trials, renews subscriptions with invoices, closes cancelled tenants, suspends overdue ones |
| 01:00, 01:15, 07:00 | `backup:clean`, `backup:run`, `backup:monitor` | Backups |
| 01:30 | `hr:grant-leave` | Leave balances |
| 03:30 | `reports:purge-exports`, `core:purge-data-exports` | Deletes export files older than a week |
| 04:00 | `platform:purge-tenants` | Deletes the data of tenants cancelled more than 30 days ago (`--dry-run` to preview) |
| 06:00 | `housekeeping:preventive` | Preventive maintenance work orders |

## 8. Security

- Headers on every response (`App\Http\Middleware\SecurityHeaders`): a Content-Security-Policy with per-request nonces, HSTS in
  production, `nosniff`, `SAMEORIGIN` framing, a strict referrer policy and no camera, microphone or location access. Horizon and
  Pulse pages have no CSP (their own inline scripts) but are for platform admins only.
- The app refuses to start in production with `APP_DEBUG=true`.
- Rate limits: sign-in (5 a minute per person and address), 2FA, platform sign-in, sign-up (10 an hour per address), one-time links,
  invitations, POS and kitchen-display registration, PIN sign-in (locks after five wrong PINs), report screens and exports
  (`reports`, 60 a minute per person), POS and kitchen-display JSON (`device-api`, 600 a minute per device and session).
- Cancelled tenants: data kept 30 days, then purged by `platform:purge-tenants`; the tenant row, its subscriptions, invoices and the
  console's audit log stay.

## 9. Load test

`tests/Load/README.md`: 25 agents booking and 25 cashiers on the POS for five minutes with k6. Run it on staging after any change
to booking, pricing or the POS, and before a large customer goes live.
