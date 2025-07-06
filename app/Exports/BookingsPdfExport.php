<?php

namespace App\Exports;

use Carbon\Carbon;

class BookingsPdfExport
{
    protected $bookings;

    protected $filters;

    public function __construct($bookings, $filters = [])
    {
        $this->bookings = $bookings;
        $this->filters = $filters;
    }

    public function generateHtml()
    {
        $totalBookings = $this->bookings->count();
        $totalRevenue = $this->bookings->sum(function ($booking) {
            return $booking->price + $booking->light_surcharge;
        });

        $confirmedBookings = $this->bookings->where('status', 'confirmed')->count();
        $pendingBookings = $this->bookings->where('status', 'pending')->count();
        $cancelledBookings = $this->bookings->where('status', 'cancelled')->count();

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Bookings Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #2563EB;
                    padding-bottom: 20px;
                }
                .header h1 {
                    color: #2563EB;
                    margin: 0 0 10px 0;
                    font-size: 24px;
                }
                .header p {
                    margin: 5px 0;
                    color: #666;
                }
                .summary {
                    background: #F8FAFC;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    border-left: 4px solid #2563EB;
                }
                .summary-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                }
                .summary-item {
                    text-align: center;
                }
                .summary-item .number {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2563EB;
                }
                .summary-item .label {
                    font-size: 11px;
                    color: #666;
                    text-transform: uppercase;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 10px;
                }
                th {
                    background-color: #2563EB;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                }
                td {
                    padding: 6px 8px;
                    border-bottom: 1px solid #E5E7EB;
                }
                tr:nth-child(even) {
                    background-color: #F8FAFC;
                }
                .status-pending {
                    color: #D97706;
                    font-weight: bold;
                }
                .status-confirmed {
                    color: #059669;
                    font-weight: bold;
                }
                .status-cancelled {
                    color: #DC2626;
                    font-weight: bold;
                }
                .booking-type-free {
                    color: #059669;
                    font-weight: bold;
                }
                .booking-type-premium {
                    color: #7C3AED;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px solid #E5E7EB;
                    padding-top: 15px;
                }
                .page-break {
                    page-break-before: always;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🎾 Tennis Court Bookings Report</h1>
                <p>Generated on: '.now()->format('F j, Y \a\t g:i A').'</p>';

        if (! empty($this->filters)) {
            $filters = [];
            if (isset($this->filters['date_from'])) {
                $filters[] = 'From: '.$this->filters['date_from'];
            }
            if (isset($this->filters['date_to'])) {
                $filters[] = 'To: '.$this->filters['date_to'];
            }
            if (isset($this->filters['status'])) {
                $filters[] = 'Status: '.ucfirst($this->filters['status']);
            }
            if (isset($this->filters['court'])) {
                $filters[] = 'Court: '.$this->filters['court'];
            }

            if (! empty($filters)) {
                $html .= '<p>Filters: '.implode(', ', $filters).'</p>';
            }
        }

        $html .= '
            </div>

            <div class="summary">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="number">'.$totalBookings.'</div>
                        <div class="label">Total Bookings</div>
                    </div>
                    <div class="summary-item">
                        <div class="number">'.number_format($totalRevenue, 0, ',', '.').'</div>
                        <div class="label">Total Revenue (IDR)</div>
                    </div>
                    <div class="summary-item">
                        <div class="number">'.$confirmedBookings.'</div>
                        <div class="label">Confirmed</div>
                    </div>
                    <div class="summary-item">
                        <div class="number">'.$pendingBookings.'</div>
                        <div class="label">Pending</div>
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Tenant</th>
                        <th>Court</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Price (IDR)</th>
                        <th>Total (IDR)</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($this->bookings as $booking) {
            $startTime = $booking->start_time instanceof Carbon ? $booking->start_time : Carbon::parse($booking->start_time);
            $endTime = $booking->end_time instanceof Carbon ? $booking->end_time : Carbon::parse($booking->end_time);
            $totalPrice = $booking->price + $booking->light_surcharge;

            $statusClass = 'status-'.$booking->status->value;
            $typeClass = 'booking-type-'.$booking->booking_type;

            $html .= '
                <tr>
                    <td>'.$booking->booking_reference.'</td>
                    <td>'.($booking->tenant->name ?? 'N/A').'</td>
                    <td>'.($booking->court->name ?? 'N/A').'</td>
                    <td>'.$booking->date->format('M j, Y').'</td>
                    <td>'.$startTime->format('H:i').' - '.$endTime->format('H:i').'</td>
                    <td class="'.$statusClass.'">'.strtoupper($booking->status->value).'</td>
                    <td class="'.$typeClass.'">'.strtoupper($booking->booking_type).'</td>
                    <td>'.number_format($booking->price, 0, ',', '.').'</td>
                    <td>'.number_format($totalPrice, 0, ',', '.').'</td>
                </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <div class="footer">
                <p>This report was generated automatically by the Tennis Court Booking System</p>
                <p>For questions or support, please contact the administration</p>
            </div>
        </body>
        </html>';

        return $html;
    }
}
