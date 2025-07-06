<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class BookingsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    protected $bookings;
    protected $filters;

    public function __construct($bookings, $filters = [])
    {
        $this->bookings = $bookings;
        $this->filters = $filters;
    }

    public function collection()
    {
        return $this->bookings;
    }

    public function headings(): array
    {
        return [
            'Booking Reference',
            'Tenant Name',
            'Tenant Email',
            'Court',
            'Date',
            'Start Time',
            'End Time',
            'Duration (Hours)',
            'Status',
            'Booking Type',
            'Price (IDR)',
            'Light Surcharge (IDR)',
            'Total Price (IDR)',
            'Lights Required',
            'Notes',
            'Created At',
            'Approved By',
            'Approved At',
            'Cancelled By',
            'Cancelled At',
            'Cancellation Reason',
        ];
    }

    public function map($booking): array
    {
        $startTime = $booking->start_time instanceof Carbon ? $booking->start_time : Carbon::parse($booking->start_time);
        $endTime = $booking->end_time instanceof Carbon ? $booking->end_time : Carbon::parse($booking->end_time);
        $duration = $startTime->diffInHours($endTime);

        return [
            $booking->booking_reference,
            $booking->tenant->name ?? 'N/A',
            $booking->tenant->email ?? 'N/A',
            $booking->court->name ?? 'N/A',
            $booking->date->format('Y-m-d'),
            $startTime->format('H:i'),
            $endTime->format('H:i'),
            $duration,
            strtoupper($booking->status->value),
            strtoupper($booking->booking_type),
            number_format($booking->price, 0, ',', '.'),
            number_format($booking->light_surcharge, 0, ',', '.'),
            number_format($booking->price + $booking->light_surcharge, 0, ',', '.'),
            $booking->is_light_required ? 'Yes' : 'No',
            $booking->notes ?? '',
            $booking->created_at->format('Y-m-d H:i:s'),
            $booking->approver->name ?? 'N/A',
            $booking->approved_at ? $booking->approved_at->format('Y-m-d H:i:s') : 'N/A',
            $booking->canceller->name ?? 'N/A',
            $booking->cancelled_at ? $booking->cancelled_at->format('Y-m-d H:i:s') : 'N/A',
            $booking->cancellation_reason ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styles
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'], // Blue
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Data rows - alternate colors
        $highestRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $fillColor = $row % 2 == 0 ? 'F8FAFC' : 'FFFFFF'; // Light gray and white
            $sheet->getStyle("A{$row}:U{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor],
                ],
            ]);
        }

        // Border for all cells
        $sheet->getStyle("A1:U{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);

        // Auto-filter
        $sheet->setAutoFilter("A1:U{$highestRow}");

        return $sheet;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Booking Reference
            'B' => 25, // Tenant Name
            'C' => 30, // Tenant Email
            'D' => 15, // Court
            'E' => 12, // Date
            'F' => 10, // Start Time
            'G' => 10, // End Time
            'H' => 15, // Duration
            'I' => 12, // Status
            'J' => 15, // Booking Type
            'K' => 15, // Price
            'L' => 20, // Light Surcharge
            'M' => 18, // Total Price
            'N' => 15, // Lights Required
            'O' => 30, // Notes
            'P' => 20, // Created At
            'Q' => 20, // Approved By
            'R' => 20, // Approved At
            'S' => 20, // Cancelled By
            'T' => 20, // Cancelled At
            'U' => 25, // Cancellation Reason
        ];
    }

    public function title(): string
    {
        $title = 'Bookings Report';

        if (!empty($this->filters)) {
            $filters = [];
            if (isset($this->filters['date_from'])) {
                $filters[] = 'From: ' . $this->filters['date_from'];
            }
            if (isset($this->filters['date_to'])) {
                $filters[] = 'To: ' . $this->filters['date_to'];
            }
            if (isset($this->filters['status'])) {
                $filters[] = 'Status: ' . ucfirst($this->filters['status']);
            }
            if (isset($this->filters['court'])) {
                $filters[] = 'Court: ' . $this->filters['court'];
            }

            if (!empty($filters)) {
                $title .= ' (' . implode(', ', $filters) . ')';
            }
        }

        return $title;
    }
}
