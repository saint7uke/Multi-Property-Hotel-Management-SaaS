<?php

namespace App\Filament\Pages\Dashboards;

use Filament\Pages\Dashboard;

class HousekeepingDashboard extends Dashboard
{
    protected static ?string $title = 'Housekeeping board';

    public function getSubheading(): ?string
    {
        return 'Room readiness and cleaning workload for '.(auth()->user()?->property?->name ?? 'your property');
    }
}
