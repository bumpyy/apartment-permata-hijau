@props(['image', 'title', 'date', 'url' => '#', 'excerpt' => ''])

<div class="relative isolate flex flex-col justify-end overflow-hidden rounded-2xl bg-white">
    <img class="aspect-video object-cover" src="{{ $image }}" alt="">

    <div class="flex flex-col gap-3 bg-white p-3 px-5 pb-6">
        <div>
            <h3 class="text-primary text-xl/snug font-semibold">
                <a href="{{ $url }}">
                    {{ $title }}
                </a>
            </h3>

            <div class="flex flex-wrap items-center gap-y-1 overflow-hidden text-xs/snug">
                <time class="" datetime="{{ $date }}">{{ $date }}</time>
            </div>
        </div>

        <p class="line-clamp-3 text-sm/snug">
            {{ $excerpt }}
        </p>

    </div>
</div>
