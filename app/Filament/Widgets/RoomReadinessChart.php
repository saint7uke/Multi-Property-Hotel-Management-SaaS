<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesHotelDashboard;
use Filament\Widgets\ChartWidget;

class RoomReadinessChart extends ChartWidget
{
    use ScopesHotelDashboard;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Room readiness';

    protected ?string $description = 'Live room inventory by operating state';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('rooms.view') ?? false;
    }

    protected function getData(): array
    {
        $query = $this->roomsQuery();

        return [
            'datasets' => [[
                'label' => 'Rooms',
                'data' => [
                    (clone $query)->whereIn('status', ['available', 'ready', 'clean', 'inspected'])->count(),
                    (clone $query)->where('status', 'occupied')->count(),
                    (clone $query)->whereIn('status', ['dirty', 'cleaning'])->count(),
                    (clone $query)->where('status', 'out_of_service')->count(),
                ],
                'backgroundColor' => ['#15803D', '#2563EB', '#D97706', '#64748B'],
                'borderWidth' => 0,
                'hoverOffset' => 4,
            ]],
            'labels' => ['Ready', 'Occupied', 'Needs attention', 'Out of service'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '68%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 8,
                        'padding' => 16,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
