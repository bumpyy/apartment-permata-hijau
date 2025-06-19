<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" sticky stashable>

        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:brand :href="route('admin.dashboard')" :name="config('app.name')">
            <x-slot name="logo">
                <x-site-logo class="flex size-10 shrink-0 items-center justify-center rounded" />
            </x-slot>
        </flux:brand>

        <flux:navlist variant="outline">
            <flux:navlist.item wire:navigate icon="home" :href="route('admin.dashboard')">Home</flux:navlist.item>
            <flux:navlist.item wire:navigate icon="calendar" :href="route('admin.calendar')">Calendar</flux:navlist.item>
            <flux:navlist.item wire:navigate icon="inbox" :href="route('admin.booking-list')">Bookings</flux:navlist.item>
            <flux:navlist.item wire:navigate icon="users" :href="route('admin.tenant.list')">Tenants</flux:navlist.item>
            <flux:navlist.group class="max-lg:hidden" expandable :expanded="true" heading="Settings">
                <flux:navlist.item wire:navigate wire:navigate :href="route('admin.settings.premium')">Premium Booking Setting</flux:navlist.item>
                <flux:navlist.item wire:navigate wire:navigate :href="route('admin.settings.tenants')">Tenants Settings</flux:navlist.item>
                <flux:navlist.item wire:navigate wire:navigate :href="route('admin.settings.site')">Site Settings</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>


        <flux:spacer />

        {{-- <flux:navlist variant="outline">
            <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit"
                target="_blank">
                {{ __('Repository') }}
            </flux:navlist.item>

            <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire"
                target="_blank">
                {{ __('Documentation') }}
            </flux:navlist.item>
        </flux:navlist> --}}

        <!-- Desktop User Menu -->
        <flux:dropdown position="bottom" align="start">
            <flux:profile name="{{ auth()->user()->name }}" initials="{{ auth()->user()->initials() }}"
                icon-trailing="chevrons-up-down" />

            <flux:menu class="w-[220px]">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('admin.settings.profile')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form class="w-full" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:menu.item class="w-full" as="button" type="submit" icon="arrow-right-start-on-rectangle">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile initials="{{ auth()->user()->initials() }}" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('admin.settings.profile')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form class="w-full" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:menu.item class="w-full" as="button" type="submit" icon="arrow-right-start-on-rectangle">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>


</html>
