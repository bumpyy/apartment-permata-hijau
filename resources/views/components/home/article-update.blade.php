@props(['variant' => 'basic', 'title' => '', 'data' => []])

@php
    if (!in_array($variant, ['overlay', 'basic'])) {
        throw new \Exception('Only "overlay" or "basic" are accepted.');
    }
@endphp

<section class="bg-white py-20 sm:py-32">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <x-section-title>
            {{ $title }}
        </x-section-title>

        <div class="sm:grid-cols-(--custom-columns) mx-auto mt-16 grid max-w-2xl auto-rows-fr grid-cols-1 gap-8 sm:mt-20 lg:mx-0 lg:max-w-none"
            style="--custom-columns: repeat({{ count($data) }}, minmax(0, 1fr))">
            @foreach ($data as $item)
                <x-dynamic-component :component="'home.news-update.cards.' . $variant" date="{{ date('F j, Y') }}" :image="'https://picsum.photos/seed/' . $loop->index . '/640/480'" :title="$item->title ?? 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.'" />
            @endforeach

            <!-- More posts... -->
        </div>
    </div>
</section>
