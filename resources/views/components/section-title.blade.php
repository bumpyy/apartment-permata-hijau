@props(['position' => 'center'])

<div {{ $attributes->merge(['class' => 'text-primary mx-auto max-w-2xl text-center']) }}>
    <h2 @class([
        'text-balance  tracking-tight text-2xl sm:text-5xl font-imbue font-thin',
    ])>
        {{ $slot }}
    </h2>

    <hr @class([
        'bg-primary my-8 h-1 w-1/4 border-0',
        'mx-auto' => $position === 'center',
        'mr-auto' => $position === 'left',
        'ml-auto' => $position === 'right',
    ]) />
</div>
