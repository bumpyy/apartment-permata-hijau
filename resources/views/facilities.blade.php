<x-frontend.layouts.app>
    <div class="flex min-h-dvh flex-col justify-center bg-white">
        <div class="mx-auto max-w-xl px-4 py-16 sm:px-6 sm:py-24 lg:max-w-7xl lg:px-8">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Book Your Tennis Court</h2>
            <p class="mt-4 text-base text-gray-500">Book your tennis court now and enjoy playing tennis with your friends
                and family. Our tennis courts are designed to give you the ultimate tennis experience.</p>

            <div class="mt-10 space-y-12 lg:grid lg:grid-cols-3 lg:gap-x-8 lg:space-y-0">
                @foreach ($courts as $court)
                    <a class="group block" href="{{ route('tennis.court.booking', $court->id) }}">
                        @if ($court->image)
                            <img class="aspect-3/2 lg:aspect-5/6 w-full rounded-lg object-cover group-hover:opacity-75"
                                src="{{ $court->image }}" alt="{{ $court->name }}">
                        @else
                            <x-placeholder-pattern
                                class="size-full rounded-md border-2 border-gray-200 stroke-gray-900/20 dark:stroke-neutral-100/20" />
                        @endif
                        <h3 class="mt-4 text-base font-semibold text-gray-900">{{ $court->name }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ $court->description }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-frontend.layouts.app>
