@props(['variant' => 'primary'])

<button type="button"
    {{ $attributes->class([
        'rounded-sm',
        'px-2',
        'py-1',
        'text-sm',
        'font-semibold',
        'shadow-xs',
        'focus-visible:outline-2',
        'focus-visible:outline-offset-2',
        match ($variant) {
            'primary' => 'bg-gray-900 text-white hover:bg-gray-700 focus-visible:outline-gray-900',
            'secondary' => 'bg-gray-100 text-gray-800 hover:bg-gray-200 focus-visible:outline-gray-300',
            'link' => 'text-gray-200 hover:underline focus-visible:outline-gray-200',
            default => 'bg-gray-900 text-white hover:bg-gray-700 focus-visible:outline-gray-900',
        },
    ]) }}>
    {{ $slot }}
</button>
