<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class Reports extends Page
{
    use ScopesPropertyAccess;

    protected string $view = 'filament.pages.reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 20;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'property_id')]
    public string $propertyId = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->endOfMonth()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return $user?->hasRole('admin')
            ? 'Portfolio performance across hotel properties'
            : 'Performance for '.($user?->property?->name ?? 'your assigned property');
    }

    public function resetFilters(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
        $this->propertyId = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => $this->exportUrl('csv'))
                ->visible(fn (): bool => auth()->user()?->can('reports.export') ?? false),
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->url(fn (): string => $this->exportUrl('xlsx'))
                ->visible(fn (): bool => auth()->user()?->can('reports.export') ?? false),
        ];
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->reportDates();
        $propertyId = $this->authorizedPropertyId();
        $user = auth()->user();

        $reservationsQuery = Reservation::query()->with(['guest', 'property', 'room.property']);
        $this->scopeReservationsFor($user, $reservationsQuery);
        $this->applyStayFilters($reservationsQuery, $from, $to, $propertyId);
        $reservations = $reservationsQuery->latest()->get();

        $roomsQuery = Room::query();
        $this->scopeRoomsFor($user, $roomsQuery);
        $roomsQuery->when($propertyId, fn (Builder $query, int $id) => $query->where('property_id', $id));

        $paymentsQuery = Payment::query()
            ->with(['reservation.property', 'reservation.room.property'])
            ->where(function (Builder $query) use ($from, $to): void {
                $query->where(function (Builder $paid) use ($from, $to): void {
                    $paid->where('status', 'paid')
                        ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()]);
                })->orWhere(function (Builder $refunded) use ($from, $to): void {
                    $refunded->where('status', 'refunded')
                        ->whereBetween('refunded_at', [$from->startOfDay(), $to->endOfDay()]);
                });
            });
        $this->scopePaymentReservations($paymentsQuery, $propertyId);
        $payments = $paymentsQuery->get();

        $periodNights = max(1, $from->diffInDays($to->addDay()));
        $sellableRooms = (clone $roomsQuery)->where('status', '!=', 'out_of_service')->count();
        $soldRoomNights = $this->soldRoomNights($reservations, $from, $to);
        $availableRoomNights = $sellableRooms * $periodNights;
        $revenue = (float) $payments->sum(fn (Payment $payment): float => $payment->status === 'refunded'
            ? -1 * (float) $payment->amount
            : (float) $payment->amount);
        $occupancy = $availableRoomNights > 0
            ? min(100, round(($soldRoomNights / $availableRoomNights) * 100, 1))
            : 0;

        return [
            'propertyOptions' => $this->propertyOptions(),
            'periodLabel' => $from->format('M j, Y').' - '.$to->format('M j, Y'),
            'summary' => [
                'bookings' => $reservations->count(),
                'personal' => $reservations->where('booking_type', 'personal')->count(),
                'events' => $reservations->where('booking_type', 'event')->count(),
                'revenue' => $revenue,
                'occupancy' => $occupancy,
                'sold_room_nights' => $soldRoomNights,
                'available_room_nights' => $availableRoomNights,
                'adr' => $soldRoomNights > 0 ? $revenue / $soldRoomNights : 0,
                'pending' => $reservations->where('status', 'pending')->count(),
            ],
            'statusBreakdown' => $this->statusBreakdown($reservations),
            'bookingMix' => $this->bookingMix($reservations),
            'arrivalTrend' => $this->arrivalTrend($reservations, $from, $to),
            'propertyPerformance' => $this->propertyPerformance($reservations, $payments, $from, $to, $propertyId),
            'recentReservations' => $reservations->take(8),
        ];
    }

    private function reportDates(): array
    {
        try {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $this->from)->startOfDay();
        } catch (\Throwable) {
            $from = CarbonImmutable::now()->startOfMonth();
        }

        try {
            $to = CarbonImmutable::createFromFormat('Y-m-d', $this->to)->startOfDay();
        } catch (\Throwable) {
            $to = $from->endOfMonth()->startOfDay();
        }

        if ($to->lessThan($from)) {
            $to = $from;
        }

        if ($from->diffInDays($to) > 366) {
            $to = $from->addDays(366);
        }

        return [$from, $to];
    }

    private function authorizedPropertyId(): ?int
    {
        $propertyId = filter_var($this->propertyId, FILTER_VALIDATE_INT) ?: null;
        $user = auth()->user();

        if (! $propertyId) {
            return null;
        }

        if ($user?->hasRole('admin')) {
            return Property::query()->whereKey($propertyId)->exists() ? $propertyId : null;
        }

        return (int) $user?->property_id === $propertyId ? $propertyId : null;
    }

    private function propertyOptions(): Collection
    {
        $query = Property::query()->where('status', 'active')->orderBy('name');
        $user = auth()->user();

        if (! $user?->hasRole('admin')) {
            $query->whereKey($user?->property_id ?? 0);
        }

        return $query->pluck('name', 'id');
    }

    private function applyStayFilters(Builder $query, CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId): void
    {
        $query
            ->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>', $from->toDateString())
            ->when($propertyId, function (Builder $query, int $id): void {
                $query->where(function (Builder $reservations) use ($id): void {
                    $reservations->where('property_id', $id)
                        ->orWhereHas('room', fn (Builder $room) => $room->where('property_id', $id));
                });
            });
    }

    private function scopePaymentReservations(Builder $query, ?int $propertyId): void
    {
        $user = auth()->user();

        $query->whereHas('reservation', function (Builder $reservations) use ($user, $propertyId): void {
            $this->scopeReservationsFor($user, $reservations);

            if ($propertyId) {
                $reservations->where(function (Builder $query) use ($propertyId): void {
                    $query->where('property_id', $propertyId)
                        ->orWhereHas('room', fn (Builder $room) => $room->where('property_id', $propertyId));
                });
            }
        });
    }

    private function soldRoomNights(Collection $reservations, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $endExclusive = $to->addDay();

        return $reservations
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereNotNull('room_id')
            ->sum(function (Reservation $reservation) use ($from, $endExclusive): int {
                $start = CarbonImmutable::parse($reservation->check_in)->max($from);
                $end = CarbonImmutable::parse($reservation->check_out)->min($endExclusive);

                return $end->greaterThan($start) ? $start->diffInDays($end) : 0;
            });
    }

    private function statusBreakdown(Collection $reservations): array
    {
        return collect([
            'pending' => ['label' => 'Pending', 'tone' => 'warning'],
            'confirmed' => ['label' => 'Confirmed', 'tone' => 'info'],
            'checked_in' => ['label' => 'Checked in', 'tone' => 'primary'],
            'checked_out' => ['label' => 'Checked out', 'tone' => 'success'],
            'cancelled' => ['label' => 'Cancelled', 'tone' => 'gray'],
        ])->map(function (array $meta, string $status) use ($reservations): array {
            return $meta + ['value' => $reservations->where('status', $status)->count()];
        })->values()->all();
    }

    private function bookingMix(Collection $reservations): array
    {
        return [
            ['label' => 'Personal / Room', 'tone' => 'primary', 'value' => $reservations->where('booking_type', 'personal')->count()],
            ['label' => 'Event / Group', 'tone' => 'info', 'value' => $reservations->where('booking_type', 'event')->count()],
        ];
    }

    private function arrivalTrend(Collection $reservations, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $daily = $from->diffInDays($to) <= 45;
        $buckets = [];

        foreach ($reservations as $reservation) {
            $arrival = CarbonImmutable::parse($reservation->check_in);
            $key = $daily ? $arrival->toDateString() : $arrival->startOfWeek()->toDateString();
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        $trend = [];
        $cursor = $daily ? $from : $from->startOfWeek();
        $end = $daily ? $to : $to->startOfWeek();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $trend[] = [
                'label' => $daily ? $cursor->format('M j') : 'Week of '.$cursor->format('M j'),
                'short_label' => $daily ? $cursor->format('j') : $cursor->format('M j'),
                'value' => $buckets[$key] ?? 0,
            ];
            $cursor = $daily ? $cursor->addDay() : $cursor->addWeek();
        }

        return $trend;
    }

    private function propertyPerformance(Collection $reservations, Collection $payments, CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId): Collection
    {
        $properties = Property::query()
            ->where('status', 'active')
            ->when($propertyId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when(! auth()->user()?->hasRole('admin'), fn (Builder $query) => $query->whereKey(auth()->user()?->property_id ?? 0))
            ->withCount(['rooms as sellable_rooms_count' => fn (Builder $query) => $query->where('status', '!=', 'out_of_service')])
            ->orderBy('name')
            ->get();
        $periodNights = max(1, $from->diffInDays($to->addDay()));

        return $properties->map(function (Property $property) use ($reservations, $payments, $from, $to, $periodNights): array {
            $propertyReservations = $reservations->filter(fn (Reservation $reservation): bool => (int) ($reservation->property_id ?? $reservation->room?->property_id) === $property->id);
            $soldNights = $this->soldRoomNights($propertyReservations, $from, $to);
            $capacity = $property->sellable_rooms_count * $periodNights;
            $revenue = $payments
                ->filter(fn (Payment $payment): bool => (int) ($payment->reservation?->property_id ?? $payment->reservation?->room?->property_id) === $property->id)
                ->sum(fn (Payment $payment): float => $payment->status === 'refunded'
                    ? -1 * (float) $payment->amount
                    : (float) $payment->amount);

            return [
                'name' => $property->name,
                'bookings' => $propertyReservations->count(),
                'sold_nights' => $soldNights,
                'occupancy' => $capacity > 0 ? min(100, round(($soldNights / $capacity) * 100, 1)) : 0,
                'revenue' => (float) $revenue,
            ];
        });
    }

    private function exportUrl(string $format): string
    {
        [$from, $to] = $this->reportDates();
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'admin';
        $query = array_filter([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'property_id' => $this->authorizedPropertyId(),
        ], fn ($value): bool => filled($value));

        return url("/{$panelId}/reports/reservations.{$format}").'?'.http_build_query($query);
    }
}
