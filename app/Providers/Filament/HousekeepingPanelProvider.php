<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboards\HousekeepingDashboard;
use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Widgets\HotelOverview;
use App\Filament\Widgets\RoomReadinessChart;
use Filament\Panel;

class HousekeepingPanelProvider extends HotelPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configureHotelPanel(
            panel: $panel,
            id: 'housekeeping',
            path: 'housekeeping',
            roleLabel: 'Housekeeping',
            resources: [
                PropertyResource::class,
                RoomResource::class,
                HousekeepingTaskResource::class,
            ],
            pages: [
                HousekeepingDashboard::class,
            ],
            widgets: [
                HotelOverview::class,
                RoomReadinessChart::class,
            ],
            navigationGroups: ['Hotel Operations'],
        );
    }
}
