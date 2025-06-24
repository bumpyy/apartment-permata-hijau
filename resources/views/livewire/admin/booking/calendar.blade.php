<div class="h-fit rounded-xl border border-gray-100 bg-white p-6 shadow-sm" x-data="{
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
}"
    x-init="init('{{ $currentYear }}', '{{ $currentMonth }}')">
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900" x-text="monthName + ' ' + year"></h2>
        <div class="flex items-center space-x-2">
            <button class="rounded-lg p-2 transition-colors duration-200 hover:bg-gray-100"
                @click="prevMonth"><flux:icon.chevron-left class="h-5 w-5 text-gray-600" /></button>
            <button class="rounded-lg p-2 transition-colors duration-200 hover:bg-gray-100"
                @click="nextMonth"><flux:icon.chevron-right class="h-5 w-5 text-gray-600" /></button>
        </div>
    </div>

    <div class="mb-2 grid grid-cols-7 gap-1">
        <template x-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day">
            <div class="py-2 text-center text-sm font-medium text-gray-500" x-text="day"></div>
        </template>
    </div>

    <div class="grid grid-cols-7 gap-1">
        <template x-for="(blank, index) in blankDays" :key="'blank-' + index">
            <div class=""></div>
        </template>

        <template x-for="day in daysInMonth" :key="'day-' + day">
            <button class="relative rounded-lg p-2 text-sm transition-all duration-200 hover:scale-105"
                :class="{
                    'bg-blue-50 text-blue-600 font-semibold border-2 border-blue-200': isToday(day),
                    'bg-blue-600 text-white font-semibold shadow-lg': selectedDate === formatDate(day)
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

            </button>
        </template>
    </div>
</div>
