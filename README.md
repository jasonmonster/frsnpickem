# FRSN Pick'em

Private, straight-up NFL pick'em for the Denver Elks Lodge pilot. Plain PHP
(8.1+) and MySQL, no framework, no build step — same philosophy as the
planner tool, upgraded from flat JSON to a real database because this one
has real concurrent writers on Sunday mornings.

Full rules, money mechanics, and the phased build plan live in the FRSN
project doc `frsn-pickem-build-plan.md`. This README is just "how do I run
the thing."

## What's built so far

**Session 1:** Signup (username + PIN, profile fields), login/logout,
profile editing, photo upload with automatic square crop, and the NFL team
reference data (all 32 teams, logos, colors) seeded in. `is_admin` exists on
`participants` but wasn't used by anything yet.

**Session 2:** Weekly game list pulled from ESPN (admin-triggered sync, see
below — no cron locally), pick submission with the two-tier lock (Thursday
tier locks at its own kickoff, everything else locks together at the second
tier's earliest kickoff — assigned by kickoff rank, not literal day-of-week,
since the real NFL schedule doesn't always cooperate), and the MNF/last-game
tiebreaker guess. `is_admin` now gates `/admin/games`.

Not yet built: auto-grading, weekly/season standings, payment log, admin
finalize-week action, email notifications, trash talk. Those are later
sessions per the build plan's phase table.

**Still to validate before real launch:** the actual cron-driven ESPN sync
on the production box, against a live near-term game rather than simulated
timestamps. The lock/grading logic is timestamp-driven and works regardless
of how the game data got there — but "does the scheduled sync actually fire
and hit ESPN correctly on a real clock" needs the real server, once it exists.

**Backlog (flagged, not built):** a manual/backup way to load games or
scores by hand if ESPN's feed ever hiccups — noted in the build plan
(Section 7) as a later-phase safety net.

## Stack

```
index.php          front controller — all routing lives here
router.php          dev-only: routes php -S the same way nginx will
src/                application code, never served directly
  views/            plain-PHP templates, no template engine
migrations/         schema + the bootstrap, seed, promote-admin, and wipe-test-data scripts
storage/avatars/    uploaded profile photos, served through /avatar/{id}, never listed
assets/             CSS + the 32 NFL team logos (std/dark/white cuts)
nginx.conf.example  location blocks for the real server block — paste in, don't replace
```

## First-time setup (local, via Valet + DBngin)

DBngin doesn't ship a `mysql` CLI, so skip the command line entirely and
run the one-shot PHP bootstrap script instead — it creates the database and
app user, loads the schema, seeds the NFL teams (if it finds them at
`~/Sites/frsn/assets/team-logos/`), creates the active season, and writes
`.env`, all in one go:

1. Make sure the MySQL service is actually **started** in DBngin (green
   dot, not the red "Start" button).

2. Run the bootstrap script from the project root:

   ```
   php migrations/000_bootstrap.php
   ```

   DBngin's default root user has no password, so no arguments are needed
   in the common case. If you did set a root password:

   ```
   php migrations/000_bootstrap.php YOUR_ROOT_PASSWORD
   ```

   To bootstrap a season other than 2026, pass the year as a second arg:

   ```
   php migrations/000_bootstrap.php YOUR_ROOT_PASSWORD 2027
   ```

   It's safe to re-run — every step checks for existing state first.

3. Park (or link) the folder with Valet, then `valet secure <sitename>` to
   get a dedicated nginx config file, and paste the deny-rule location
   blocks from `nginx.conf.example` into it — see the "locking it down"
   note below, this part matters.

4. Visit the site, hit `/signup`, confirm you can sign up, log out, and log
   back in with the same username and PIN.

If you'd rather do it by hand (a GUI client like TablePlus, or `brew
install mysql-client` for the CLI), the manual steps are:

```
CREATE DATABASE pickem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pickem'@'localhost' IDENTIFIED BY 'somepassword';
GRANT ALL ON pickem.* TO 'pickem'@'localhost';
```

then copy `.env.example` to `.env` with those credentials, load
`migrations/001_initial_schema.sql`, run
`php migrations/seed_nfl_teams.php ~/Sites/frsn/assets/team-logos/manifest.json ~/Sites/frsn/assets/team-logos/pro`,
and insert the season row yourself.

## Locking it down — don't skip this

Valet's default per-site nginx config funnels every request through its own
front controller, and its `BasicValetDriver` will happily serve a physically
existing file's raw contents when nothing more specific matches — including
`.env`, `src/*.php`, and everything under `migrations/`. `nginx.conf.example`
has the deny rules that stop this, but they only take effect once they're
pasted into a **dedicated per-site nginx file**, which Valet only generates
via `valet secure <sitename>` (plain `park`/`link` alone rides a generic
wildcard config with nowhere to paste custom rules into).

After running `valet secure`, confirm the deny rules actually landed and
took effect:

```
curl -sk -o /dev/null -w "%{http_code}\n" https://pickem.test/.env
curl -sk -o /dev/null -w "%{http_code}\n" https://pickem.test/src/Auth.php
curl -sk -o /dev/null -w "%{http_code}\n" https://pickem.test/migrations/001_initial_schema.sql
```

All three should come back `404`. If any come back `200`, the deny rules
aren't live — run `sudo nginx -t` to check for a config error elsewhere,
then `valet restart`.

One more gotcha we hit: parking `~/Sites/pickem` **as its own directory**
(on top of it already living inside a parked `~/Sites`) makes Valet treat
its subfolders as their own phantom sites — `src.test`, `migrations.test`,
etc., each serving that folder's contents directly. `cd ~/Sites/pickem &&
valet unpark` clears that if `~/.config/valet/config.json`'s `paths` list
ever has `~/Sites/pickem` in it alongside `~/Sites`.

## Admin access

`is_admin` on `participants` doesn't do anything until you set it. Sign up
as normal, then:

```
php migrations/promote_admin.php <username>
```

Admins get an "Admin: sync games" link on the dashboard and can hit
`/admin/games` to pull a week's matchups from ESPN. `--revoke` undoes it.

## Syncing games (admin, on demand — no cron locally)

From `/admin/games`, pick a week and hit "Sync from ESPN." Safe to run
repeatedly — it upserts by ESPN's event id rather than duplicating. In
production this same code path (`Game::syncWeek()`) is what a cron job will
call; the admin button is just the manual trigger for local dev, where
there's no cron daemon running.

## Wiping test data

Preseason had already wrapped by the time picks were built, so there was no
live upcoming game to test the lock/pick flow against — Week 1 of the real
regular season got loaded in as stand-in test data instead. Before opening
real signups, clear it out:

```
php migrations/wipe_test_games.php
```

Deletes games, picks, and tiebreaker answers for the active season only —
participants and payments are untouched. Safe because nothing about a week
is "official" yet (that's Phase 2's finalize action, not built).

## A note on the dev router

`router.php` (used by `php -S`) serves any file that physically exists,
which means it does **not** enforce the `/src/`, `/migrations/`, `/storage/`,
`.env` deny rules — those only exist in `nginx.conf.example`. Fine for
`php -S 127.0.0.1:8811 router.php` on your own machine; never point that
command at anything reachable from outside your Mac. The real deploy (Valet
locally, SpinupWP in production) always goes through nginx with those rules
in place.

## Conventions carried over from the planner

MySQL auto-increment ids instead of the planner's 12-hex ids, but the same
spirit: `created_at`/`updated_at` timestamps, plain PDO, no ORM, add a field
by adding a column and a form input, not by pulling in a migration
framework.
