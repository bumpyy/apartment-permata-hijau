<x-frontend.layouts.app>
    <section class="bg-secondary container py-8">
        <x-section-title>
            Committee
        </x-section-title>

        <div class="grid grid-cols-3 gap-8">
            @foreach ($committees as $key => $value)
                <div class="flex flex-col items-center justify-center">
                    <div @class([
                        'size-32 overflow-clip rounded-full',
                        'bg-gray-300' => !$value->hasMedia('committee_image'),
                    ])>
                        @if ($value->getFirstMediaUrl('committee_image'))
                            <img class="size-full object-cover" src="{{ $value->getFirstMediaUrl('committee_image') }}"
                                alt="">
                        @endif
                    </div>
                    <h3 class="text-lg font-semibold">{{ $value->name }}</h3>
                    <p class="text-sm text-gray-600">{{ $value->position }}</p>
                </div>
            @endforeach
        </div>

    </section>
</x-frontend.layouts.app>
