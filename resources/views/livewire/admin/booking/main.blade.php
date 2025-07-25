<div class="mx-auto max-w-7xl p-8 w-full">
    <!-- Left Column: Dashboard, Filters, Bookings Table -->
    <div>
        <!-- Dashboard Stats -->
        @include('livewire.admin.booking.ui.stats')

        <!-- Todays Bookings Section -->
        @include('livewire.admin.booking.ui.todays-bookings')

        <!-- Upcoming Bookings Preview -->
        @include('livewire.admin.booking.ui.upcoming-bookings-preview')

        <!-- Court Filter Tabs -->
        @include('livewire.admin.booking.ui.court-filter-tabs')

        <!-- View Toggle and Export -->
        @include('livewire.admin.booking.ui.view-toggle')

        <div class="flex flex-col xl:flex-row relative gap-6">
            <div class="flex-1 min-w-0">
                @if($viewMode === 'table')
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
