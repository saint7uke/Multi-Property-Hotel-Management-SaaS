<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Widgets\Concerns\ScopesHotelDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HotelOverview extends StatsOverviewWidget
{
    use ScopesHotelDashboard;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('dashboard.view') ?? false;
    }

    protected function getStats(): array
    {
        $user = $this->dashboardUser();
        $reservations = $this->reservationsQuery();
        $rooms = $this->roomsQuery();
        $stats = [];

        if ($user?->can('reservations.view')) {
            $arrivals = (clone $reservations)
                ->whereDate('check_in', today())
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->count();
            $departures = (clone $reservations)
                ->whereDate('check_out', today())
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->count();
            $pending = (clone $reservations)->where('status', 'pending')->count();
            $eventRequests = (clone $reservations)
                ->where('status', 'pending')
                ->where('booking_type', 'event')
                ->count();

            $stats[] = Stat::make('Arrivals today', $arrivals)
                ->description($arrivals === 1 ? '1 guest arrival' : "{$arrivals} guest arrivals")
                ->descriptionIcon(Heroicon::OutlinedArrowDownOnSquare)
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('primary')
                ->url(ReservationResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'confirmed']]]));

            $stats[] = Stat::make('Departures today', $departures)
                ->description('Scheduled check-outs')
                ->descriptionIcon(Heroicon::OutlinedArrowUpOnSquare)
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->url(ReservationResource::getUrl('index'));

            $stats[] = Stat::make('Pending requests', $pending)
                ->description($eventRequests === 1 ? '1 event / group request' : "{$eventRequests} event / group requests")
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->icon(Heroicon::OutlinedQueueList)
                ->color($pending > 0 ? 'warning' : 'success')
                ->url(ReservationResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'pending']]]));
        }

        if ($user?->can('rooms.view')) {
            $sellableRooms = (clone $rooms)->where('status', '!=', 'out_of_service')->count();
            $occupiedRooms = (clone $rooms)->where('status', 'occupied')->count();
            $attentionRooms = (clone $rooms)->whereIn('status', ['dirty', 'cleaning'])->count();
            $occupancyRate = $sellableRooms > 0 ? round(($occupiedRooms / $sellableRooms) * 100, 1) : 0;

            $stats[] = Stat::make('Current occupancy', $occupancyRate.'%')
                ->description("{$occupiedRooms} of {$sellableRooms} sellable rooms")
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->icon(Heroicon::OutlinedChartBarSquare)
                ->color('info')
                ->url(RoomResource::getUrl('index'));

            $stats[] = Stat::make('Rooms needing attention', $attentionRooms)
                ->description($attentionRooms > 0 ? 'Dirty or being cleaned' : 'All rooms are ready')
                ->descriptionIcon($attentionRooms > 0 ? Heroicon::OutlinedWrenchScrewdriver : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedSparkles)
                ->color($attentionRooms > 0 ? 'danger' : 'success')
                ->url($user->can('housekeeping.view') ? HousekeepingTaskResource::getUrl('index') : RoomResource::getUrl('index'));
        }

        if ($user?->can('reports.view')) {
            $revenue = $this->paymentsQuery()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');

            $stats[] = Stat::make('Revenue this month', 'PHP '.number_format((float) $revenue, 2))
                ->description(now()->format('F Y').' paid collections')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success');
        }

        return $stats;
    }
}
