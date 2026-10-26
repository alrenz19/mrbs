# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A heavily customized fork of MRBS (Meeting Room Booking System, plain PHP ≥ 7.2, MySQL/PostgreSQL, no framework, no build step, no test suite). Upstream docs live in `README`, `INSTALL`, `AUTHENTICATION`, `UPGRADE`, `README.sqlapi`. The site-specific additions (participants, "prepare" items, representatives/groups, guest booking, kiosk/badge APIs, email queue, web-push reminders) are layered directly into upstream files — there is no clean separation, so diff against upstream behaviour carefully.

## Running locally

- Local dev uses Laragon: the app is served from `web/` at `http://localhost/mrbs/web/`. Edits take effect on page refresh.
- `web/config.inc.php` is gitignored and site-specific; `docker-config.inc.php` is the env-driven template (`MRBS_DB_HOST`, `MRBS_DB_DATABASE`, `MRBS_TIMEZONE`, …).
- Schema: `tables.my.sql` / `tables.pg.sql` (upstream). `mrbs (1).sql` is a full MySQL dump including the custom tables (`mrbs_participants`, plus `prepare`/`groups` referenced via `_tbl()`). Upstream's `upgrade.inc`/`web/upgrade/` were removed, yet `web/defaultincludes.inc` still `require`s `upgrade.inc` — check this before assuming a fresh checkout boots.
- Docker alternatives: `docker_app/` (`docker-compose up -d` → app on :8080, phpMyAdmin on :8888, see `docker_app/HOWTO.md`); root `docker-compose.yml` only runs the push-notification daemon.
- Lint a file: `php -l web/<file>.php`. Language-file consistency: `php checklang.php`.

## Architecture

- **Entry points** are the flat `web/*.php` pages (`index.php` calendar views, `edit_entry.php` → `edit_entry_handler.php`, `del_entry.php`, `admin.php`, `report.php`, `search.php`, `kiosk.php`, …) plus `web/ajax/*.php`. Every page starts with `namespace MRBS;` and `require "defaultincludes.inc";`, which loads config in this order: `systemdefaults.inc.php` → `areadefaults.inc.php` → `config.inc.php` → `site_config.inc` → `internalconfig.inc.php`, then functions, DB, auth, session. Never edit `systemdefaults.inc.php` to change settings; override in `config.inc.php`.
- **Library code** is namespaced `MRBS\` under `web/lib/MRBS/` and autoloaded by `web/lib/autoload.inc` (PSR-4-ish: `MRBS\Form\Form` → `lib/MRBS/Form/Form.php`). Auth backends (`Auth/Auth*.php`) and session schemes (`Session/Session*.php`) are chosen by `$auth['type']` / `$auth['session']` via the factory classes. Forms are built with the fluent `MRBS\Form\*` classes, not raw HTML.
- **Database**: always use `db()` (from `dbsys.inc`) with `?` placeholders — `db()->query($sql, $params)`, `db()->query1(...)`, `db()->command(...)`. Table names go through `_tbl('entry')` so the configured prefix (`mrbs_`) applies. Some custom pages (`guest_booking.php`, `badge_api.php`) use MySQL-only SQL with hardcoded `mrbs_` names.
- **Strings** come from `get_vocab('key')` backed by `web/lang/lang.*`; add new keys to `lang.en` at minimum.
- **Themes/CSS**: `web/Themes/<name>/`, `web/css/`; `web/css/custom.css` is gitignored (local overrides).
- **Composer** dependencies are vendored and committed: `web/lib/vendor` (PHPMailer, phpdotenv) and `web/push-notification/vendor` (minishlink/web-push). Run `composer install` inside the respective directory if you change `composer.json`.

### Custom email pipeline (participant notifications)

1. `edit_entry_handler.php` / `del_entry.php` call `addToEmailQueue()`, which writes a JSON job to `web/temp/email_queue/email_job_<entryId>_<ts>.json` (no DB, no blocking send).
2. `background_email.php` (session-less, `MRBS_NO_SESSION`) processes queued jobs and sends via `web/Mail/participantsNotif.php` (`MRBS\Email`, PHPMailer over Office 365 SMTP; it opens its own PDO connection rather than using `db()`). Its SMTP and DB credentials come from the gitignored repo-root `.env` (template: `.env.example`), loaded with phpdotenv.
3. On the Windows production server (`D:\wwwroot\mrbs\web`, `D:\PHP\php.exe`) the `*.bat` files / `nssm.exe` / `task_*.xml` set up a loop running `background_email.php` every 5 s as a scheduled task/service. Logs: `web/logs/*.log`, `web/email_output.log`. `queue_manager.php` is a web status page for the queue.

Upstream MRBS mail (`MailQueue.php`, `functions_mail.inc`, flushed on shutdown) still exists for its own notifications; the two systems are independent.

### Push notifications

`web/push-notification/` is a separate mini-app: `server/daemon_meetings.php` (long-running, run via root `docker-compose.yml`) sends meeting reminders using VAPID keys in `vapid/` and subscriptions stored in `server/subscription.json`; `sw.js`/`main.js` are the browser side.

## Conventions

- `.editorconfig`: 2-space indent, LF; `.ics` files need CRLF.
- Committed log files (`web/logs/`, `web/*.log`) churn constantly — don't include them in commits unless asked.
