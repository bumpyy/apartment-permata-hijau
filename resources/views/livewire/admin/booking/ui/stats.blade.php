<div class="mb-6 grid grid-cols-4 gap-4">
    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-blue-700">{{ $this->totalBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Total Bookings</div>
    </div>
    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-green-700">{{ $this->activeBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Confirmed</div>
    </div>
    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-yellow-600">{{ $this->pendingBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Pending</div>
    </div>
    <div class="rounded-xl bg-white p-4 text-center shadow">
        <div class="text-2xl font-bold text-red-600">{{ $this->cancelledBookings }}</div>
        <div class="mt-1 text-xs text-gray-500">Cancelled</div>
    </div>
</div>
