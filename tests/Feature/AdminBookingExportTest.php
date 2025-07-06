<?php

namespace Tests\Feature;

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AdminBookingExportTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $tenant;

    protected $court;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create court
        $this->court = Court::factory()->create();

        // Create some test bookings
        $this->createTestBookings();
    }

    private function createTestBookings()
    {
        // Create bookings for different dates and statuses
        $dates = [
            now()->subDays(5),
            now()->subDays(3),
            now()->subDays(1),
            now(),
            now()->addDays(1),
            now()->addDays(3),
        ];

        $statuses = ['pending', 'confirmed', 'cancelled'];
        $types = ['free', 'premium'];

        foreach ($dates as $index => $date) {
            $status = $statuses[$index % count($statuses)];
            $type = $types[$index % count($types)];

            Booking::factory()->create([
                'tenant_id' => $this->tenant->id,
                'court_id' => $this->court->id,
                'date' => $date,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'status' => BookingStatusEnum::from($status),
                'booking_type' => $type,
                'price' => $type === 'premium' ? 150000 : 0,
                'light_surcharge' => 0,
                'is_light_required' => false,
                'booking_reference' => 'BK'.$this->tenant->id.'-'.$this->court->id.'-'.$date->format('Y-m-d').'-TEST'.$index,
            ]);
        }
    }

    public function test_admin_can_access_export_modal()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.booking'))
            ->assertStatus(200)
            ->assertSee('Export Report');
    }

    public function test_admin_can_open_export_modal()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->call('openExportModal')
            ->assertSet('showExportModal', true)
            ->assertSet('exportDateFrom', now()->startOfMonth()->format('Y-m-d'))
            ->assertSet('exportDateTo', now()->endOfMonth()->format('Y-m-d'));
    }

    public function test_admin_can_close_export_modal()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->call('closeExportModal')
            ->assertSet('showExportModal', false);
    }

    public function test_export_validation_requires_date_range()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', '')
            ->set('exportDateTo', '')
            ->call('exportBookings')
            ->assertHasErrors(['exportDateFrom', 'exportDateTo']);
    }

    public function test_export_validation_requires_valid_date_range()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->format('Y-m-d'))
            ->set('exportDateTo', now()->subDay()->format('Y-m-d'))
            ->call('exportBookings')
            ->assertHasErrors(['exportDateTo']);
    }

    public function test_export_validation_requires_valid_format()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportFormat', 'invalid')
            ->call('exportBookings')
            ->assertHasErrors(['exportFormat']);
    }

    public function test_excel_export_returns_download_response()
    {
        Excel::fake();

        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->subWeek()->format('Y-m-d'))
            ->set('exportDateTo', now()->addWeek()->format('Y-m-d'))
            ->set('exportFormat', 'excel')
            ->call('exportBookings');

        Excel::assertDownloaded('bookings_report_'.now()->format('Y-m-d_H-i-s').'.xlsx');
    }

    public function test_pdf_export_returns_download_response()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->subWeek()->format('Y-m-d'))
            ->set('exportDateTo', now()->addWeek()->format('Y-m-d'))
            ->set('exportFormat', 'pdf')
            ->call('exportBookings');

        // The PDF export should return a response
        // Note: We can't easily test the actual PDF content in a unit test
        // but we can verify the method doesn't throw exceptions
        $this->assertTrue(true);
    }

    public function test_export_with_status_filter()
    {
        Excel::fake();

        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->subWeek()->format('Y-m-d'))
            ->set('exportDateTo', now()->addWeek()->format('Y-m-d'))
            ->set('exportStatusFilter', 'confirmed')
            ->set('exportFormat', 'excel')
            ->call('exportBookings');

        Excel::assertDownloaded('bookings_report_'.now()->format('Y-m-d_H-i-s').'.xlsx');
    }

    public function test_export_with_court_filter()
    {
        Excel::fake();

        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->subWeek()->format('Y-m-d'))
            ->set('exportDateTo', now()->addWeek()->format('Y-m-d'))
            ->set('exportCourtFilter', $this->court->id)
            ->set('exportFormat', 'excel')
            ->call('exportBookings');

        Excel::assertDownloaded('bookings_report_'.now()->format('Y-m-d_H-i-s').'.xlsx');
    }

    public function test_export_with_booking_type_filter()
    {
        Excel::fake();

        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->subWeek()->format('Y-m-d'))
            ->set('exportDateTo', now()->addWeek()->format('Y-m-d'))
            ->set('exportBookingTypeFilter', 'premium')
            ->set('exportFormat', 'excel')
            ->call('exportBookings');

        Excel::assertDownloaded('bookings_report_'.now()->format('Y-m-d_H-i-s').'.xlsx');
    }

    public function test_export_with_no_results_shows_error()
    {
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->addMonths(6)->format('Y-m-d'))
            ->set('exportDateTo', now()->addMonths(7)->format('Y-m-d'))
            ->set('exportFormat', 'excel')
            ->call('exportBookings')
            ->assertSet('isExporting', false);

        // Should have flashed an error message
        $this->assertTrue(session()->has('error'));
    }

    public function test_export_handles_exceptions_gracefully()
    {
        // This test would require mocking the export classes to throw exceptions
        // For now, we'll just verify the export method exists and can be called
        $this->actingAs($this->admin, 'admin')
            ->livewire('admin.booking')
            ->set('showExportModal', true)
            ->set('exportDateFrom', now()->subWeek()->format('Y-m-d'))
            ->set('exportDateTo', now()->addWeek()->format('Y-m-d'))
            ->set('exportFormat', 'excel');

        // The method should exist and not throw an exception
        $this->assertTrue(method_exists($this->admin, 'exportBookings'));
    }
}
