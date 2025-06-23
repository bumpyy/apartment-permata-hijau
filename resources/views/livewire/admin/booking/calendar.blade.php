<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm" x-data="{
    year: null,
    month: null,
    daysInMonth: [],
    blankDays: [],
    monthName: '',
    selectedDate: null,
    bookings: @js(array_keys($bookings ?? [])),

    init(y, m) {
        this.year = parseInt(y);
        this.month = parseInt(m);
        this.calculate();
    },
    calculate() {
        const date = new Date(this.year, this.month - 1);
        this.monthName = date.toLocaleString('default', {
            month: 'long'
        });

        const days = new Date(this.year, this.month, 0).getDate();
        const firstDay = new Date(this.year, this.month - 1, 1).getDay();

        this.blankDays = Array.from({
            length: firstDay
        });

        this.daysInMonth = Array.from({
            length: days
        }, (_, i) => i + 1);
    },
    nextMonth() {
        this.month++;
        if (this.month > 12) {
            this.month = 1;
            this.year++;
        }
        this.calculate();
    },
    prevMonth() {
        this.month--;
        if (this.month < 1) {
            this.month = 12;
            this.year--;
        }
        this.calculate();
    },
    isToday(day) {
        const today = new Date();
        return today.getDate() === day && today.getMonth() + 1 === this.month && today.getFullYear() === this
            .year;
    },
    getBookingsForDay(day) {
        const date = this.formatDate(day);

        return this.bookings[date] ?? [];
    },
    formatDate(day) {
        return `${this.year}-${String(this.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    },
    selectDay(day) {
        const formatted = this.formatDate(day);
        this.selectedDate = formatted;
        this.$dispatch('calendar-day-clicked', { date: formatted });
    },
    hasBooking(day) {
        return this.bookings.includes(this.formatDate(day));
    }
}" x-init="init('{{ $currentYear }}', '{{ $currentMonth }}')">
    <div class="mb-4 flex items-center justify-between">
        <button class="cursor-pointer text-gray-500 hover:text-black" @click="prevMonth">&lt;</button>
        <div class="text-lg font-semibold" x-text="monthName + ' ' + year"></div>
        <button class="cursor-pointer text-gray-500 hover:text-black" @click="nextMonth">&gt;</button>
    </div>

    <div class="grid grid-cols-7 gap-2 text-center text-sm font-medium text-gray-500">
        <template x-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day">
            <div x-text="day"></div>
        </template>
    </div>

    <div class="mt-2 grid grid-cols-7 gap-2 text-center">
        <template x-for="(blank, index) in blankDays" :key="'blank-' + index">
            <div class="h-16"></div>
        </template>

        <template x-for="day in daysInMonth" :key="'day-' + day">
            <div class="relative flex h-16 cursor-pointer flex-col items-center justify-center rounded border"
                :class="{
                    'bg-blue-100 border-blue-400': isToday(day),
                    'ring-2 ring-indigo-500 ring-offset-1': selectedDate === formatDate(day)
                }"
                @click="selectDay(day)">
                <span x-text="day"></span>

                <!-- Booking Indicator -->
                <template x-if="getBookingsForDay(day).length">
                    <div class="absolute bottom-1 left-1/2 flex -translate-x-1/2 flex-wrap justify-center gap-0.5">
                        <template x-for="(booking, i) in getBookingsForDay(day)" :key="i">
                            <div class="rounded px-1 py-0.5 text-[10px] text-white"
                                :class="{
                                    'bg-yellow-500': booking.status === 'pending',
                                    'bg-green-600': booking.status === 'confirmed',
                                    'bg-red-600': booking.status === 'cancelled',
                                }"
                                x-text="(booking.type[0] ?? '?').toUpperCase() + ':' + (booking.status[0] ?? '?').toUpperCase()"
                                :title="booking.type + ' / ' + booking.status"></div>
                        </template>
                    </div>
                </template>

            </div>
        </template>
    </div>
</div>
