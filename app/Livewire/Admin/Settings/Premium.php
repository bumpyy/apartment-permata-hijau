<?php

namespace App\Livewire\Admin\Settings;

use App\Models\PremiumDateOverride;
use Livewire\Component;

class Premium extends Component
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

    public function render()
    {
        return view('livewire.admin.settings.premium');
    }
}
