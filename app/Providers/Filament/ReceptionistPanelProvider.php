<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboards\ReceptionistDashboard;
use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Widgets\HotelOverview;
use App\Filament\Widgets\RecentReservations;
use App\Filament\Widgets\ReservationStatusChart;
use App\Filament\Widgets\RoomReadinessChart;
use Filament\Panel;

class ReceptionistPanelProvider extends HotelPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configureHotelPanel(
            panel: $panel,
            id: 'receptionist',
            path: 'receptionist',
            roleLabel: 'Front Desk',
            resources: [
                PropertyResource::class,
                RoomResource::class,
                ReservationResource::class,
                HousekeepingTaskResource::class,
            ],
            pages: [
                ReceptionistDashboard::class,
            ],
            widgets: [
                HotelOverview::class,
                ReservationStatusChart::class,
                RoomReadinessChart::class,
                RecentReservations::class,
            ],
            navigationGroups: ['Hotel Operations'],
        );
    }
}
