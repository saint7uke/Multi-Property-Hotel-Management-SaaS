<?php

namespace App\Filament\Pages\Dashboards;

use Filament\Pages\Dashboard;

class SystemAdminDashboard extends Dashboard
{
    protected static ?string $title = 'System overview';

    public function getSubheading(): ?string
    {
        return 'Portfolio operations, revenue, staff, and platform activity';
    }
}
