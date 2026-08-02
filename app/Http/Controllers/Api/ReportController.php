<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReservationsExport;
use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ScopesPropertyAccess;

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'property_id' => ['nullable', 'exists:properties,id'],
        ]);

        $reservations = Reservation::query()->with('room.property');
        $this->scopeReservationsFor($request->user(), $reservations);
        $this->applyReportFilters($reservations, $validated);

        $rooms = Room::query();
        $this->scopeRoomsFor($request->user(), $rooms);
        $rooms->when($validated['property_id'] ?? null, fn ($query, $propertyId) => $query->where('property_id', $propertyId));

        $transactions = Payment::query()
            ->whereIn('status', ['paid', 'refunded'])
            ->when(($validated['from'] ?? null) || ($validated['to'] ?? null), function ($query) use ($validated): void {
                $query->where(function ($transactions) use ($validated): void {
                    $transactions->where(function ($paid) use ($validated): void {
                        $paid->where('status', 'paid')
                            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('paid_at', '>=', $from))
                            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('paid_at', '<=', $to));
                    })->orWhere(function ($refunded) use ($validated): void {
                        $refunded->where('status', 'refunded')
                            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('refunded_at', '>=', $from))
                            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('refunded_at', '<=', $to));
                    });
                });
            })
            ->whereHas('reservation', function ($query) use ($request, $validated) {
                $this->scopeReservationsFor($request->user(), $query);

                if ($validated['property_id'] ?? null) {
                    $this->applyPropertyFilter($query, $validated['property_id']);
                }
            })
            ->get(['status', 'amount']);

        $netRevenue = $transactions->sum(fn (Payment $payment): float => $payment->status === 'refunded'
            ? -1 * (float) $payment->amount
            : (float) $payment->amount);

        return response()->json([
            'summary' => [
                'reservations' => (clone $reservations)->count(),
                'pending' => (clone $reservations)->where('status', 'pending')->count(),
                'confirmed' => (clone $reservations)->where('status', 'confirmed')->count(),
                'checked_in' => (clone $reservations)->where('status', 'checked_in')->count(),
                'revenue' => (float) $netRevenue,
                'occupancy_rate' => $rooms->count() ? round(((clone $rooms)->where('status', 'occupied')->count() / $rooms->count()) * 100, 1) : 0,
            ],
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $rows = $this->exportRows($request);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Guest', 'Property', 'Room', 'Status', 'Payment', 'Check in', 'Check out', 'Estimated total']);

            foreach ($rows as $reservation) {
                fputcsv($handle, [
                    $reservation->reference_number,
                    $reservation->guest->name,
                    $reservation->room?->property?->name ?? $reservation->property?->name,
                    $reservation->room?->room_number,
                    $reservation->status,
                    $reservation->payment_status,
                    $reservation->check_in?->toDateString(),
                    $reservation->check_out?->toDateString(),
                    'PHP '.number_format((float) $reservation->estimated_total, 2),
                ]);
            }

            fclose($handle);
        }, 'reservations-report.csv');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        return Excel::download(new ReservationsExport($this->exportRows($request)), 'reservations-report.xlsx');
    }

    private function exportRows(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'property_id' => ['nullable', 'exists:properties,id'],
        ]);

        $query = Reservation::query()->with('guest', 'property', 'room.property');
        $this->scopeReservationsFor($request->user(), $query);
        $this->applyReportFilters($query, $validated);

        return $query->latest()->get();
    }

    private function applyReportFilters($query, array $validated): void
    {
        $query
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('check_out', '>', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('check_in', '<=', $to));

        if ($validated['property_id'] ?? null) {
            $this->applyPropertyFilter($query, $validated['property_id']);
        }
    }

    private function applyPropertyFilter($query, int|string $propertyId): void
    {
        $query->where(function ($query) use ($propertyId) {
            $query->where('property_id', $propertyId)
                ->orWhereHas('room', fn ($room) => $room->where('property_id', $propertyId));
        });
    }
}
