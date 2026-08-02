<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboards\HotelManagerDashboard;
use App\Filament\Pages\Reports;
use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\HotelOverview;
use App\Filament\Widgets\RecentReservations;
use App\Filament\Widgets\ReservationStatusChart;
use App\Filament\Widgets\RoomReadinessChart;
use Filament\Panel;

class ManagerPanelProvider extends HotelPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configureHotelPanel(
            panel: $panel,
            id: 'manager',
            path: 'manager',
            roleLabel: 'Hotel Management',
            resources: [
                PropertyResource::class,
                RoomResource::class,
                ReservationResource::class,
                HousekeepingTaskResource::class,
                ReviewResource::class,
                PaymentResource::class,
                UserResource::class,
            ],
            pages: [
                HotelManagerDashboard::class,
                Reports::class,
            ],
            widgets: [
                HotelOverview::class,
                ReservationStatusChart::class,
                RoomReadinessChart::class,
                RecentReservations::class,
            ],
            navigationGroups: ['Hotel Operations', 'Guest Experience', 'Finance', 'Administration'],
        );
    }
}
