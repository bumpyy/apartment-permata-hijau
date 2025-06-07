<x-backend.layouts.app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-backend.layouts.app.sidebar>
