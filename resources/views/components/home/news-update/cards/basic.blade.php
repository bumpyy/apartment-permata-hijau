@props(['date', 'image', 'title'])

<article class="flex flex-col items-center justify-between">
    <div class="relative w-full">
        <img class="sm:aspect-2/1 lg:aspect-3/2 aspect-video w-full rounded-2xl bg-gray-100 object-cover"
            src="{{ $image }}" alt="">
        <div class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-gray-900/10"></div>
    </div>
    <div class="max-w-xl">
        {{-- <div class="mt-8 flex items-center gap-x-4 text-xs">
            <time class="text-gray-500" datetime="{{ $date }}">{{ $date }}</time>
            <x-button class="text-5xl" variant="secondary">Event</x-button>
        </div> --}}
        <div class="group relative flex flex-col gap-2">
            <h3 class="text-primary font-imbue mt-6 text-center text-2xl/6 font-thin">
                {{-- <a href=""> --}}
                <span class="absolute inset-0"></span>
                {{ $title }}
                {{-- </a> --}}
            </h3>
            <time class="text-gray-500" datetime="{{ $date }}">{{ $date }}</time>
            {{-- <p class="mt-5 line-clamp-3 text-sm/6 text-gray-600">Illo sint voluptas. Error voluptates culpa eligendi.
                Hic vel totam vitae illo. Non aliquid explicabo necessitatibus unde. Sed exercitationem placeat
                consectetur nulla deserunt vel. Iusto corrupti dicta.</p> --}}
        </div>

    </div>
</article>
