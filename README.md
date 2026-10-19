# Laravel 13 Lab — E-commerce Learning Project

A hands-on lab to learn modern Laravel 13 end-to-end: from dependency
container up to queued payments, structured logging and security hardening.
Small e-commerce domain — products, orders, checkout — used as the vehicle,
every feature tested (92 Pest tests).

**Learning model:** each milestone = one framework concern, implemented for
real, verified by tests, checked in the browser. Journey documented in
[`PROGRESS.md`](PROGRESS.md), security posture in [`AUDIT.md`](AUDIT.md).

## Features by milestone

| # | Milestone | What's inside |
|---|---|---|
| M1 | Architecture | Container binding (interface → singleton), method injection, Collections |
| M2 | Database & Eloquent | Relations, `casts()` method, model events, scopes, JSON columns, race-safe stock via `lockForUpdate`, factories |
| M3 | Auth & Authorization | Session auth (regenerate/invalidate), FormRequest, custom validation rule, `OrderPolicy`, rate limiting, Sanctum tokens |
| M4 | API Layer | Versioned `/api/v1`, API Resources, offset + cursor pagination, transactional checkout with rollback, payment driver via config |
| M5 | Events & Queues | `OrderPlaced` event discovery, queued `ProcessPayment` (retry/backoff/idempotency/`failed()`), queued multi-channel notifications, scheduler |
| M6 | Deep features | Broadcasting (log driver), cache + invalidation, private file storage upload, localization (`?lang=id`) |
| M7 | Security & Logging | Signed URLs, encrypted casts, consistent JSON errors (no internal leaks), JSON log channel + `request_id` correlation |

## Tech stack

- PHP 8.5 · Laravel 13 · Pest · SQLite
- Queue: database driver · Mail: log driver · Broadcast: log driver
- Sanctum personal access tokens for the API
- No Redis/Docker needed — plain `composer install` runs everything

## Getting started

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan test          # 93 tests, all green
php artisan serve         # http://localhost:8000
```

Useful extras:

```bash
php artisan queue:work            # process payments + notifications
php artisan orders:cleanup        # cancel stale pending orders (daily 03:00)
```

Demo login (web): `test@example.com` / `password`.

API quick try:

```bash
curl -X POST http://localhost:8000/api/token \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
# → {"token":"1|..."} → use as Authorization: Bearer on /api/v1/*
```

## Structure

```
app/
├── Console/Commands/   # orders:cleanup (scheduled daily)
├── Events/             # OrderPlaced, StockUpdated (broadcast)
├── Http/
│   ├── Controllers/    # Web + Api/
│   ├── Middleware/     # SetLocale, AssignRequestId, EnsureCartNotEmpty
│   ├── Requests/       # FormRequest validation layer
│   ├── Resources/      # API JSON contracts
│   └── Rules/          # StockAvailable custom rule
├── Jobs/               # ProcessPayment (queued, idempotent)
├── Listeners/          # SendOrderConfirmation (auto-discovered)
├── Notifications/      # OrderConfirmation (mail + database)
├── Policies/           # OrderPolicy ownership rules
└── Services/Payment/   # PaymentGateway interface + fake/http drivers
tests/{Feature,Unit}/   # 92 Pest tests
```

## Frontend (phases F1–F4 — complete)

A Vue 3 SPA (`frontend/`) consuming this API — Composition API + `<script
setup>`, Pinia (cart/auth stores), Vue Router with async guard, Sanctum SPA
cookie auth, and a full TypeScript migration (`vue-tsc --noEmit` clean).
Tests: Vitest + Vue Test Utils (44 tests, jsdom).

## Docs

- [`PROGRESS.md`](PROGRESS.md) — milestone log, session resume guide, open questions
- [`AUDIT.md`](AUDIT.md) — code-review checklist mapped against this codebase
- [`DEBRIEF.md`](DEBRIEF.md) — every milestone's Q&A (architecture, security, async, Vue)
