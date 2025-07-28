@props(['show' => null, 'title' => null, 'alpineShow' => null])

<div class="fixed inset-0 z-50 flex h-full w-full items-center justify-center overflow-y-auto bg-gray-600 bg-opacity-50"
    @if ($alpineShow) x-show="{{ $alpineShow }}"
    @elseif($show)
        x-show="$wire.{{ $show }}"
    @else
        x-show="false" @endif
    style="display: none;" x-cloak>
    <div class="fixed inset-0 z-50 bg-gray-500 opacity-75"></div>
    <div class="relative z-[60] w-full max-w-lg rounded-lg bg-white p-8 shadow-xl">
        @if (isset($title))
            <h2 class="mb-4 text-xl font-bold">{{ $title }}</h2>
        @endif
        <div>{{ $slot }}</div>
        @isset($footer)
            <div class="mt-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
