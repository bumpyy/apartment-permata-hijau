@props(['variant' => 'basic', 'title' => '', 'data' => []])

@php
    if (!in_array($variant, ['overlay', 'basic'])) {
        throw new \Exception('Only "overlay" or "basic" are accepted.');
    }
@endphp

<section class="bg-white py-20 sm:py-32">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="text-primary mx-auto max-w-2xl text-center">
            <h2 class="text-balance text-2xl font-semibold tracking-tight sm:text-5xl">
                {{ $title }}
            </h2>

            <hr class="bg-primary mx-auto my-8 h-1 w-1/4 border-0" />

            {{-- <p class="mt-2 text-lg/8 text-gray-600">Learn how to grow your business with our expert advice.</p>
            --}}
        </div>

        <div class="sm:grid-cols-(--custom-columns) mx-auto mt-16 grid max-w-2xl auto-rows-fr grid-cols-1 gap-8 sm:mt-20 lg:mx-0 lg:max-w-none"
            style="--custom-columns: repeat({{ count($data) }}, minmax(0, 1fr))">
            @foreach ($data as $item)
                <x-dynamic-component :component="'home.news-update.cards.' . $variant" date="{{ date('F j, Y') }}" :image="'https://picsum.photos/seed/' . $loop->index . '/640/480'" :title="$item->title ?? 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.'" />
            @endforeach

            <!-- More posts... -->
        </div>
    </div>
</section>
