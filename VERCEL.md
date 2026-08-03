# Vercel deployment

This repository deploys Laravel through a Vercel PHP community runtime and builds frontend assets with Vite.

## Import settings

- Application Preset: `Other`
- Root Directory: `./`
- Install Command: leave the dashboard override disabled; `vercel.json` installs npm dependencies and the PHP runtime installs Composer dependencies
- Build Command: leave the dashboard override disabled; `vercel.json` builds Vite and prepares browser-safe static assets
- Output Directory: leave the dashboard override disabled; `vercel.json` publishes `.vercel-static` without exposing PHP entry points

## Required environment variables

Configure these for Production and Preview in the Vercel project settings. The application accepts the standard Neon `POSTGRES_URL` and prefixed `DATABASE_POSTGRES_URL` variables in addition to `DATABASE_URL`:

```dotenv
APP_NAME="M.A Group of Hotels"
APP_ENV=production
APP_KEY=base64:replace-with-a-generated-key
APP_DEBUG=false
APP_URL=https://replace-with-project-domain.vercel.app
LOG_CHANNEL=stderr

DB_CONNECTION=pgsql
DATABASE_URL=postgresql://replace-with-managed-database-connection-url
DB_SSLMODE=require

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
SESSION_SECURE_COOKIE=true
```

Generate `APP_KEY` locally without changing `.env`:

```powershell
& "F:\Xampp 8\php\php.exe" artisan key:generate --show
```

Do not commit the generated key or production database credentials.

## Database setup

Vercel Functions do not provide a persistent database. Connect a managed Postgres database, add its connection URL above, and run migrations from a trusted machine:

```powershell
$env:DB_CONNECTION="pgsql"
$env:DATABASE_URL="postgresql://production-connection-url"
& "F:\Xampp 8\php\php.exe" artisan migrate --force
```

After migrations, provision the staff users explicitly with a strong temporary password:

```powershell
$env:STAFF_SEED_PASSWORD="replace-with-a-long-temporary-password"
$env:STAFF_SEED_PROPERTY_SLUG="ma-grand-manila"
& "F:\Xampp 8\php\php.exe" artisan staff:provision --password="$env:STAFF_SEED_PASSWORD" --property-slug="$env:STAFF_SEED_PROPERTY_SLUG"
```

Use `STAFF_SEED_RESET_PASSWORDS=true` only when you intentionally want to reset existing demo staff passwords.

## Serverless limitations

- Set `QUEUE_CONNECTION=sync`; a persistent Laravel queue worker cannot run inside a Vercel Function.
- Set `BROADCAST_CONNECTION=log` until Reverb is hosted on a separate persistent service or replaced by a managed WebSocket provider.
- Local uploaded files are not persistent on Vercel. Configure an S3-compatible disk before enabling property media or chat attachments in production.
- Use `LOG_CHANNEL=stderr` so runtime errors appear in Vercel logs.

After the first deployment receives its final domain, update `APP_URL` and redeploy.
