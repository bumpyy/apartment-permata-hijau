<div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 className="text-lg font-semibold text-gray-900 mb-6">
        Schedule for {{ $selectedDate }}
    </h2>

    <div className="space-y-3">
        @forelse ($bookings as $booking)
            {{-- @dd($booking) --}}
            <div class="group relative rounded-xl bg-gray-50 p-4 transition-all duration-200 hover:bg-gray-100 hover:shadow-md"
                key="{{ $booking->id }}">
                <div class="flex items-center gap-4">
                    {{-- <img class="h-12 w-12 rounded-full border-2 border-white object-cover shadow-sm"
                         src="tenantName{{ $booking->tenant->avatar }}" alt="{{ $booking->tenant->name }}" /> --}}

                    <div class="flex-1">
                        <div class="mb-1 flex items-center gap-2">
                            <h3 class="font-medium text-gray-900">{{ $booking->tenant->name }}</h3>
                            <span class="flex items-center gap-1" @class([
                                'bg-emerald-50 text-emerald-700 border-emerald-200' =>
                                    $booking->status === 'confirmed',
                                'bg-amber-50 text-amber-700 border-amber-200' =>
                                    $booking->status === 'pending',
                                'bg-red-50 text-red-700 border-red-200' => $booking->status === 'cancelled',
                                'bg-gray-50 text-gray-700 border-gray-200' => $booking->status === 'none',
                            ]) <span>
                                {{-- {{ getStatusIcon($booking->status) }} --}}
                                {{ $booking->status }}
                            </span>
                            </span>
                            {{-- <span
                                    class="{{ getTypeColor($booking->type) }} rounded-full px-2 py-1 text-xs font-medium">
                                    {{ $booking->type }}
                                </span> --}}
                        </div>
                        <p class="mb-1 text-sm text-gray-600">
                            {{ $booking->startTime }} - {{ $booking->endTime }}
                        </p>
                        <p class="text-sm font-medium text-gray-700">{{ $booking->property }}</p>
                        @if ($booking->notes)
                            <p class="mt-1 text-sm text-gray-500">{{ $booking->notes }}</p>
                        @endif
                    </div>

                    <div class="relative">
                        <button
                            class="rounded-lg p-2 text-gray-400 opacity-0 transition-colors duration-200 hover:bg-white hover:text-gray-600 group-hover:opacity-100"
                            onclick="document.getElementById('menu-{{ $booking->id }}').classList.toggle('hidden')">
                            <MoreVertical class="h-5 w-5" />
                        </button>

                        <div class="absolute right-0 top-full z-10 mt-2 hidden w-48 rounded-lg border border-gray-200 bg-white py-2 shadow-lg"
                            id="menu-{{ $booking->id }}">
                            <button
                                class="flex w-full items-center gap-3 px-4 py-2 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50"
                                onclick="document.getElementById('menu-{{ $booking->id }}').classList.add('hidden')">
                                <Edit2 class="h-4 w-4" />
                                Edit Booking
                            </button>
                            @if ($booking->status === 'pending')
                                <button
                                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-emerald-700 transition-colors duration-150 hover:bg-emerald-50"
                                    onclick="document.getElementById('menu-{{ $booking->id }}').classList.add('hidden')">
                                    <Check class="h-4 w-4" />
                                    Confirm Booking
                                </button>
                            @endif
                            @if ($booking->status !== 'cancelled')
                                <button
                                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-red-700 transition-colors duration-150 hover:bg-red-50"
                                    onclick="document.getElementById('menu-{{ $booking->id }}').classList.add('hidden')">
                                    <X class="h-4 w-4" />
                                    Cancel Booking
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div className="text-center py-12">
                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Clock className="w-8 h-8 text-gray-400" />
                </div>
                <p className="text-gray-500 text-sm">No bookings scheduled for this date</p>
            </div>
        @endforelse
    </div>

</div>

{{--  --}}
