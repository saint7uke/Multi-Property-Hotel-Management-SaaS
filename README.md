# M.A Group of Hotels

Hotel management and booking platform for M&A Grand Manila, M&A Skyline Cebu, and M&A Skyline Singapore.

## What is implemented

- Laravel 12 API backend with Sanctum SPA session auth.
- Spatie Laravel Permission roles for `admin`, `manager`, `receptionist`, and `housekeeping`.
- Public Blade site with Home, About, Journal, Contact, booking request, booking lookup, and review submission.
- React + TypeScript staff portal at `/staff`.
- Server-paginated reservation and room APIs.
- Reset-safe create/edit modals in the staff portal.
- Housekeeping room readiness board.
- Review moderation.
- Manager/admin reporting summary plus CSV and Excel reservation exports.
- Audit logging for sensitive state changes.
- Tailwind v4 theme using the requested M.A palette and local Fraunces font package.

## Local demo

The current `.env` uses local MySQL so you can manage the data in phpMyAdmin.

```powershell
& "F:\Xampp 8\php\php.exe" -d extension=zip -d extension=gd artisan migrate:fresh --seed
npm.cmd run build
& "F:\Xampp 8\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

Open:

- Public site: `http://127.0.0.1:8000/`
- Staff portal: `http://127.0.0.1:8000/staff`

Local database tools:

- phpMyAdmin: `http://localhost/phpmyadmin`
- Database name: `ma_hotels`
- MySQL user: `root`
- MySQL password: blank unless you set one in XAMPP

Seeded staff accounts all use password `password`:

- `admin@mahotels.test`
- `manager@mahotels.test`
- `reception@mahotels.test`
- `housekeeping@mahotels.test`

## Production notes

For MySQL, update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ma_hotels
DB_USERNAME=root
DB_PASSWORD=
```

The architecture prompt still has these open decisions:

- Card payment provider: PayMongo, Stripe, or another PH-compatible gateway.
- Housekeeping sync: polling or Laravel Reverb/websockets.
- Reservation confirmation: auto-confirm after paid payment or always staff-reviewed.
- Staff property scope: strictly one property or multi-property assignment.

## Verification

```powershell
& "F:\Xampp 8\php\php.exe" -d extension=zip -d extension=gd artisan test
npm.cmd run build
```
