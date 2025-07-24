@props(['title' => '', 'description' => '', 'image' => '', 'reverse' => false])
<div {{ $attributes->merge(['class' => 'grid text-center md:grid-cols-2']) }}>
    <div @class(['md:order-2' => $reverse])>
        <img class="h-full w-full object-cover" src="{{ $image }}" alt="">
    </div>

    <div @class([
        'bg-secondary  flex flex-col items-center justify-center p-[clamp(2rem,7vw,8rem)]',
        'md:order-1' => $reverse,
    ])>
        @if (str_starts_with($title, '<') && str_ends_with($title, '>'))
            {!! $title !!}
        @else
            <h3 class="text-primary mb-4 text-[clamp(var(--text-xl),4vw,var(--text-5xl))] font-bold md:mb-8">
                {{ $title }}
            </h3>
        @endif
        <div class="text-[clamp(var(--text-sm),4vw,var(--text-lg))]">
            {{ $description }}
        </div>
    </div>
</div>
