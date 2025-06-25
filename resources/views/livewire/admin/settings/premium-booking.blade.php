<?php

use App\Models\PremiumDateOverride;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
    #[Layout('components.backend.layouts.app')]
    #[Title('Premium Booking Overrides')]
    class extends Component
    {
        public string $date = '';

        public string $note = '';

        public $overrides = [];

        public $premiumBookingDate;

        public function mount(): void
        {
            $this->refreshOverrides();
            $this->premiumBookingDate = \App\Models\PremiumDateOverride::getCurrentMonthPremiumDate();
        }

        public function refreshOverrides(): void
        {
            $overrides = PremiumDateOverride::orderBy('date')->get();
            $this->overrides = $overrides
                ->groupBy(fn ($item) => \Carbon\Carbon::parse($item->date)->format('Y'))
                ->toArray();
        }

        public function addOverride(): void
        {
            $this->validate([
                'date' => 'required|date',
                'note' => 'nullable|string|max:255',
            ]);

            $month = \Carbon\Carbon::parse($this->date)->month;
            $year = \Carbon\Carbon::parse($this->date)->year;

            $existing = PremiumDateOverride::whereMonth('date', $month)
                ->whereYear('date', $year)
                ->first();

            if ($existing) {
                $existing->update([
                    'date' => $this->date,
                    'note' => $this->note,
                ]);
            } else {
                PremiumDateOverride::create([
                    'date' => $this->date,
                    'note' => $this->note,
                ]);
            }

            $this->date = '';
            $this->note = '';
            $this->refreshOverrides();
            $this->dispatch('override-added');
        }

        public function deleteOverride($id): void
        {
            PremiumDateOverride::find($id)?->delete();
            $this->refreshOverrides();
            $this->dispatch('override-deleted');
        }
    };
?>

<div class="min-h-screen">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600 py-6 text-center text-white mb-8 rounded-xl shadow">
            <div class="absolute inset-0 bg-black opacity-10"></div>
            <div class="relative z-10 flex flex-col items-center">
                <div class="flex items-center gap-3 mb-2">
                    <flux:icon.calendar-days class="h-8 w-8 text-white" />
                    <h2 class="text-2xl font-bold">Premium Booking Date</h2>
                </div>

                @php
                    // Flatten and sort overrides
                    $allOverrides = collect($overrides)->flatten(1)->sortBy('date')->values();
                    $nextOverride = $allOverrides->firstWhere('date', '>=', now()->toDateString());
                    if (!$nextOverride && $allOverrides->count()) {
                        $nextOverride = $allOverrides->last();
                    }
                    // Group by year only if there are multiple years
                    $years = $allOverrides->map(fn($o) => \Carbon\Carbon::parse($o['date'])->year)->unique();
                    $groupedOverrides = $years->count() > 1
                        ? $allOverrides->groupBy(fn($o) => \Carbon\Carbon::parse($o['date'])->year)
                        : collect(['' => $allOverrides]);
                @endphp

                @if ($allOverrides->count())
                    <div class="text-lg">
                        ⭐ Premium registration opens:
                        <span class="font-bold">{{ \Carbon\Carbon::parse($nextOverride['date'])->format('F j, Y') }}</span>
                        @if ($nextOverride['note'])
                            <span class="ml-2 italic text-blue-100">({{ $nextOverride['note'] }})</span>
                        @endif
                    </div>
                @else
                    <div class="text-lg">
                        ⭐ No override set. Default: <span class="font-bold">{{ now()->format('F') }} 25</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <!-- Calendar/Form Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <form wire:submit.prevent="addOverride" class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Override Date</label>
                    <div x-data="{
                        year: {{ now()->year }},
                        month: {{ now()->month }},
                        daysInMonth: [],
                        blankDays: [],
                        monthName: '',
                        selectedDate: @entangle('date').defer,
                        overrideDates: @entangle('overrides').defer,
                        getFlatOverrideDates() {
                            // Flatten the grouped overrides to a flat array of date strings
                            if (!this.overrideDates) return [];
                            return Object.values(this.overrideDates).flat().map(o => o.date);
                        },
                        init() {
                            this.calculate();
                            if (!this.selectedDate) {
                                this.selectedDate = this.formatDate(new Date().getDate());
                            }
                            // Listen for Livewire events to refresh overrideDates
                            window.addEventListener('override-added', () => { this.$nextTick(() => this.$forceUpdate && this.$forceUpdate()); });
                            window.addEventListener('override-deleted', () => { this.$nextTick(() => this.$forceUpdate && this.$forceUpdate()); });
                        },
                        calculate() {
                            const date = new Date(this.year, this.month - 1);
                            this.monthName = date.toLocaleString('default', { month: 'long' });
                            const days = new Date(this.year, this.month, 0).getDate();
                            const firstDay = new Date(this.year, this.month - 1, 1).getDay();
                            this.blankDays = Array.from({ length: firstDay });
                            this.daysInMonth = Array.from({ length: days }, (_, i) => i + 1);
                        },
                        nextMonth() {
                            this.month++;
                            if (this.month > 12) { this.month = 1; this.year++; }
                            this.calculate();
                        },
                        prevMonth() {
                            this.month--;
                            if (this.month < 1) { this.month = 12; this.year--; }
                            this.calculate();
                        },
                        isToday(day) {
                            const today = new Date();
                            return today.getDate() === day && today.getMonth() + 1 === this.month && today.getFullYear() === this.year;
                        },
                        formatDate(day) {
                            return `${this.year}-${String(this.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        },
                        selectDay(day) {
                            const formatted = this.formatDate(day);
                            this.selectedDate = formatted;
                            this.$wire.set('date', formatted);
                        },
                        isOverride(day) {
                            return this.getFlatOverrideDates().includes(this.formatDate(day));
                        },
                        selectOverride(date) {
                            const d = new Date(date);
                            this.year = d.getFullYear();
                            this.month = d.getMonth() + 1;
                            this.calculate();
                            this.selectedDate = date;
                            this.$wire.set('date', date);
                        },
                    }" x-init="init()">
                        <div class="mb-4 flex items-center justify-between">
                            <button type="button" class="rounded-lg p-2 transition-colors duration-200 hover:bg-gray-100" @click="prevMonth"><svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></button>
                            <h3 class="text-lg font-semibold text-gray-900" x-text="monthName + ' ' + year"></h3>
                            <button type="button" class="rounded-lg p-2 transition-colors duration-200 hover:bg-gray-100" @click="nextMonth"><svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></button>
                        </div>
                        <div class="mb-2 grid grid-cols-7 gap-1">
                            <template x-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day">
                                <div class="py-2 text-center text-sm font-medium text-gray-500" x-text="day"></div>
                            </template>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(blank, index) in blankDays" :key="'blank-' + index">
                                <div></div>
                            </template>
                            <template x-for="day in daysInMonth" :key="'day-' + day">
                                <button type="button"
                                    class="relative rounded-lg p-2 text-sm transition-all duration-200 hover:scale-105"
                                    :class="{
                                        'bg-blue-50 text-blue-600 font-semibold border-2 border-blue-200': isToday(day),
                                        'bg-blue-600 text-white font-semibold shadow-lg scale-110': selectedDate === formatDate(day),
                                        'bg-purple-600 text-white font-bold ring-2 ring-purple-300': isOverride(day),
                                        'cursor-not-allowed opacity-50': new Date(year, month - 1, day) < new Date() - 1 && !isToday(day),
                                    }"
                                    @click="selectDay(day)"
                                    :disabled="new Date(year, month - 1, day) < new Date() - 1 && !isToday(day)">
                                    <span x-text="day"></span>
                                    <template x-if="isOverride(day)">
                                        <span class="absolute top-0 right-0 h-2 w-2 bg-purple-500 rounded-full border-2 border-white"></span>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                        <input type="text" wire:model="note" class="w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 px-3 py-2 text-sm shadow-sm" maxlength="255" placeholder="Add a note for this override..." />
                    </div>
                    <div>
                        <button type="submit" class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-all duration-200 shadow" wire:loading.attr="disabled">
                            <svg wire:loading.remove class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Add Override
                            <span wire:loading>
                                <svg class="inline h-5 w-5 animate-spin ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                    <x-action-message on="override-added">
                        <div class="animate-fade-in text-green-600 font-bold">Override added!</div>
                    </x-action-message>
                </form>
            </div>

            <!-- List Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">All Premium Date Overrides</h2>
                @forelse ($groupedOverrides as $year => $items)
                    @if ($year)
                        <div class="mb-2 text-xl text-blue-800 font-bold border-b border-blue-100 pb-1">{{ $year }}</div>
                    @endif
                    <div class="grid gap-4">
                        @foreach ($items as $override)
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4 shadow-sm">
                                <div>
                                    <div class="font-semibold text-gray-900 text-base cursor-pointer hover:underline" @click="$el.closest('[x-data]').__x.$data.selectOverride('{{ $override['date'] }}')">
                                        {{ \Carbon\Carbon::parse($override['date'])->format('F j, Y') }}
                                    </div>
                                    @if ($override['note'])
                                        <div class="text-gray-500 text-sm mt-1">{{ $override['note'] }}</div>
                                    @endif
                                </div>
                                <button wire:click="deleteOverride({{ $override['id'] }})" class="flex items-center gap-1 text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 rounded-lg px-3 py-2 transition-all duration-200">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    <span>Delete</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="py-2 text-gray-500">No overrides set.</div>
                @endforelse
                <x-action-message on="override-deleted">
                    <div class="animate-fade-in text-red-600 font-bold">Override deleted!</div>
                </x-action-message>
            </div>
        </div>
    </div>
</div>
