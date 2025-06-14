@props([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
])

<?php if ($expandable && $heading): ?>

<ui-disclosure data-flux-navlist-group {{ $attributes->class('group/disclosure') }}
    @if ($expanded === true) open @endif>
    <button
        class="group/disclosure-button mb-[2px] flex h-10 w-full items-center rounded-lg text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 lg:h-8"
        type="button">
        <div class="pe-4 ps-3">
            <flux:icon.chevron-down class="size-3! group-data-open/disclosure-button:block hidden" />
            <flux:icon.chevron-right class="size-3! group-data-open/disclosure-button:hidden block" />
        </div>

        <span class="text-sm font-medium leading-none">{{ $heading }}</span>
    </button>

    <div class="data-open:block relative hidden space-y-[2px] ps-7" @if ($expanded === true) data-open @endif>
        <div class="absolute inset-y-[3px] start-0 ms-4 w-px bg-zinc-200"></div>

        {{ $slot }}
    </div>
</ui-disclosure>

<?php elseif ($heading): ?>

<div {{ $attributes->class('block space-y-[2px]') }}>
    <div class="px-1 py-2">
        <div class="text-xs leading-none text-zinc-400">{{ $heading }}</div>
    </div>

    <div>
        {{ $slot }}
    </div>
</div>

<?php else: ?>

<div {{ $attributes->class('block space-y-[2px]') }}>
    {{ $slot }}
</div>

<?php endif; ?>
