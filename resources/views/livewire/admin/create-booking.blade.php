<?php

use function Livewire\Volt\{layout, state, mount};
use App\Models\Booking;
use App\Models\Tenant;
use Carbon\Carbon;

layout('components.frontend.app');

state([
    'selectedCourt' => 2,
    'selectedDate' => '',
    'selectedTime' => '',
    'selectedTenant' => '',
    'tenantName' => '',
    'tenantPhone' => '',
    'bookingLimit' => '',
    'isLightRequired' => false,
    'tenants' => [],
]);

mount(function () {
    $this->tenants = Tenant::where('is_active', true)->get();
    $this->selectedDate = '16 June 2025';
    $this->selectedTime = '19:00 - 20:00';
    $this->checkLightRequirement();
});

$checkLightRequirement = function () {
    $time = explode(' - ', $this->selectedTime)[0];
    $hour = (int) explode(':', $time)[0];
    $this->isLightRequired = $hour >= 18;
};

$selectTenant = function ($tenantId) {
    $tenant = Tenant::find($tenantId);
    if ($tenant) {
        $this->selectedTenant = $tenant->tenant_id;
        $this->tenantName = $tenant->name;
        $this->tenantPhone = $tenant->phone;
        $this->bookingLimit = $tenant->remaining_bookings . '/' . $tenant->booking_limit;
    }
};

$confirmBooking = function () {
    $tenant = Tenant::where('tenant_id', $this->selectedTenant)->first();

    if (!$tenant) {
        session()->flash('error', 'Please select a valid tenant.');
        return;
    }

    $date = Carbon::createFromFormat('d F Y', $this->selectedDate);
    $startTime = explode(' - ', $this->selectedTime)[0];
    $endTime = explode(' - ', $this->selectedTime)[1];

    $booking = Booking::create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->selectedCourt,
        'date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'status' => 'confirmed',
        'is_light_required' => $this->isLightRequired,
        'approved_by' => auth()->id(),
        'approved_at' => now(),
    ]);

    $booking->calculatePrice();
    $booking->booking_reference = $booking->generateReference();
    $booking->save();

    session()->flash('message', 'Booking created successfully! Reference: #' . $booking->booking_reference);

    $this->reset(['selectedTenant', 'tenantName', 'tenantPhone', 'bookingLimit']);
};

?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-8">Court {{ $selectedCourt }} / {{ $selectedDate }} / {{ $selectedTime }}</h1>

    <div class="mb-6">
        <span class="text-sm font-medium">Status:</span>
        <div class="flex items-center gap-2 mt-1">
            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
            <span class="font-semibold">AVAILABLE</span>
        </div>
    </div>

    <hr class="my-6">

    <h2 class="text-xl font-bold mb-6">Make Booking</h2>

    @if (session()->has('message'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
        {{ session('message') }}
    </div>
    @endif

    @if (session()->has('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Tenant</label>
                <select
                    class="w-full p-3 border border-gray-300 rounded bg-white"
                    wire:model="selectedTenant"
                    wire:change="selectTenant($event.target.value)">
                    <option value="">Select Tenant</option>
                    @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Name</label>
                <input
                    type="text"
                    class="w-full p-3 border border-gray-300 rounded bg-gray-100"
                    wire:model="tenantName"
                    readonly>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Booking Limit</label>
                <input
                    type="text"
                    class="w-full p-3 border border-gray-300 rounded bg-gray-100"
                    wire:model="bookingLimit"
                    readonly>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Phone</label>
                <input
                    type="text"
                    class="w-full p-3 border border-gray-300 rounded bg-gray-100"
                    wire:model="tenantPhone"
                    readonly>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Status</label>
                <input
                    type="text"
                    class="w-full p-3 border border-gray-300 rounded bg-green-500 text-white font-semibold"
                    value="GREEN"
                    readonly>
            </div>
        </div>
    </div>

    @if($isLightRequired)
    <div class="mt-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
        <div class="flex items-center gap-2">
            <span class="font-medium">50k for tennis court light applied</span>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                <span class="text-red-600 font-semibold">UNPAID</span>
            </div>
        </div>
    </div>
    @endif

    <div class="mt-8 flex justify-end">
        <button
            class="bg-gray-700 text-white px-8 py-3 rounded font-semibold hover:bg-gray-800 transition-colors"
            wire:click="confirmBooking"
            @disabled(!$selectedTenant)>
            CONFIRM
        </button>
    </div>
</div>
