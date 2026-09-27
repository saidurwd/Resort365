# Resort365

Multi-tenant SaaS resort management system: booking engine, front office, billing, restaurant POS, housekeeping, inventory, procurement, accounting, HR and payroll.

- Architecture & requirements: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Development plan & progress: [docs/DEVELOPMENT_PLAN.md](docs/DEVELOPMENT_PLAN.md)
- Conventions for contributors and Claude Code: [CLAUDE.md](CLAUDE.md)

## Requirements

PHP 8.4, Composer, Node 22, MySQL 8.4, Redis 7. Locally, [Laravel Herd](https://herd.laravel.com) serves the app at `http://resort365.test`.

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

Create the `resort365` and `resort365_testing` databases first. The default `.env.example` expects MySQL on port 3307.

## Quality checks

```sh
composer lint      # Pint + Rector (check only)
composer analyse   # Larastan level 6
composer test      # Pest
composer fix       # apply Rector + Pint
```
