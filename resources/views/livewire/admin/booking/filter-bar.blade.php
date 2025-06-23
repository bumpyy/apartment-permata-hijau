<div class="mb-4 flex flex-wrap items-center gap-2">
    <input class="rounded border px-3 py-1 text-sm" type="text" wire:model.live.debounce.300ms="searchTerm"
        placeholder="Search..." />

    <select class="rounded border px-2 py-1 text-sm" wire:model.live="statusFilter">
        <option value="">All Status</option>
        <option value="confirmed">Confirmed</option>
        <option value="pending">Pending</option>
        <option value="cancelled">Cancelled</option>
    </select>

    <select class="rounded border px-2 py-1 text-sm" wire:model.live="typeFilter">
        <option value="">All Types</option>
        <option value="free">Free</option>
        <option value="premium ">Premium</option>
    </select>
</div>
