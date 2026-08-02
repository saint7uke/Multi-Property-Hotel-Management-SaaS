<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReservationsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly Collection $reservations) {}

    public function collection(): Collection
    {
        return $this->reservations;
    }

    public function headings(): array
    {
        return ['Reference', 'Guest', 'Property', 'Room', 'Status', 'Payment', 'Check in', 'Check out', 'Estimated total'];
    }

    public function map($reservation): array
    {
        return [
            $reservation->reference_number,
            $reservation->guest->name,
            $reservation->room?->property?->name ?? $reservation->property?->name,
            $reservation->room?->room_number,
            $reservation->status,
            $reservation->payment_status,
            $reservation->check_in?->toDateString(),
            $reservation->check_out?->toDateString(),
            'PHP '.number_format((float) $reservation->estimated_total, 2),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
