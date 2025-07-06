<?php

use App\Exports\BookingsExport;
use App\Exports\BookingsPdfExport;
use App\Models\Booking;
use App\Models\Court;
use App\Enum\BookingStatusEnum;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

new #[Layout('components.backend.layouts.app')]
class extends Component
{
    // Export filters
    public $dateFrom = '';
    public $dateTo = '';
    public $statusFilter = '';
    public $courtFilter = '';
    public $bookingTypeFilter = '';
    public $exportFormat = 'excel'; // 'excel' or 'pdf'

    // Export state
    public $isExporting = false;
    public $exportProgress = 0;
    public $exportMessage = '';

    public function mount()
    {
        // Set default date range to current month
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    public function getCourtsProperty()
    {
        return Court::orderBy('name')->get();
    }

    public function getFilteredBookingsProperty()
    {
        $query = Booking::with(['tenant', 'court', 'approver', 'canceller'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc');

        // Apply date filters
        if ($this->dateFrom) {
            $query->where('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('date', '<=', $this->dateTo);
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', BookingStatusEnum::from($this->statusFilter));
        }

        // Apply court filter
        if ($this->courtFilter) {
            $query->where('court_id', $this->courtFilter);
        }

        // Apply booking type filter
        if ($this->bookingTypeFilter) {
            $query->where('booking_type', $this->bookingTypeFilter);
        }

        return $query->get();
    }

    public function getBookingStatsProperty()
    {
        $bookings = $this->filteredBookings;

        return [
            'total' => $bookings->count(),
            'confirmed' => $bookings->where('status', 'confirmed')->count(),
            'pending' => $bookings->where('status', 'pending')->count(),
            'cancelled' => $bookings->where('status', 'cancelled')->count(),
            'free' => $bookings->where('booking_type', 'free')->count(),
            'premium' => $bookings->where('booking_type', 'premium')->count(),
            'total_revenue' => $bookings->sum(function ($booking) {
                return $booking->price + $booking->light_surcharge;
            }),
            'avg_revenue_per_booking' => $bookings->count() > 0 ?
                $bookings->sum(function ($booking) {
                    return $booking->price + $booking->light_surcharge;
                }) / $bookings->count() : 0,
        ];
    }

    public function exportReport()
    {
        $this->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
            'exportFormat' => 'required|in:excel,pdf',
        ]);

        $this->isExporting = true;
        $this->exportProgress = 0;
        $this->exportMessage = 'Preparing export...';

        try {
            $bookings = $this->filteredBookings;

            $this->exportProgress = 25;
            $this->exportMessage = 'Processing data...';

            // Prepare filters for export
            $filters = [
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'status' => $this->statusFilter,
                'court' => $this->courtFilter,
                'booking_type' => $this->bookingTypeFilter,
            ];

            $this->exportProgress = 50;
            $this->exportMessage = 'Generating report...';

            if ($this->exportFormat === 'excel') {
                $this->exportExcel($bookings, $filters);
            } else {
                $this->exportPdf($bookings, $filters);
            }

            $this->exportProgress = 100;
            $this->exportMessage = 'Export completed successfully!';

            // Reset after a short delay
            $this->dispatch('export-completed');

        } catch (\Exception $e) {
            $this->exportMessage = 'Export failed: ' . $e->getMessage();
            \Log::error('Export failed: ' . $e->getMessage());
        } finally {
            $this->isExporting = false;
        }
    }

    private function exportExcel($bookings, $filters)
    {
        $filename = 'bookings_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new BookingsExport($bookings, $filters), $filename);
    }

    private function exportPdf($bookings, $filters)
    {
        $pdfExport = new BookingsPdfExport($bookings, $filters);
        $html = $pdfExport->generateHtml();

        $filename = 'bookings_report_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function resetFilters()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        $this->statusFilter = '';
        $this->courtFilter = '';
        $this->bookingTypeFilter = '';
    }
} ?>

<div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-900">📊 Report Export</h1>
                <div class="flex items-center gap-2">
                    <button wire:click="resetFilters" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        🔄 Reset Filters
                    </button>
                </div>
            </div>

            <!-- Export Filters -->
            <div class="mt-6 bg-white shadow rounded-xl p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">📋 Export Filters</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" wire:model="dateFrom" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" wire:model="dateTo" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model="statusFilter" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Court Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Court</label>
                        <select wire:model="courtFilter" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Courts</option>
                            @foreach($this->courts as $court)
                                <option value="{{ $court->id }}">{{ $court->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Booking Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Booking Type</label>
                        <select wire:model="bookingTypeFilter" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Types</option>
                            <option value="free">Free</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>

                    <!-- Export Format -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Export Format</label>
                        <select wire:model="exportFormat" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="excel">📊 Excel (.xlsx)</option>
                            <option value="pdf">📄 PDF (.pdf)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Statistics Summary -->
            <div class="mt-6 bg-white shadow rounded-xl p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">📈 Statistics Summary</h2>

                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $this->bookingStats['total'] }}</div>
                        <div class="text-sm text-blue-600">Total Bookings</div>
                    </div>

                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $this->bookingStats['confirmed'] }}</div>
                        <div class="text-sm text-green-600">Confirmed</div>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ $this->bookingStats['pending'] }}</div>
                        <div class="text-sm text-yellow-600">Pending</div>
                    </div>

                    <div class="bg-red-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-red-600">{{ $this->bookingStats['cancelled'] }}</div>
                        <div class="text-sm text-red-600">Cancelled</div>
                    </div>

                    <div class="bg-purple-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ $this->bookingStats['premium'] }}</div>
                        <div class="text-sm text-purple-600">Premium</div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-gray-600">{{ $this->bookingStats['free'] }}</div>
                        <div class="text-sm text-gray-600">Free</div>
                    </div>

                    <div class="bg-indigo-50 p-4 rounded-lg text-center">
                        <div class="text-lg font-bold text-indigo-600">IDR {{ number_format($this->bookingStats['total_revenue'], 0, ',', '.') }}</div>
                        <div class="text-sm text-indigo-600">Total Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Export Button -->
            <div class="mt-6 bg-white shadow rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">🚀 Export Report</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            @if($this->bookingStats['total'] > 0)
                                Export {{ $this->bookingStats['total'] }} bookings as {{ strtoupper($exportFormat) }} file
                            @else
                                Export empty report as {{ strtoupper($exportFormat) }} file (no bookings found)
                            @endif
                        </p>
                    </div>

                    <button
                        wire:click="exportReport"
                        wire:loading.attr="disabled"
                        @class([
                            'px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200',
                            'bg-blue-600 hover:bg-blue-700 cursor-pointer' => !$isExporting,
                            'bg-gray-400 cursor-not-allowed' => $isExporting,
                        ])
                        @if($isExporting) disabled @endif
                    >
                        @if($isExporting)
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                Exporting...
                            </div>
                        @else
                            📥 Export {{ strtoupper($exportFormat) }}
                        @endif
                    </button>
                </div>

                @if($isExporting)
                    <div class="mt-4">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: {{ $exportProgress }}%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ $exportMessage }}</p>
                    </div>
                @endif
            </div>

            <!-- Preview Table -->
            @if($this->filteredBookings->count() > 0)
                <div class="mt-6 bg-white shadow rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">👀 Preview (First 10 Records)</h2>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Court</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total (IDR)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->filteredBookings->take(10) as $booking)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->booking_reference }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->tenant->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->court->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->date->format('M j, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'confirmed' => 'bg-green-100 text-green-800',
                                                    'cancelled' => 'bg-red-100 text-red-800'
                                                ];
                                                $colorClass = $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span @class(["inline-flex px-2 py-1 text-xs font-semibold rounded-full", $colorClass])>
                                                {{ ucfirst($booking->status->value) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $typeColors = [
                                                    'free' => 'bg-green-100 text-green-800',
                                                    'premium' => 'bg-purple-100 text-purple-800'
                                                ];
                                                $typeColorClass = $typeColors[$booking->booking_type] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span @class(["inline-flex px-2 py-1 text-xs font-semibold rounded-full", $typeColorClass])>
                                                {{ ucfirst($booking->booking_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($booking->price + $booking->light_surcharge, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($this->filteredBookings->count() > 10)
                        <p class="text-sm text-gray-500 mt-4 text-center">
                            Showing first 10 of {{ $this->filteredBookings->count() }} records. Export to see all data.
                        </p>
                    @endif
                </div>
            @else
                <div class="mt-6 bg-white shadow rounded-xl p-6">
                    <div class="text-center py-8">
                        <div class="text-4xl mb-4">📭</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No bookings found</h3>
                        <p class="text-gray-600">No bookings match your current filters. You can still export an empty report or try adjusting your filters.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @script
    <script>
        // Listen for export completion
        $wire.$on('export-completed', () => {
            // Show success notification
            toast('Export completed successfully!', {type: 'success'});
        });
    </script>
    @endscript
</div>
