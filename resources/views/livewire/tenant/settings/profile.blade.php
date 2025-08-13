<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.frontend.layouts.app')]
class extends Component
{
    public $tenant;

    public $freeBookings = [];

    public $premiumBookings = [];

    public $pastBookings = [];

    // Edit mode properties
    public $isEditing = false;

    public $editName = '';

    public $editEmail = '';

    public $editPhone = '';

    public $editPassword = '';

    public $editPasswordConfirmation = '';

    public $editDisplayName = '';

    public $profilePicture = '';

    public function mount()
    {
        $this->tenant = auth('tenant')->user();
    }

    public function loadTenantDetails()
    {

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
            'editPassword' => 'nullable|string|confirmed:editPasswordConfirmation|min:8',
        ]);

        $this->tenant->update([
            'name' => $this->editName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone,
            'display_name' => $this->editDisplayName,
        ]);

        if ($this->editPassword) {
            $this->tenant->update(['password' => Hash::make($this->editPassword)]);
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
}

?>

<div class="mx-auto max-w-7xl p-6">
    <!-- Header Section -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tenant Profile</h1>
            <p class="mt-1 text-sm text-gray-600">Manage Your </p>
        </div>
        <div class="flex items-center gap-3">
             <a href="{{ route('tenant.dashboard') }}"
               class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                ← Back to Dashboard
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
                        @if ($tenant && $tenant->getFirstMediaUrl('profile_picture'))
                            <img src="{{ $tenant->getFirstMediaUrl('profile_picture') }}" alt="Profile Picture" class="h-16 w-16 rounded-full object-cover border border-gray-300" />
                        @else
                            <div class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-500 border border-gray-300">
                                {{ $tenant ? $tenant->initials() : '?' }}
                            </div>
                        @endif
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
                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" wire:model="editPassword"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    @error('editPassword') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" wire:model="editPasswordConfirmation"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500">
                    @error('editPasswordConfirmation') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
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

            <div>
            </div>
        </div>
        @endif
    </div>
</div>
