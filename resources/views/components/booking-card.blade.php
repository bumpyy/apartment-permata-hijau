@props(['booking'])

@php
    $id = $booking?->id ?? uniqid();
    $tenantName = $booking?->tenant?->name ?? 'AVAILABLE';
    $tenant = $booking?->tenant ?? null;
    $status = $booking->status ?? '';
    $bookingType = $booking->booking_type ?? '';
    $startTime = $booking->start_time ?? null;
    $endTime = $booking->end_time ?? null;
    $notes = $booking->notes ?? '';
@endphp

<div @class([
    'group relative rounded-xl p-4 transition-all duration-200  hover:shadow-md',
    'bg-gray-50 hover:bg-gray-100' => !$tenant,
    'bg-blue-100 hover:bg-blue-200' =>
        $tenant && $status === \App\Enum\BookingstatusEnum::PENDING,
    'bg-blue-100 hover:bg-blue-200' =>
        $tenant && $status === \App\Enum\BookingstatusEnum::CONFIRMED,
    'bg-blue-100 hover:bg-blue-200' =>
        $tenant && $status === \App\Enum\BookingStatusEnum::CANCELLED,
]) key="{{ $id }}">
    <div class="flex items-center gap-4">
        @if ($tenant && $tenant->getFirstMediaUrl('profile_picture'))
            <img class="h-16 w-16 rounded-full border border-gray-300 object-cover"
                src="{{ $tenant->getFirstMediaUrl('profile_picture') }}" alt="{{ $tenant }}" />
        @else
            <div
                class="flex h-16 w-16 items-center justify-center rounded-full border border-gray-300 bg-gray-200 text-2xl font-bold text-gray-500">
                @if ($tenant)
                    {{ $tenant->initials() }}
                @else
                    <flux:icon.check-circle class="h-6 w-6 text-green-600" />
                @endif
            </div>
        @endif



        <div class="flex-1">
            <div class="mb-1 flex items-center gap-2">
                <h3 class="font-medium text-gray-900">{{ $tenantName }}</h3>
                <span class="flex items-center gap-1" class="rounded-full px-2 py-1 text-xs font-medium"
                    @class([
                        'bg-emerald-50 text-emerald-700 border-emerald-200' =>
                            $status === 'confirmed',
                        'bg-amber-50 text-amber-700 border-amber-200' => $status === 'pending',
                        'bg-red-50 text-red-700 border-red-200' => $status === 'cancelled',
                        'bg-gray-50 text-gray-700 border-gray-200' => $status === 'none',
                    ])>
                    {{ $status }} {{ $bookingType }}
                </span>
            </div>
            <p class="mb-1 text-start text-sm text-gray-600">
                {{ $startTime?->format('H:i') }} - {{ $endTime?->format('H:i') }}
            </p>
            @if ($notes)
                <p class="mt-1 text-sm text-gray-500">{{ $notes }}</p>
            @endif
        </div>

        @if (isset($booking->date))
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button
                    class="rounded-lg p-2 text-gray-400 opacity-0 transition-colors duration-200 hover:bg-white hover:text-gray-600 group-hover:opacity-100"
                    @click="open = !open">
                    <flux:icon.chevron-down class="h-5 w-5" />
                </button>

                <div class="absolute right-0 top-full z-10 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-2 shadow-lg"
                    id="menu-{{ $id }}" x-show="open">
                    <button
                        class="flex w-full items-center gap-3 px-4 py-2 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50"
                        class="h-4 w-4" @click="open = false">
                        <flux:icon.pencil />
                        Edit Booking
                    </button>
                    @if ($status === 'pending')
                        <button
                            class="flex w-full items-center gap-3 px-4 py-2 text-sm text-emerald-700 transition-colors duration-150 hover:bg-emerald-50"
                            @click="open = false">
                            <flux:icon.check class="h-4 w-4" />
                            Confirm Booking
                        </button>
                    @endif
                    @if ($booking)
                        <button
                            class="flex w-full items-center gap-3 px-4 py-2 text-sm text-red-700 transition-colors duration-150 hover:bg-red-50"
                            @click="open = false" wire:click="$parent.cancelBooking({{ $id }})">
                            <flux:icon.trash class="h-4 w-4" />
                            Cancel Booking
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
