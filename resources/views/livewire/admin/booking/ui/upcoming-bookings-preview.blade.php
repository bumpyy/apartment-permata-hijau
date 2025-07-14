@if ($this->upcomingBookings->isNotEmpty())
    <div class="mb-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center text-xl font-bold text-gray-900">
                <svg class="mr-2 h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                    </path>
                </svg>
                Upcoming Bookings (Next 10)
            </h2>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Court
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tenant
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Time
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($this->upcomingBookings as $date => $bookings)
                            <tr class="bg-gray-50">
                                <td class="px-4 py-2 text-sm font-semibold text-gray-700" colspan="5">
                                    {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}
                                </td>
                            </tr>
                            @foreach ($bookings as $booking)
                                <tr class="cursor-pointer hover:bg-blue-50"
                                    wire:click="showDetail({{ $booking->id }})">
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $booking->date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $booking->court->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $booking->tenant->name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $booking->start_time->format('H:i') }} -
                                        {{ $booking->end_time->format('H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                                            'bg-green-100 text-green-800' =>
                                                $booking->status === \App\Enum\BookingStatusEnum::CONFIRMED,
                                            'bg-yellow-100 text-yellow-800' =>
                                                $booking->status === \App\Enum\BookingStatusEnum::PENDING,
                                        ])>
                                            {{ ucfirst($booking->status->value) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
