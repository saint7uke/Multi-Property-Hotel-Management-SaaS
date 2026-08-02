<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesHotelDashboard;
use Filament\Widgets\ChartWidget;

class ReservationStatusChart extends ChartWidget
{
    use ScopesHotelDashboard;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Reservation pipeline';

    protected ?string $description = 'Current workload by booking status';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('reservations.view') ?? false;
    }

    protected function getData(): array
    {
        $query = $this->reservationsQuery();
        $statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];

        return [
            'datasets' => [[
                'label' => 'Reservations',
                'data' => collect($statuses)
                    ->map(fn (string $status): int => (clone $query)->where('status', $status)->count())
                    ->all(),
                'backgroundColor' => ['#D97706', '#2563EB', '#0891B2', '#15803D', '#64748B'],
                'borderWidth' => 0,
                'borderRadius' => 5,
            ]],
            'labels' => ['Pending', 'Confirmed', 'Checked in', 'Checked out', 'Cancelled'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.18)'],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
