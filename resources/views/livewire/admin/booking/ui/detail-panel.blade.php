@if ($showDetailPanel)
    <div class="w-full flex-shrink-0 lg:w-[350px] xl:w-[400px]">
        <div class="sticky top-20 flex min-h-[400px] flex-col justify-between rounded-xl border border-gray-100 bg-white p-8 shadow-xl"
            x-data="{ showConfirm: false, showCancel: false, showEdit: false }" @close-edit-modal.window="showEdit = false">
            <button class="absolute right-3 top-3 text-xl text-gray-400 hover:text-gray-700" wire:click="closeDetailPanel"
                title="Close">&times;</button>
            <div>
                @if ($isAddMode)
                    <form class="space-y-4" wire:submit.prevent="createBookingFromPanel">
                        <div>
                            <label class="block text-sm font-medium">Court</label>
                            <select class="w-full rounded border p-2" wire:model="panelAddForm.court_id" required>
                                <option value="">Select Court</option>
                                @foreach ($panelAvailableCourts as $court)
                                    <option value="{{ $court['id'] }}"
                                        @if ($court['is_booked']) disabled @endif>
                                        {{ $court['name'] }} @if ($court['is_booked'])
                                            (Booked)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Tenant</label>
                            <select class="w-full rounded border p-2" wire:model="panelAddForm.tenant_id" required>
                                <option value="">Select Tenant</option>
                                @foreach ($panelTenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Date</label>
                            <input class="w-full rounded border bg-gray-100 p-2" type="text"
                                value="{{ $panelAddForm['date'] }}" readonly>
                            @php
                                $selectedDate = \Carbon\Carbon::parse($panelAddForm['date']);
                                $bookingType = $this->getDateBookingType($selectedDate);
                                $isToday = $selectedDate->isToday();
                                $isPast = $selectedDate->isPast();
                            @endphp

                            <div class="mt-2">
                                <label class="inline-flex items-center text-sm">
                                    <input class="rounded border-gray-300" type="checkbox"
                                        wire:model.live="panelAddForm.override_booking_type" />
                                    <span class="ml-2">Override booking type</span>
                                </label>
                            </div>

                            @if (!$panelAddForm['override_booking_type'])
                                <div class="mt-1 text-xs">
                                    @if ($isPast)
                                        <span class="text-red-600">⚠️ Past date - booking not allowed</span>
                                    @elseif($isToday)
                                        <span class="text-orange-600">⚠️ Today - only future time slots available</span>
                                    @elseif($bookingType === 'free')
                                        <span class="text-green-600">🆓 Free booking</span>
                                    @elseif($bookingType === 'premium')
                                        <span class="text-purple-600">⭐ Premium booking</span>
                                    @else
                                        <span class="text-gray-600">🔒 No booking available for this date</span>
                                    @endif
                                </div>
                            @else
                                <div class="mt-1 text-xs">
                                    <label class="block text-sm font-medium">Booking type</label>
                                    <select class="w-full rounded border bg-gray-100 p-2"
                                        wire:model="panelAddForm.booking_type">
                                        <option value="free">🆓 Free booking</option>
                                        <option value="premium">⭐ Premium booking</option>
                                    </select>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Time</label>
                            <input class="w-full rounded border bg-gray-100 p-2" type="text"
                                value="{{ $panelAddForm['start_time'] }} - {{ $panelAddForm['end_time'] }}" readonly>
                            @php
                                $isPeak =
                                    \Carbon\Carbon::createFromFormat('H:i', $panelAddForm['start_time'])->hour >= 18;
                            @endphp
                            @if ($isPeak)
                                <div class="mt-1 text-xs text-orange-600">💡 Lights required (peak hours)</div>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Notes</label>
                            <textarea class="w-full rounded border p-2" wire:model="panelAddForm.notes"></textarea>
                        </div>
                        @if ($panelAddError)
                            <div class="mt-2 text-sm text-red-600">{{ $panelAddError }}</div>
                        @endif
                        <div class="flex justify-end gap-2">
                            <button class="rounded border px-4 py-2" type="button"
                                wire:click="cancelAddBooking">Cancel</button>
                            <button class="rounded bg-green-600 px-4 py-2 text-white" type="submit">Create
                                Booking</button>
                        </div>
                    </form>
                @elseif($selectedBooking)
                    @php
                        $bookingDateTime = $selectedBooking->date
                            ->copy()
                            ->setTime($selectedBooking->start_time->hour, $selectedBooking->start_time->minute);
                        $isPastBooking = $bookingDateTime->isPast();
                    @endphp
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <div class="text-xs text-gray-400">Prepared for</div>
                            <div class="text-lg font-bold text-gray-800">{{ $selectedBooking->tenant->name }}</div>
                            <div class="text-xs text-gray-500">{{ $selectedBooking->tenant->email }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-400">Date</div>
                            <div class="flex items-center gap-2">
                                <div @class([
                                    'font-semibold',
                                    'text-gray-700' => !$isPastBooking,
                                    'text-gray-500' => $isPastBooking,
                                ])>{{ $selectedBooking->date->format('d F, Y') }}</div>
                                @if ($isPastBooking)
                                    <span class="text-sm text-red-500">🔒</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="mb-1 text-xs text-gray-400">Court</div>
                        <div class="font-semibold text-gray-700">{{ $selectedBooking->court->name ?? '-' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="mb-1 text-xs text-gray-400">Time</div>
                        <div class="font-semibold text-gray-700">{{ $selectedBooking->start_time->format('H:i') }} -
                            {{ $selectedBooking->end_time->format('H:i') }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="mb-1 text-xs text-gray-400">Status</div>
                        @php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'confirmed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                            ];
                            $colorClass = $statusColors[$selectedBooking->status->value] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <span class="{{ $colorClass }} inline-flex px-2 py-1 text-xs font-semibold">
                            {{ ucfirst($selectedBooking->status->value) }}
                        </span>
                    </div>
                    <div class="mb-4">
                        <div class="mb-1 text-xs text-gray-400">Type</div>
                        <div class="flex items-center gap-2">
                            <span @class([
                                'inline-flex items-center px-2 py-1 text-xs font-medium rounded-full',
                                'bg-green-100 text-green-800' => $selectedBooking->booking_type === 'free',
                                'bg-purple-100 text-purple-800' =>
                                    $selectedBooking->booking_type === 'premium',
                            ])>
                                @if ($selectedBooking->booking_type === 'free')
                                    🆓 Free
                                @else
                                    ⭐ Premium
                                @endif
                            </span>
                            <span
                                class="font-semibold text-gray-700">{{ ucfirst($selectedBooking->booking_type) }}</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="mb-1 text-xs text-gray-400">Light Required</div>
                        <div class="font-semibold text-gray-700">
                            {{ $selectedBooking->is_light_required ? 'Yes' : 'No' }}</div>
                    </div>
                    @if ($selectedBooking->notes)
                        <div class="mb-4">
                            <div class="mb-1 text-xs text-gray-400">Notes</div>
                            <div class="text-gray-600">{{ $selectedBooking->notes }}</div>
                        </div>
                    @endif

                    <!-- User Action Information -->
                    @if ($selectedBooking->approved_by)
                        <div class="mb-4">
                            <div class="mb-1 text-xs text-gray-400">Confirmed By</div>
                            <div class="font-semibold text-gray-700">
                                {{ $selectedBooking->approver->name ?? 'Unknown User' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $selectedBooking->approved_at ? $selectedBooking->approved_at->format('d M Y, H:i') : '' }}
                            </div>
                        </div>
                    @endif

                    @if ($selectedBooking->cancelled_by)
                        <div class="mb-4">
                            <div class="mb-1 text-xs text-gray-400">Cancelled By</div>
                            <div class="font-semibold text-gray-700">
                                {{ $selectedBooking->canceller->name ?? 'Unknown User' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $selectedBooking->cancelled_at ? $selectedBooking->cancelled_at->format('d M Y, H:i') : '' }}
                            </div>
                        </div>
                    @endif

                    @if ($selectedBooking->edited_by)
                        <div class="mb-4">
                            <div class="mb-1 text-xs text-gray-400">Last Edited By</div>
                            <div class="font-semibold text-gray-700">
                                {{ $selectedBooking->editor->name ?? 'Unknown User' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $selectedBooking->edited_at ? $selectedBooking->edited_at->format('d M Y, H:i') : '' }}
                            </div>
                        </div>
                    @endif

                    @if ($selectedBooking->cancellation_reason)
                        <div class="mb-4">
                            <div class="mb-1 text-xs text-gray-400">Cancellation Reason</div>
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-gray-600">
                                {{ $selectedBooking->cancellation_reason }}
                            </div>
                        </div>
                    @endif
                @endif
            </div>
            @if (!$isAddMode && $selectedBooking)
                @php
                    $bookingDateTime = $selectedBooking->date
                        ->copy()
                        ->setTime($selectedBooking->start_time->hour, $selectedBooking->start_time->minute);
                    $isPastBooking = $bookingDateTime->isPast();
                @endphp

                @if (!$isPastBooking)
                    <div class="mt-6 flex flex-col gap-2">
                        <!-- Edit Button -->
                        <button
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                            wire:click="edit({{ $selectedBooking->id }})">
                            <svg class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                </path>
                            </svg>
                            Edit Booking
                        </button>
                        <!-- Confirm Button (only show if not already confirmed) -->
                        @if ($selectedBooking->status->value !== 'confirmed')
                            <button
                                class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700"
                                x-on:click="showConfirm = true">
                                <svg class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                Confirm Booking
                            </button>
                        @endif
                        <!-- Cancel Button (only show if not already cancelled) -->
                        @if ($selectedBooking->status->value !== 'cancelled')
                            <button
                                class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700"
                                x-on:click="showCancel = true">
                                <svg class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Cancel Booking
                            </button>
                        @endif
                    </div>
                @else
                    <!-- Past Booking Notice -->
                    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="text-sm font-medium text-gray-700">Past Booking</div>
                                <div class="text-xs text-gray-500">This booking has already passed. No actions can be
                                    performed.</div>
                            </div>
                        </div>
                    </div>
                @endif
                <!-- Restore Edit, Confirm, and Cancel Modals -->
                <x-modal :show="'showEditModal'" :title="'Edit Booking'">
                    <form class="space-y-4" wire:submit.prevent="updateBooking">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select class="mt-1 block w-full rounded border border-gray-300 p-2"
                                wire:model.defer="editForm.status" required>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            @error('editForm.status')
                                <span class="text-xs text-red-600">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="rounded border-gray-300" id="is_light_required" type="checkbox"
                                wire:model.defer="editForm.is_light_required" />
                            <label class="text-sm font-medium text-gray-700" for="is_light_required">Light
                                Required</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea class="mt-1 block w-full rounded border border-gray-300 p-2" wire:model.defer="editForm.notes"></textarea>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button class="rounded border px-4 py-2" type="button"
                                wire:click="closeEditModal">Cancel</button>
                            <button class="rounded bg-blue-600 px-4 py-2 text-white" type="submit">Save</button>
                        </div>
                    </form>
                </x-modal>
                <x-modal :alpineShow="'showConfirm'" :title="'Confirm Booking'">
                    <p class="mb-4">Are you sure you want to confirm this booking for
                        <strong>{{ $selectedBooking->tenant->name ?? '' }}</strong> on
                        {{ $selectedBooking->date->format('d F Y') ?? '' }} at
                        {{ $selectedBooking->start_time->format('H:i') ?? '' }}?
                    </p>
                    <div class="flex justify-end gap-2">
                        <button class="rounded border px-4 py-2" type="button"
                            @click="showConfirm = false">Cancel</button>
                        <button class="rounded bg-green-600 px-4 py-2 text-white" type="button"
                            wire:click="confirmBooking({{ $selectedBooking->id }})"
                            @click="showConfirm = false">Confirm</button>
                    </div>
                </x-modal>
                <x-modal :alpineShow="'showCancel'" :title="'Cancel Booking'">
                    <p class="mb-4">Are you sure you want to cancel this booking for
                        <strong>{{ $selectedBooking->tenant->name ?? '' }}</strong> on
                        {{ $selectedBooking->date->format('d F Y') ?? '' }} at
                        {{ $selectedBooking->start_time->format('H:i') ?? '' }}?
                    </p>
                    <div class="flex justify-end gap-2">
                        <button class="rounded border px-4 py-2" type="button" @click="showCancel = false">Keep
                            Booking</button>
                        <button class="rounded bg-red-600 px-4 py-2 text-white" type="button"
                            wire:click="openCancelModal({{ $selectedBooking->id }})"
                            @click="showCancel = false">Cancel Booking</button>
                    </div>
                </x-modal>
            @endif
        </div>
    </div>
@endif
