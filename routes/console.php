<?php

use Database\Seeders\StaffAccountsSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('staff:provision {--password=} {--property-slug=ma-grand-manila} {--reset-passwords}', function () {
    $password = (string) $this->option('password');

    if ($password === '') {
        $password = (string) env('STAFF_SEED_PASSWORD', app()->environment('production') ? '' : 'password');
    }

    if ($password === '') {
        $this->error('Set --password or STAFF_SEED_PASSWORD before provisioning staff accounts.');

        return 1;
    }

    app(StaffAccountsSeeder::class)->seedAccounts(
        $password,
        (string) $this->option('property-slug'),
        (bool) $this->option('reset-passwords'),
    );

    $this->info('Staff accounts provisioned successfully.');

    return 0;
})->purpose('Provision the default staff accounts safely');
