@props(['show' => null, 'title' => null, 'alpineShow' => null])

<div
    @if($alpineShow)
        x-show="{{ $alpineShow }}"
    @elseif($show)
        x-show="$wire.{{ $show }}"
    @else
        x-show="false"
    @endif
    class="fixed inset-0 z-50 flex items-center justify-center"
    style="display: none;"
    x-cloak
>
    <div class="fixed inset-0 bg-gray-500 opacity-75 z-50"></div>
    <div class="relative z-60 bg-white rounded-lg shadow-xl p-8 w-full max-w-lg">
        @if(isset($title))
            <h2 class="text-xl font-bold mb-4">{{ $title }}</h2>
        @endif
        <div>{{ $slot }}</div>
        @isset($footer)
            <div class="mt-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
