@props(['date', 'image', 'title'])

<article class="relative isolate flex flex-col justify-end overflow-hidden rounded-2xl bg-gray-400 px-8 pt-80 pb-8 sm:pt-48 lg:pt-80">
    <img src="{{ $image }}" alt="" class="absolute inset-0 -z-10 size-full object-cover">
    <div class="absolute inset-0 -z-10 bg-linear-to-t from-gray-900 via-gray-900/40"></div>
    <div class="absolute inset-0 -z-10 rounded-2xl ring-1 ring-gray-900/10 ring-inset"></div>

    <div class="flex flex-wrap items-center gap-y-1 overflow-hidden text-sm/6 text-gray-300">
        <time datetime="{{ $date }}" class="mr-8">{{ $date }}</time>
    </div>
    <h3 class="mt-3 text-lg/6 font-semibold text-white">
        <a href="#">
            <span class="absolute inset-0"></span>
            {{ $title }}
        </a>
    </h3>
</article>
