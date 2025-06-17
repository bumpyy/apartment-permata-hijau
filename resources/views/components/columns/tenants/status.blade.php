@props(['value'])

<div class="flex">
    <div @class([
        'text-white rounded-xl px-2 uppercase font-bold text-xs',
        'bg-red-500' => $value === 'deleted',
        'bg-green-500' => $value === 'active',
        'bg-gray-500' => $value === 'inactive',
    ])>
        {{ $value }}
    </div>
</div>
