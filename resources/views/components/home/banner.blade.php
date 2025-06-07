<section>
    <div class="max-h-[550px] min-h-48 overflow-clip bg-gray-300">
        <img class="h-full w-full object-cover" src="{{ asset('img/header.jpg') }}" alt="">
    </div>

    <div class="bg-secondary">
        <div class="text-primary container flex flex-col items-center justify-center gap-8 py-24 text-center">
            <x-site-logo class="-mt-52 size-52 opacity-35" />
            <h2 class="text-5xl font-bold">Timeless Luxury, Since 1995</h2>
            <p class="text-black">
                Experience timeless elegance and premium comfort in a colonial-style residence that has defined luxury
                living in
                Jakarta since 1995. Apartment Permata Hijau offers a harmonious blend of classic architecture, spacious
                layouts,
                and serene surroundings — a true sanctuary for those who value heritage, exclusivity, and enduring
                quality.
            </p>
            <div class="flex flex-wrap justify-center gap-8">
                @foreach ([
        [
            'icon' => 'lucide-house',
            'value' => '18.000',
            'label' => 'Square Meters',
        ],
        ['icon' => 'lucide-car', 'value' => '890', 'label' => 'Parking Slots'],
        ['icon' => 'lucide-users', 'value' => '3', 'label' => 'Towers'],
        ['icon' => 'lucide-sofa', 'value' => '182', 'label' => 'Unit Apartments'],
    ] as $item)
                    <div class="flex items-center gap-2">

                        <x-dynamic-component class="size-12" :component="$item['icon']" />

                        <div class="flex flex-col text-start">
                            <h3 class="text-3xl font-bold leading-6">{{ $item['value'] }}</h3>
                            <p>{{ $item['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
