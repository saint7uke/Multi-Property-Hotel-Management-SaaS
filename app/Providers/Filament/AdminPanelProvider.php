<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboards\SystemAdminDashboard;
use App\Filament\Pages\Reports;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\ContactInquiries\ContactInquiryResource;
use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Filament\Resources\NewsletterSubscriptions\NewsletterSubscriptionResource;
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

class AdminPanelProvider extends HotelPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configureHotelPanel(
            panel: $panel,
            id: 'admin',
            path: 'admin',
            roleLabel: 'System Administration',
            resources: [
                PropertyResource::class,
                RoomResource::class,
                ReservationResource::class,
                HousekeepingTaskResource::class,
                ReviewResource::class,
                ContactInquiryResource::class,
                NewsletterSubscriptionResource::class,
                PaymentResource::class,
                UserResource::class,
                AuditLogResource::class,
            ],
            pages: [
                SystemAdminDashboard::class,
                Reports::class,
            ],
            widgets: [
                HotelOverview::class,
                ReservationStatusChart::class,
                RoomReadinessChart::class,
                RecentReservations::class,
            ],
            navigationGroups: ['Hotel Operations', 'Guest Experience', 'Finance', 'Administration'],
        )->default();
    }
}
