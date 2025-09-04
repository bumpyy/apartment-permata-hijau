<div class="mb-6 grid grid-cols-3 gap-4">

    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-blue-700">{{ $this->upcomingBookings->count() }}</div>
        <div class="mt-1 text-xs text-gray-500">Upcoming bookings days</div>
    </div>

    {{-- <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-blue-700">{{ $this->totalBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Total bookings slots</div>
    </div> --}}

    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-green-700">{{ $this->activeBookingsCount }}</div>
        <div class="mt-1 text-xs text-gray-500">Time Slots Booked</div>
    </div>

    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-yellow-600">{{ $this->pendingBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Pending</div>
    </div>

    {{-- <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-red-600">{{ $this->cancelledBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Cancelled booking slots</div>
    </div> --}}
</div>
