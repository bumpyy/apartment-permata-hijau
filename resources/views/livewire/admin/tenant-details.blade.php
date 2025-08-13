<?php

namespace App\Http\Livewire\Admin;

use App\Models\Booking;
use App\Models\Tenant;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.backend.layouts.app')]
class extends Component
{
    public $tenant = null;

    public $tenantId = '';

    public $freeBookings = [];

    public $premiumBookings = [];

    public $pastBookings = [];

    // Edit mode properties
    public $isEditing = false;

    public $editName = '';

    public $editEmail = '';

    public $editPhone = '';

    public $editDisplayName = '';

    public $profilePicture = '';

    public function mount($id = null)
    {
        if ($id) {
            $this->tenantId = $id;
            $this->loadTenantDetails();
        }
    }

    public function loadTenantDetails()
    {
        $this->tenant = Tenant::where('tenant_id', $this->tenantId)->orWhere('id', $this->tenantId)->first();

        if (! $this->tenant) {
            session()->flash('error', 'Tenant not found');

            return;
        }

        $this->loadBookings();
    }

    public function loadBookings()
    {
        $this->freeBookings = $this->tenant->bookings()
            ->where('booking_type', 'free')
            ->where('status', '!=', 'cancelled')
            ->where('date', '>=', Carbon::now())
            ->with('court')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $this->premiumBookings = $this->tenant->bookings()
            ->where('booking_type', 'premium')
            ->where('status', '!=', 'cancelled')
            ->where('date', '>=', Carbon::now())
            ->with('court')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $this->pastBookings = $this->tenant->bookings()
            ->where('date', '<', Carbon::now())
            ->with('court')
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit(10)
            ->get();
    }

    public function startEditing()
    {
        $this->isEditing = true;
        $this->editName = $this->tenant->name;
        $this->editEmail = $this->tenant->email;
        $this->editPhone = $this->tenant->phone ?? '';
        $this->editDisplayName = $this->tenant->display_name ?? '';
        $this->profilePicture = '';
    }

    public function cancelEditing()
    {
        $this->isEditing = false;
        $this->reset(['editName', 'editEmail', 'editPhone', 'editDisplayName']);
    }

    public function saveTenantDetails()
    {
        $this->validate([
            'editName' => 'required|string|max:191',
            'editEmail' => 'required|email|max:191',
            'editPhone' => 'nullable|string|max:20',
            'editDisplayName' => 'nullable|string|max:191',
            'profilePicture' => 'nullable',
        ]);

        $this->tenant->update([
            'name' => $this->editName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone,
            'display_name' => $this->editDisplayName,
        ]);

        if (! empty($this->profilePicture)) {
            $this->tenant->clearMediaCollection('profile_picture');
            $this->tenant->addMedia($this->profilePicture)->toMediaCollection('profile_picture');
            $this->profilePicture = '';
        }

        $this->isEditing = false;
        $this->loadTenantDetails();
        session()->flash('message', 'Tenant details updated successfully!');
    }

    public function removeProfilePicture()
    {
        $this->tenant->clearMediaCollection('profile_picture');
        $this->loadTenantDetails();
        session()->flash('message', 'Profile picture removed successfully!');
    }

    public function confirmPayment($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'confirmed',
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        $this->loadBookings();
        session()->flash('message', 'Payment confirmed successfully!');
    }

    public function denyBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'cancelled',
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        $this->loadBookings();
        session()->flash('message', 'Booking denied successfully!');
    }

    public function cancelBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => auth('admin')->id(),
            'cancelled_at' => now(),
        ]);

        $this->loadBookings();
        session()->flash('message', 'Booking cancelled successfully!');
    }
}

?>

<div class="mx-auto max-w-7xl p-6">
    <!-- Header Section -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tenant Profile</h1>
            <p class="mt-1 text-sm text-gray-600">Manage tenant information and bookings</p>
        </div>
        <div class="flex items-center gap-3">
             <a href="{{ route('admin.tenant.list') }}"
               class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                ← Back to Tenants
            </a>
        </div>
    </div>

    @if (session()->has('message'))
    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
        {{ session('message') }}
    </div>
    @endif

    @if (session()->has('error'))
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
        {{ session('error') }}
    </div>
    @endif

    @if ($tenant)
    <!-- Tenant Profile Section -->
    <div class="mb-8 rounded-lg bg-white p-6 shadow">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Tenant Information</h2>
            @if (!$isEditing)
            <button wire:click="startEditing"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Edit Profile
            </button>
            @endif
        </div>

        @if ($isEditing)
        <!-- Edit Form -->
        <form wire:submit.prevent="saveTenantDetails" class="space-y-4" x-data="{ uploading: false }">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Profile Picture</label>
                    <div class="flex items-center gap-4">
                        @if (is_string($profilePicture) && Str::startsWith($profilePicture, 'livewire-file:'))
                            <img src="{{ \Spatie\LivewireFilepond\WithFilePond::getTemporaryUrl($profilePicture) }}" alt="Profile Picture Preview" class="h-16 w-16 rounded-full object-cover border border-gray-300" />
                        @elseif ($tenant && $tenant->getFirstMediaUrl('profile_picture'))
                            <img src="{{ $tenant->getFirstMediaUrl('profile_picture') }}" alt="Profile Picture" class="h-16 w-16 rounded-full object-cover border border-gray-300" />
                        @else
                            <div class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-500 border border-gray-300">
                                {{ $tenant ? $tenant->initials() : '?' }}
                            </div>
                        @endif

                        <div class="flex-1">
                            <x-filepond::upload
                                wire:model="profilePicture"
                                allow-image-preview
                                max-files="1"
                                :files="
                                    $tenant && $tenant->getFirstMediaUrl('profile_picture')
                                        ? [
                                            [
                                                'source' => $tenant->getFirstMediaUrl('profile_picture'),
                                                'options' => [
                                                    'type' => 'local',
                                                ],
                                            ]
                                        ]
                                        : []
                                "
                                x-on:filepond-processing="uploading = true"
                                x-on:filepond-processfile="uploading = false"
                                x-on:filepond-removefile="uploading = false"
                            />
                            @error('profilePicture') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                            @if ($tenant && $tenant->getFirstMediaUrl('profile_picture'))
                                <button type="button" wire:click="removeProfilePicture" class="mt-2 text-xs text-red-600 hover:underline">Remove</button>
                            @endif
                            <div x-show="uploading" class="text-sm text-blue-600 mt-2">Uploading file, please wait...</div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" wire:model="editName"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    @error('editName') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Display Name</label>
                    <input type="text" wire:model="editDisplayName"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    @error('editDisplayName') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" wire:model="editEmail"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    @error('editEmail') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" wire:model="editPhone"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    @error('editPhone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="flex items-center gap-3 pt-4">
                <button type="submit"
                        class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 flex items-center gap-2"
                        :disabled="uploading"
                        :class="{ 'opacity-50 cursor-not-allowed': uploading }">
                    <svg x-show="uploading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" x-cloak>
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Save Changes</span>
                </button>
                <button type="button" wire:click="cancelEditing"
                        class="rounded-lg bg-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-400">
                    Cancel
                </button>
            </div>
        </form>
        @else
        <!-- Display Information -->
        <div class="flex items-center gap-4 mb-6">
            @if ($tenant && $tenant->getFirstMediaUrl('profile_picture'))
                <img src="{{ $tenant->getFirstMediaUrl('profile_picture') }}" alt="Profile Picture" class="h-16 w-16 rounded-full object-cover border border-gray-300" />
            @else
                <div class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-500 border border-gray-300">
                    {{ $tenant ? $tenant->initials() : '?' }}
                </div>
            @endif
        </div>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="space-y-4">
                <div>
                    <span class="text-sm font-medium text-gray-500">Full Name</span>
                    <p class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Display Name</span>
                    <p class="text-lg font-semibold text-gray-900">{{ $tenant->display_name ?? 'Not set' }}</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <span class="text-sm font-medium text-gray-500">Email</span>
                    <p class="text-lg font-semibold text-gray-900">{{ $tenant->email }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Phone</span>
                    <p class="text-lg font-semibold text-gray-900">{{ $tenant->phone ?? 'Not provided' }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Statistics Section -->
    <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-4">
        <div class="rounded-lg bg-blue-50 p-6">
            <div class="text-2xl font-bold text-blue-600">{{ count($freeBookings) }}</div>
            <div class="text-sm text-blue-600">Active Free Bookings</div>
        </div>
        <div class="rounded-lg bg-green-50 p-6">
            <div class="text-2xl font-bold text-green-600">{{ count($premiumBookings) }}</div>
            <div class="text-sm text-green-600">Active Premium Bookings</div>
        </div>
        <div class="rounded-lg bg-purple-50 p-6">
            <div class="text-2xl font-bold text-purple-600">{{ count($pastBookings) }}</div>
            <div class="text-sm text-purple-600">Past Bookings</div>
        </div>
        <div class="rounded-lg bg-orange-50 p-6">
            <div class="text-2xl font-bold text-orange-600">{{ $tenant->bookings()->where('status', 'pending')->count() }}</div>
            <div class="text-sm text-orange-600">Pending Bookings</div>
        </div>
    </div>

    <!-- Active Bookings Section -->
    <div class="mb-8">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">Active Bookings</h3>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Free Bookings -->
            <div class="rounded-lg bg-white p-6 shadow">
                <h4 class="mb-4 text-md font-semibold text-blue-600">Free Bookings</h4>
                @if (!empty($freeBookings))
                    @foreach ($freeBookings as $booking)
                    <div class="mb-3 rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900">
                                    Court {{ $booking->court->name }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ $booking->date->format('d M Y') }} • {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                                </div>
                                @if ($booking->is_light_required)
                                <div class="mt-1 text-xs text-orange-600">
                                    ⚡ Light required (+50k)
                                </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($booking->status === \App\Enum\BookingStatusEnum::PENDING)
                                <button class="rounded bg-red-500 px-3 py-1 text-xs text-white hover:bg-red-600"
                                        wire:click="denyBooking({{ $booking->id }})">
                                    Deny
                                </button>
                                <button class="rounded bg-green-600 px-3 py-1 text-xs text-white hover:bg-green-700"
                                        wire:click="confirmPayment({{ $booking->id }})">
                                    Confirm
                                </button>
                                @else
                                <span class="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                    {{ ucfirst($booking->status->value) }}
                                </span>
                                <button class="rounded bg-gray-500 px-3 py-1 text-xs text-white hover:bg-gray-600"
                                        wire:click="cancelBooking({{ $booking->id }})">
                                    Cancel
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="py-8 text-center text-gray-500">
                        <div class="text-lg">📅</div>
                        <div>No active free bookings</div>
                    </div>
                @endif
            </div>

            <!-- Premium Bookings -->
            <div class="rounded-lg bg-white p-6 shadow">
                <h4 class="mb-4 text-md font-semibold text-green-600">Premium Bookings</h4>
                @if (!empty($premiumBookings))
                    @foreach ($premiumBookings as $booking)
                    <div class="mb-3 rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900">
                                    Court {{ $booking->court->name }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ $booking->date->format('d M Y') }} • {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                                </div>
                                @if ($booking->is_light_required)
                                <div class="mt-1 text-xs text-orange-600">
                                    ⚡ Light required (+50k)
                                </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($booking->status === \App\Enum\BookingStatusEnum::PENDING)
                                <button class="rounded bg-red-500 px-3 py-1 text-xs text-white hover:bg-red-600"
                                        wire:click="denyBooking({{ $booking->id }})">
                                    Deny
                                </button>
                                <button class="rounded bg-green-600 px-3 py-1 text-xs text-white hover:bg-green-700"
                                        wire:click="confirmPayment({{ $booking->id }})">
                                    Confirm
                                </button>
                                @else
                                <span class="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                    {{ ucfirst($booking->status->value) }}
                                </span>
                                <button class="rounded bg-gray-500 px-3 py-1 text-xs text-white hover:bg-gray-600"
                                        wire:click="cancelBooking({{ $booking->id }})">
                                    Cancel
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="py-8 text-center text-gray-500">
                        <div class="text-lg">💎</div>
                        <div>No active premium bookings</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Past Bookings Section -->
    @if (!empty($pastBookings))
    <div class="mb-8">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Past Bookings</h3>
        <div class="rounded-lg bg-white p-6 shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Court</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Light</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($pastBookings as $booking)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                Court {{ $booking->court->name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ $booking->date->format('d M Y') }} • {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $booking->booking_type === 'premium' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ ucfirst($booking->booking_type) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                <span class="rounded-full px-2 py-1 text-xs font-medium
                                    {{ $booking->status === \App\Enum\BookingStatusEnum::CONFIRMED ? 'bg-green-100 text-green-800' :
                                       ($booking->status === \App\Enum\BookingStatusEnum::CANCELLED ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ ucfirst($booking->status->value) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                @if ($booking->is_light_required)
                                    <span class="text-orange-600">⚡ Yes</span>
                                @else
                                    <span class="text-gray-400">No</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    @endif

@filepondScripts

</div>
