<?php

namespace App\Http\Livewire\Admin;

use App\Models\Booking;
use App\Models\Tenant;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.backend.layouts.app')]
class extends Component
{
    public $tenant = null;

    public $tenantId = '';

    public $freeBookings = [];

    public $premiumBookings = [];

    public function mount($id = null)
    {
        if ($id) {
            $this->tenantId = $id;
            $this->loadTenantDetails();
        }
    }

    public function loadTenantDetails()
    {
        $this->tenant = Tenant::where('tenant_id', $this->tenantId)->orWhere('id', $this->tenantId)->first();

        if (! $this->tenant) {
            session()->flash('error', 'Tenant not found');

            return;
        }

        $this->loadBookings();
    }

    public function loadBookings()
    {
        $this->freeBookings = $this->tenant->bookings()
            ->where('booking_type', 'free')
            ->where('status', '!=', 'cancelled')
            ->where('date', '>=', Carbon::now())
            ->with('court')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $this->premiumBookings = $this->tenant->bookings()
            ->where('booking_type', 'premium')
            ->where('status', '!=', 'cancelled')
            ->where('date', '>=', Carbon::now())
            ->with('court')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    public function confirmPayment($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'confirmed',
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        $this->loadBookings();
        session()->flash('message', 'Payment confirmed successfully!');
    }

    public function denyBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'cancelled',
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        $this->loadBookings();
        $this->loadQuotas();
        session()->flash('message', 'Booking denied successfully!');
    }
}

?>

<div>
    <div class="mx-auto max-w-7xl p-6">
        <!-- Search Section -->
        <div class="mb-6">
            <h1 class="mb-4 text-3xl font-bold">Tenant Details</h1>
        </div>

        @if (session()->has('message'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            {{ session('message') }}
        </div>
        @endif

        @if (session()->has('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            {{ session('error') }}
        </div>
        @endif

        @if ($tenant)
        <!-- Tenant Info -->
        <div class="mb-6 rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-xl font-bold">{{ $tenant->display_name }}</h2>
            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div>
                    <span class="font-medium">Name:</span> {{ $tenant->name }}
                </div>
                <div>
                    <span class="font-medium">Email:</span> {{ $tenant->email }}
                </div>
                <div>
                    <span class="font-medium">Phone:</span> {{ $tenant->phone ?? 'N/A' }}
                </div>
            </div>
        </div>

        <!-- Quota Section -->
        <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Free Booking Quota -->
            <div class="rounded-lg bg-white p-6 shadow">

                @if (!empty($freeBookings))
                @foreach ($freeBookings as $booking)
                <div class="mb-3 flex items-center justify-between rounded bg-gray-100 p-4">
                    <div>
                        <div class="font-semibold">
                            Court {{ $booking->court->name }} / {{ $booking->booking_type_display }} /
                            {{ $booking->date->format('d M Y') }} /
                            {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                        </div>
                        @if ($booking->is_light_required)
                        <div class="text-sm text-orange-600">
                            (additional 50k for tennis court light applied)
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($booking->status === 'pending')
                        <button
                            class="rounded bg-gray-500 px-3 py-1 text-sm text-white hover:bg-gray-600"
                            wire:click="denyBooking({{ $booking->id }})">
                            DENY
                        </button>
                        <button
                            class="rounded bg-gray-700 px-3 py-1 text-sm text-white hover:bg-gray-800"
                            wire:click="confirmPayment({{ $booking->id }})">
                            CONFIRM PAYMENT
                        </button>
                        @else
                        <button class="rounded bg-gray-400 px-3 py-1 text-sm text-white">
                            EDIT
                        </button>
                        <span class="rounded bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                            STATUS {{ $booking->status_display }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
                @else
                <div class="py-4 text-center text-gray-500">No free bookings found</div>
                @endif
            </div>

            <!-- Premium Booking Quota -->
            <div class="rounded-lg bg-white p-6 shadow">

                @if (!empty($premiumBookings))
                @foreach ($premiumBookings as $booking)
                <div class="mb-3 flex items-center justify-between rounded bg-gray-100 p-4">
                    <div>
                        <div class="font-semibold">
                            Court {{ $booking->court->name }} / {{ $booking->booking_type_display }} /
                            {{ $booking->date->format('d M Y') }} /
                            {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                        </div>
                        @if ($booking->is_light_required)
                        <div class="text-sm text-orange-600">
                            (additional 50k for tennis court light applied)
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($booking->status === 'pending')
                        <button
                            class="rounded bg-gray-500 px-3 py-1 text-sm text-white hover:bg-gray-600"
                            wire:click="denyBooking({{ $booking->id }})">
                            DENY
                        </button>
                        <button
                            class="rounded bg-gray-700 px-3 py-1 text-sm text-white hover:bg-gray-800"
                            wire:click="confirmPayment({{ $booking->id }})">
                            CONFIRM PAYMENT
                        </button>
                        @else
                        <button class="rounded bg-gray-400 px-3 py-1 text-sm text-white">
                            EDIT
                        </button>
                        <span class="rounded bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                            STATUS {{ $booking->status_display }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
                @else
                <div class="py-4 text-center text-gray-500">No premium bookings found</div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
