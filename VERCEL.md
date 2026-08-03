# Vercel deployment

This repository deploys Laravel through a Vercel PHP community runtime and builds frontend assets with Vite.

## Import settings

- Application Preset: `Other`
- Root Directory: `./`
- Install Command: leave the dashboard override disabled; `vercel.json` installs npm dependencies and the PHP runtime installs Composer dependencies
- Build Command: leave the dashboard override disabled; `vercel.json` runs `npm run build`
- Output Directory: leave empty

## Required environment variables

Configure these for Production and Preview in the Vercel project settings:

```dotenv
APP_NAME="M.A Group of Hotels"
APP_ENV=production
APP_KEY=base64:replace-with-a-generated-key
APP_DEBUG=false
APP_URL=https://replace-with-project-domain.vercel.app
LOG_CHANNEL=stderr

DB_CONNECTION=mysql
DB_HOST=replace-with-managed-database-host
DB_PORT=3306
DB_DATABASE=replace-with-database-name
DB_USERNAME=replace-with-database-user
DB_PASSWORD=replace-with-database-password

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

Vercel Functions do not provide a persistent database. Create a managed MySQL database, add its credentials above, and run migrations from a trusted machine:

```powershell
$env:DB_HOST="production-host"
$env:DB_DATABASE="production-database"
$env:DB_USERNAME="production-user"
$env:DB_PASSWORD="production-password"
& "F:\Xampp 8\php\php.exe" artisan migrate --force
```

Review production users before running any seeder. Do not seed default passwords into a public deployment.

## Serverless limitations

- Set `QUEUE_CONNECTION=sync`; a persistent Laravel queue worker cannot run inside a Vercel Function.
- Set `BROADCAST_CONNECTION=log` until Reverb is hosted on a separate persistent service or replaced by a managed WebSocket provider.
- Local uploaded files are not persistent on Vercel. Configure an S3-compatible disk before enabling property media or chat attachments in production.
- Use `LOG_CHANNEL=stderr` so runtime errors appear in Vercel logs.

After the first deployment receives its final domain, update `APP_URL` and redeploy.
