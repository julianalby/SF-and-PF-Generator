# SF / PF Number System

Internal web app for generating and recording **Service Form (SF)** and
**Project Form (PF)** numbers. Built with Laravel 12 and SQLite — no separate
database server, no Node/npm build step.

## Requirements

- PHP 8.2 or newer, with the `sqlite3`, `mbstring`, `xml`, `curl`, `zip`,
  `bcmath` and `intl` extensions (all standard on most PHP installs)
- Composer 2
- Access to Packagist (the normal `composer install`/`update` — nothing
  project-specific to worry about on a normal machine or server)

## First-time setup

```bash
composer setup
```

This one command runs everything needed: `composer install`, creates `.env`
from `.env.example`, generates the app key, creates the empty SQLite file,
and runs migrations + seeders. Equivalent manual steps, if you'd rather run
them one at a time:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

The seeder creates six accounts (see **Seed data** below). Then start the
app locally with:

```bash
composer dev
# same as: php artisan serve
```

and open `http://127.0.0.1:8000`.

## Seed data

| Username | Role  |
|----------|-------|
| Alida    | User  |
| Windy    | User  |
| Aqiqah   | User  |
| Tohal    | User  |
| Adam     | User  |
| Finance  | Admin |

By default each seeded user gets a **random 16-character password**,
printed once in the terminal when you seed (`php artisan migrate --seed` or
`php artisan db:seed`) — write it down or reset it immediately, since it is
never stored anywhere in plaintext.

For local development only, you can set a single shared password for all
seeded users instead, in `.env`:

```
SEED_USER_PASSWORD=some-dev-password
```

Leave this **empty in production** so real accounts get proper random
passwords.

Re-seeding is safe: it never touches a username that already exists, so it
won't reset passwords or duplicate accounts.

## Managing users afterwards

Everything past the initial seed goes through the **User Management** page
(Admin role, in the nav bar) or these artisan commands:

```bash
php artisan user:create <username> --role=User        # prompts for a password
php artisan user:create <username> --role=Admin --password=...
php artisan user:password <username>                   # reset a password
```

Usernames are case-insensitive ("Windy", "windy", "WINDY" are the same
account) and passwords are always stored as Argon2id hashes.

## How the numbering works

SF numbers start at **26090119**, PF numbers at **26090138** — two
completely independent counters (set once, in `.env`, via `SF_START_NUMBER`
/ `PF_START_NUMBER`; changing them after the app has been used has no
effect, since the running counter in the database is what's authoritative
from then on).

Numbers are handed out by `app/Services/SequenceService.php`: it increments
a counter row as the *first* statement of a database transaction (not a
"read the max and add 1", which two people submitting at the same instant
could both do at once), so two forms submitted in the same instant can never
receive the same number, and a form that fails to save for any reason gives
its number back rather than leaving a gap. Every SF/PF form also carries a
one-time token, so double-clicking submit, or hitting the back button and
resubmitting, safely returns the *same* record instead of creating a
duplicate.

If you ever want to double-check this holds on your own server (different
PHP version, different filesystem, different load), a standalone stress
test is included:

```bash
php scripts/check-concurrency.php            # 8 workers, default
php scripts/check-concurrency.php 20 50 30   # 20 workers, 50 forms each, 30 racing on the same submission
```

It runs entirely against a throwaway temporary database (never your real
one) and reports PASS/FAIL — no duplicates, no gaps, no lost forms.

## Running the tests

```bash
composer test
```

122 automated tests cover authentication, role-based access control, the
numbering guarantees above, form validation, admin pages, and the seeder —
see `tests/Feature/`.

## Deploying

- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env` on the real
  server.
- Serve over HTTPS and set `SESSION_SECURE_COOKIE=true` in `.env`.
- Point your webserver's document root at `public/`, same as any Laravel
  app (or run `php artisan serve` behind a reverse proxy for something
  small/internal).
- Back up `database/database.sqlite` — it's the entire database.
- `composer.lock` is intentionally not included in this handoff (it was
  generated through a network workaround specific to the sandbox this app
  was built in). Running `composer install` once on a normal machine with
  Packagist access will generate a proper one; commit it after that if
  you're putting this under version control.

## Project layout notes

- `app/Services/SequenceService.php` / `FormRecordService.php` — the
  number-generation and idempotency logic described above.
- `app/Models/User.php` — role checks (`isAdmin()`) are case-insensitive
  but fail closed: anything that isn't exactly "Admin" (any case) is
  treated as a normal user.
- No frontend build step: `public/css/app.css` and `public/js/app.js` are
  plain, hand-written files served as-is.
- `resources/views/errors/` — standalone error pages (403/404/419/429/
  500/503) that render without needing a session, so they still work if
  something goes wrong with auth itself.
