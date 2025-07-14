<!-- Filter Bar -->
<div class="mb-6 flex flex-col gap-4 rounded-xl bg-white p-4 shadow md:flex-row md:items-center">
    <div class="flex flex-1 items-center gap-2">
        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
        </svg>
        <input class="w-full rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200"
            wire:model.live.debounce.500ms="search" type="search" placeholder="Search bookings..." />
    </div>
    <select class="rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200" wire:model.live="statusFilter">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="cancelled">Cancelled</option>
    </select>
    <input class="rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200" type="date"
        wire:model.live="dateFilter" />
    <div class="flex items-center gap-2">
        <input class="rounded border-gray-300" id="excludeCancelled" type="checkbox"
            wire:model.live="excludeCancelled" />
        <label class="whitespace-nowrap text-sm text-gray-700" for="excludeCancelled">Exclude
            Cancelled</label>
    </div>
</div>

<!-- Table Tabs -->
<div class="mb-4 flex gap-2">
    <button
        class="{{ $tableTab === 'active' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} rounded px-4 py-2 font-medium transition-colors focus:outline-none"
        wire:click="setTableTab('active')">
        📅 Active Bookings ({{ $this->activeBookingsCount }})
    </button>
    <button
        class="{{ $tableTab === 'past' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} rounded px-4 py-2 font-medium transition-colors focus:outline-none"
        wire:click="setTableTab('past')">
        📚 Past Bookings ({{ $this->pastBookingsCount }})
    </button>
</div>

<!-- Table Header -->
<div class="mb-4 rounded-xl bg-white p-4 shadow">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @if ($tableTab === 'active')
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-green-500"></div>
                    <h3 class="text-lg font-semibold text-gray-800">Active Bookings</h3>
                    <span class="text-sm text-gray-500">(Future and todays bookings)</span>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-orange-500"></div>
                    <h3 class="text-lg font-semibold text-gray-800">Past Bookings</h3>
                    <span class="text-sm text-gray-500">(Historical booking records)</span>
                </div>
            @endif
        </div>
        <div class="text-sm text-gray-500">
            Showing {{ $this->bookings->count() }} of {{ $this->bookings->total() }} bookings
        </div>
    </div>
</div>

<!-- Bookings Table -->
<div class="cursor-grab overflow-x-auto rounded-xl bg-white shadow active:cursor-grabbing" x-data="{
    isDown: false,
    startX: 0,
    scrollLeft: 0
}"
    x-on:mousedown="isDown = true; startX = $event.pageX - $el.offsetLeft; scrollLeft = $el.scrollLeft; $el.classList.add('cursor-grabbing')"
    x-on:mouseleave="isDown = false; $el.classList.remove('cursor-grabbing')"
    x-on:mouseup="isDown = false; $el.classList.remove('cursor-grabbing')"
    x-on:mousemove="$event.preventDefault(); if (isDown) { const x = $event.pageX - $el.offsetLeft; const walk = (x - startX) * 2; $el.scrollLeft = scrollLeft - walk; }"
    x-on:selectstart="$event.preventDefault()">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Tenant</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Reference</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Court</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @foreach ($this->groupedBookings as $date => $courts)
                <tr>
                    <td class="border-b border-t border-gray-200 bg-gray-100 px-6 py-2 text-base font-bold"
                        colspan="7">{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</td>
                </tr>
                @foreach ($courts as $courtName => $bookings)
                    <tr>
                        <td class="border-b border-t border-blue-200 bg-blue-50 px-6 py-2 text-sm font-semibold"
                            colspan="7">Court: {{ $courtName }}</td>
                    </tr>
                    @foreach ($bookings as $booking)
                        @php
                            $isPastBooking = $booking->date->isPast();
                        @endphp
                        <tr wire:click="handleBookingCardClick({{ $booking->id }})" @class([
                            'hover:bg-blue-50 cursor-pointer',
                            'bg-gray-50' => $isPastBooking && $tableTab === 'past',
                            'opacity-75' => $isPastBooking && $tableTab === 'past',
                        ])>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="font-semibold text-gray-900">
                                    {{ $booking->tenant->name }}</div>
                                <div class="text-xs text-gray-500">{{ $booking->tenant->email }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                {{ $booking->booking_reference }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                {{ $booking->court->name ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                <div class="flex items-center gap-2">
                                    <span>{{ $booking->date->format('Y-m-d') }}</span>
                                    @if ($isPastBooking && $tableTab === 'past')
                                        <span
                                            class="rounded-full bg-orange-100 px-2 py-1 text-xs text-orange-600">Past</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                {{ $booking->start_time instanceof \Carbon\Carbon ? $booking->start_time->format('H:i') : $booking->start_time }}
                                -
                                {{ $booking->end_time instanceof \Carbon\Carbon ? $booking->end_time->format('H:i') : $booking->end_time }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                    ];
                                    $colorClass = $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span @class([
                                    'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                                    $colorClass,
                                ])>
                                    {{ ucfirst($booking->status->value) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

</div>

<div class="p-4">
    {{ $this->bookings->links() }}
</div>
