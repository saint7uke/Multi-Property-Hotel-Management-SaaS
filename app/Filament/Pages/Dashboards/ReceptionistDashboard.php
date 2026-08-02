<?php

namespace App\Filament\Pages\Dashboards;

use Filament\Pages\Dashboard;

class ReceptionistDashboard extends Dashboard
{
    protected static ?string $title = 'Front desk';

    public function getSubheading(): ?string
    {
        return 'Today\'s arrivals, departures, reservations, and room readiness';
    }
}
