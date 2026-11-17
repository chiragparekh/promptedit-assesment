# Pixelforge

A Laravel + Inertia + Vue app for submitting image generation prompts, watching them run, and tracking their cost.

## Requirements

- PHP 8.3+
- Node.js 20+ and npm
- Composer

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
```

Start the app:

```bash
composer run dev
```

Then open http://localhost:8000.

## Demo login

The seeder creates one account, ready to use — no registration needed:

| Field    | Value           |
| -------- | --------------- |
| Email    | `demo@acme.com` |
| Password | `password`      |

If you did not seed while migrating, create the demo user at any time:

```bash
php artisan db:seed
```

The seeder is safe to run more than once; it updates the demo user instead of duplicating it.

## What you can do

- Sign in and land on the dashboard.
- Write a prompt, get a cost estimate, and submit a generation.
- Watch generations move from queued to succeeded or failed.
- See the quoted cost next to the actual cost once a generation finishes.

## Tests and checks

```bash
composer test        # Pint lint check, PHPStan, and Pest
npm run types:check  # Vue TypeScript check
```
