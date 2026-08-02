<?php

namespace App\Filament\Pages\Dashboards;

use Filament\Pages\Dashboard;

class HotelManagerDashboard extends Dashboard
{
    protected static ?string $title = 'Property overview';

    public function getSubheading(): ?string
    {
        return auth()->user()?->property?->name.' operations and performance';
    }
}
