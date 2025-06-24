<div class="mb-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <div class="flex flex-1 flex-col gap-4 sm:flex-row">
        <div class="relative max-w-md flex-1">
            <flux:icon.magnifying-glass
                class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 transform text-gray-400" />
            <input
                class="w-full rounded-lg border border-gray-200 py-3 pl-10 pr-4 transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                type="text" wire:model.live.debounce.300ms="searchTerm" placeholder="Search..." />
        </div>

        <div class="flex gap-2">
            <select
                class="rounded-lg border border-gray-200 px-4 py-3 transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                wire:model.live="courtFilter">
                <option value="">All Court</option>
                @foreach (\App\Models\Court::all() as $court)
                    <option value="{{ $court->id }}">{{ ucfirst($court->name) }}</option>
                @endforeach
            </select>

            <select
                class="rounded-lg border border-gray-200 px-4 py-3 transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                wire:model.live="statusFilter">
                <option value="">All Status</option>
                <option value="confirmed">Confirmed</option>
                <option value="pending">Pending</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <select
                class="rounded-lg border border-gray-200 px-4 py-3 transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                wire:model.live="typeFilter">
                <option value="">All Types</option>
                <option value="free">Free</option>
                <option value="premium ">Premium</option>
            </select>
        </div>

        <div class="flex gap-3">
            {{-- <button
                class="flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-3 text-gray-700 transition-colors duration-200 hover:bg-gray-200">
                <flux:icon.printer class="h-5 w-5" />
                Export
            </button> --}}

            <button
                class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-white shadow-sm transition-colors duration-200 hover:bg-blue-700 hover:shadow-md">
                <flux:icon.plus class="h-5 w-5" />
                Add Booking
            </button>
        </div>

    </div>
</div>
