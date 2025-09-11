<div class="mx-auto w-full max-w-7xl p-4 md:p-8">
    <!-- Left Column: Dashboard, Filters, Bookings Table -->
    <div>
        <!-- Dashboard Stats -->
        @include('livewire.admin.booking.ui.stats')

        <!-- Todays Bookings Section -->
        @include('livewire.admin.booking.ui.todays-bookings')

        {{-- <!-- Upcoming Bookings Preview -->
        @include('livewire.admin.booking.ui.upcoming-bookings-preview') --}}

        <!-- Court Filter Tabs -->
        @include('livewire.admin.booking.ui.court-filter-tabs')

        <!-- View Toggle and Export -->
        @include('livewire.admin.booking.ui.view-toggle')

        <div class="relative flex flex-col gap-6 xl:flex-row">
            <div class="min-w-0 flex-1">
                @if ($viewMode === 'table')
                    @include('livewire.admin.booking.views.table-view')
                @else
                    @include('livewire.admin.booking.views.calendar-view')
                @endif
            </div>

            @include('livewire.admin.booking.ui.detail-panel')
        </div>
    </div>

    <!-- Cancellation Confirmation Modal -->
    @include('livewire.admin.booking.modals.cancellation-confirmation')

    <!-- Export Modal -->
    @include('livewire.admin.booking.modals.export')
</div>
