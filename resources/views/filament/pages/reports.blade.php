<x-filament-panels::page class="ma-responsive-report">
    <style>
        .ma-report-number { font-variant-numeric: tabular-nums; }
        .ma-report-kpi { min-height: 132px; }
        .ma-report-kpi-value { margin-top: .45rem; font-size: 1.75rem; line-height: 1.1; font-weight: 650; letter-spacing: 0; }
        .ma-report-muted { color: rgb(100 116 139); }
        .dark .ma-report-muted { color: rgb(148 163 184); }
        .ma-report-track { height: .55rem; overflow: hidden; border-radius: .25rem; background: rgb(226 232 240); }
        .dark .ma-report-track { background: rgb(51 65 85); }
        .ma-report-fill { height: 100%; min-width: 0; border-radius: .25rem; }
        .ma-tone-primary { background: rgb(180 125 20); }
        .ma-tone-info { background: rgb(37 99 235); }
        .ma-tone-success { background: rgb(21 128 61); }
        .ma-tone-warning { background: rgb(217 119 6); }
        .ma-tone-gray { background: rgb(100 116 139); }
        .ma-report-trend { display: flex; height: 170px; align-items: end; gap: .35rem; overflow-x: auto; padding-top: 1rem; }
        .ma-report-trend-item { display: flex; min-width: 1.75rem; flex: 1 0 1.75rem; height: 100%; flex-direction: column; justify-content: end; align-items: center; gap: .4rem; }
        .ma-report-trend-bar { width: 100%; max-width: 2.5rem; min-height: 2px; border-radius: .25rem .25rem 0 0; background: rgb(180 125 20); }
        .ma-report-table-wrap { max-width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; -webkit-overflow-scrolling: touch; }
        .ma-report-table { width: 100%; min-width: 720px; border-collapse: collapse; }
        .ma-report-table th { padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600; color: rgb(100 116 139); }
        .ma-report-table td { border-top: 1px solid rgb(226 232 240); padding: .8rem 1rem; font-size: .875rem; }
        .dark .ma-report-table td { border-color: rgb(51 65 85); }
        .dark .ma-report-table th { color: rgb(148 163 184); }
        .ma-report-badge { display: inline-flex; align-items: center; border-radius: .25rem; padding: .2rem .5rem; font-size: .75rem; font-weight: 600; background: rgb(241 245 249); color: rgb(51 65 85); }
        .dark .ma-report-badge { background: rgb(51 65 85); color: rgb(226 232 240); }
        @media (max-width: 639px) {
            .ma-responsive-report { min-width: 0; }
            .ma-report-kpi { min-height: 0; }
            .ma-report-kpi-value { font-size: clamp(1.35rem, 7vw, 1.75rem); overflow-wrap: anywhere; }
            .ma-report-trend { height: 145px; padding-bottom: .35rem; }
            .ma-report-table { min-width: 640px; }
            .ma-report-table th, .ma-report-table td { padding: .65rem .75rem; white-space: nowrap; }
        }
    </style>

    <x-filament::section compact>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="grid gap-1.5">
                <span class="text-sm font-medium">Stay date from</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live.blur="from" max="{{ $to }}" />
                </x-filament::input.wrapper>
            </label>

            <label class="grid gap-1.5">
                <span class="text-sm font-medium">Stay date to</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live.blur="to" min="{{ $from }}" />
                </x-filament::input.wrapper>
            </label>

            @if (auth()->user()->hasRole('admin'))
                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Property</span>
                    <x-filament::input.wrapper>
                        <select wire:model.live="propertyId" class="w-full border-0 bg-transparent py-1.5 text-sm outline-none dark:text-white">
                            <option value="">All properties</option>
                            @foreach ($propertyOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>
                </label>
            @else
                <div class="grid content-start gap-1.5">
                    <span class="text-sm font-medium">Property</span>
                    <div class="flex min-h-10 items-center text-sm ma-report-muted">{{ auth()->user()->property?->name ?? 'No property assigned' }}</div>
                </div>
            @endif

            <div class="flex items-end justify-between gap-3">
                <div>
                    <div class="text-xs font-medium uppercase ma-report-muted">Reporting period</div>
                    <div class="mt-1 text-sm font-semibold">{{ $periodLabel }}</div>
                </div>
                <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="resetFilters">
                    Reset
                </x-filament::button>
            </div>
        </div>
        <div wire:loading.delay class="mt-3 text-sm ma-report-muted" role="status">Updating report...</div>
    </x-filament::section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section class="ma-report-kpi">
            <div class="text-sm font-medium ma-report-muted">Bookings in period</div>
            <div class="ma-report-kpi-value ma-report-number">{{ number_format($summary['bookings']) }}</div>
            <div class="mt-2 text-sm ma-report-muted">{{ $summary['personal'] }} personal, {{ $summary['events'] }} event / group</div>
        </x-filament::section>
        <x-filament::section class="ma-report-kpi">
            <div class="text-sm font-medium ma-report-muted">Net collected revenue</div>
            <div class="ma-report-kpi-value ma-report-number">PHP {{ number_format($summary['revenue'], 2) }}</div>
            <div class="mt-2 text-sm ma-report-muted">Verified payments less refunds processed during the period</div>
        </x-filament::section>
        <x-filament::section class="ma-report-kpi">
            <div class="text-sm font-medium ma-report-muted">Room-night occupancy</div>
            <div class="ma-report-kpi-value ma-report-number">{{ number_format($summary['occupancy'], 1) }}%</div>
            <div class="mt-2 text-sm ma-report-muted">{{ $summary['sold_room_nights'] }} of {{ $summary['available_room_nights'] }} room nights</div>
        </x-filament::section>
        <x-filament::section class="ma-report-kpi">
            <div class="text-sm font-medium ma-report-muted">Average daily rate</div>
            <div class="ma-report-kpi-value ma-report-number">PHP {{ number_format($summary['adr'], 2) }}</div>
            <div class="mt-2 text-sm ma-report-muted">Net collected revenue per sold room night</div>
        </x-filament::section>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Reservation status</x-slot>
            <x-slot name="description">Booking workload within the selected stay dates</x-slot>
            @php($statusMax = max(1, collect($statusBreakdown)->max('value')))
            <div class="grid gap-4">
                @foreach ($statusBreakdown as $item)
                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span>{{ $item['label'] }}</span>
                            <span class="font-semibold ma-report-number">{{ number_format($item['value']) }}</span>
                        </div>
                        <div class="ma-report-track" role="img" aria-label="{{ $item['label'] }}: {{ $item['value'] }} reservations">
                            <div class="ma-report-fill ma-tone-{{ $item['tone'] }}" style="width: {{ ($item['value'] / $statusMax) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Booking mix</x-slot>
            <x-slot name="description">Personal stays compared with event and group requests</x-slot>
            @php($mixTotal = max(1, collect($bookingMix)->sum('value')))
            <div class="grid gap-5">
                @foreach ($bookingMix as $item)
                    @php($percentage = round(($item['value'] / $mixTotal) * 100, 1))
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium">{{ $item['label'] }}</div>
                                <div class="text-xs ma-report-muted">{{ $percentage }}% of selected bookings</div>
                            </div>
                            <span class="text-lg font-semibold ma-report-number">{{ number_format($item['value']) }}</span>
                        </div>
                        <div class="ma-report-track" role="img" aria-label="{{ $item['label'] }}: {{ $item['value'] }} bookings, {{ $percentage }} percent">
                            <div class="ma-report-fill ma-tone-{{ $item['tone'] }}" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
                @if ($summary['pending'] > 0)
                    <div class="border-t pt-4 text-sm dark:border-gray-700">
                        <span class="font-semibold ma-report-number">{{ $summary['pending'] }}</span>
                        pending {{ str('reservation')->plural($summary['pending']) }} require follow-up.
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Arrival trend</x-slot>
        <x-slot name="description">Scheduled arrivals by {{ count($arrivalTrend) > 46 ? 'week' : 'day' }}</x-slot>
        @php($trendMax = max(1, collect($arrivalTrend)->max('value')))
        <div class="ma-report-trend" role="img" aria-label="Arrival volume during {{ $periodLabel }}">
            @foreach ($arrivalTrend as $point)
                <div class="ma-report-trend-item" title="{{ $point['label'] }}: {{ $point['value'] }} arrivals">
                    <span class="text-xs font-semibold ma-report-number">{{ $point['value'] ?: '' }}</span>
                    <div class="ma-report-trend-bar" style="height: {{ max(2, ($point['value'] / $trendMax) * 120) }}px"></div>
                    <span class="text-xs ma-report-muted">{{ $point['short_label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Property performance</x-slot>
        <x-slot name="description">Comparable room-night and payment results for the selected period</x-slot>
        <div class="ma-report-table-wrap">
            <table class="ma-report-table">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Bookings</th>
                        <th>Sold room nights</th>
                        <th>Occupancy</th>
                        <th class="text-right">Net revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($propertyPerformance as $property)
                        <tr>
                            <td class="font-medium">{{ $property['name'] }}</td>
                            <td class="ma-report-number">{{ number_format($property['bookings']) }}</td>
                            <td class="ma-report-number">{{ number_format($property['sold_nights']) }}</td>
                            <td class="ma-report-number">{{ number_format($property['occupancy'], 1) }}%</td>
                            <td class="text-right font-medium ma-report-number">PHP {{ number_format($property['revenue'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center ma-report-muted">No property data is available for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Bookings in this report</x-slot>
        <x-slot name="description">The eight most recently received reservations matching the filters</x-slot>
        <div class="ma-report-table-wrap">
            <table class="ma-report-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Guest</th>
                        <th>Property</th>
                        <th>Stay</th>
                        <th>Status</th>
                        <th class="text-right">Estimated total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentReservations as $reservation)
                        <tr>
                            <td class="font-medium">{{ $reservation->reference_number }}</td>
                            <td>{{ $reservation->guest?->name }}</td>
                            <td>{{ $reservation->property?->name ?? $reservation->room?->property?->name }}</td>
                            <td>{{ $reservation->check_in?->format('M j') }} - {{ $reservation->check_out?->format('M j, Y') }}</td>
                            <td><span class="ma-report-badge">{{ str($reservation->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="text-right ma-report-number">PHP {{ number_format((float) $reservation->estimated_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center ma-report-muted">No reservations match the selected stay dates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
