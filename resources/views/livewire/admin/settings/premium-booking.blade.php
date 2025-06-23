<?php

use App\Settings\PremiumSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
    #[Layout('components.backend.layouts.app')]
    #[Title('Profile')]
    class extends Component
    {
        public string $date;

        public string $email = '';

        /**
         * Mount the component.
         */
        public function mount(PremiumSettings $premiumSettings): void
        {
            $this->date = $premiumSettings->open_date;
        }

        /**
         * Update the profile information for the currently authenticated user.
         */
        public function updatePremiumDate(PremiumSettings $premiumSettings): void
        {
            $premiumSettings->open_date = $this->date;

            $premiumSettings->save();

            $this->dispatch('date-updated');
        }
    }; ?>

<section class="w-full">
    <form wire:submit="updatePremiumDate" class="my-6 w-full space-y-6">
            <flux:input wire:model="date" :label="__('Date')" type="number" min="1" max="31" autofocus required />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="date-updated">
                    {{ __('date updated') }}
                </x-action-message>
            </div>
    </form>
</section>
