<x-frontend.layouts.app :title="__('Welcome')">
    <section class="mb-28">
        <div class="max-h-[550px] min-h-48 overflow-clip bg-gray-300">
            <img class="h-full w-full object-cover" src="{{ asset('img/header.jpg') }}" alt="">
        </div>

        <div class="">
            <div class="text-primary container flex flex-col items-center justify-center gap-8 py-24 text-center">
                <x-site-logo class="-mt-52 size-52 opacity-35" />
                <h1 class="text-5xl font-bold">Timeless Luxury, Since 1995</h1>
                <p class="text-black">
                    Experience timeless elegance and premium comfort in a colonial-style residence that has defined
                    luxury
                    living in
                    Jakarta since 1995. Apartment Permata Hijau offers a harmonious blend of classic architecture,
                    spacious
                    layouts,
                    and serene surroundings — a true sanctuary for those who value heritage, exclusivity, and enduring
                    quality.
                </p>

            </div>
            <div class="bg-primary container flex flex-col gap-8 p-16 text-white">
                <div class="grid grid-cols-2 border-b border-b-white/50 pb-8">
                    <div>
                        <h2>High-rise Building</h2>
                        <p>Tower 1: 12 Floors + Basement</p>
                        <p>Tower 2: 11 Floors + Basement</p>
                    </div>

                    <div>
                        <h2>Building Height</h2>
                        <p>±43.20 meters</p>
                    </div>
                </div>
                <div class="grid grid-cols-2">
                    <div>
                        <h2>Total building area</h2>
                        <p>±38,179 m²</p>
                    </div>
                </div>
                <div class="flex justify-between gap-4">
                    @php
                        $apartments = [
                            [
                                'name' => 'tower 1',
                                'floors' => 'Basement to 11th Floor',
                                'area' => '12 x 750 m² = 9,000 m²',
                            ],
                            [
                                'name' => 'tower 2',
                                'floors' => 'Basement to 12th Floor',
                                'area' => '13 x 916 m² = 10,992 m²',
                            ],
                            [
                                'name' => 'tower 3',
                                'floors' => 'Basement to 13th Floor',
                                'area' => '13 x 1,339 m² = 18,187 m²',
                            ],
                        ];
                    @endphp
                    @foreach ($apartments as $apartment)
                        <div class="grid grid-cols-[auto_1fr] gap-4">
                            <x-icons.apt class="size-20" />
                            <div>
                                <h3>{{ $apartment['name'] }}</h3>
                                <p>{{ $apartment['floors'] }}</p>
                                <p>{{ $apartment['area'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section>
        <x-section-title>
            Key Highlight
        </x-section-title>

        <x-split-grid title="Developer" image="https://picsum.photos/seed/1/320/240">
            <x-slot:description>
                Although the specific developer is not widely documented, this apartment is recognized as one of
                <strong>the earliest buildings</strong> in the Permata Hijau area.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Architectural Style" image="https://picsum.photos/seed/2/320/240" reverse>
            <x-slot:description>
                The apartment showcases a luxurious classical <strong>design</strong>, offering an elegant and
                sophisticated living experience.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Developer" image="https://picsum.photos/seed/3/320/240">
            <x-slot:description>
                Although the specific developer is not widely documented, this apartment is recognized as one of
                <strong>the earliest buildings</strong> in the Permata Hijau area.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Location" image="https://picsum.photos/seed/4/320/240" reverse>
            <x-slot:description>
                Strategically located in <strong>South Jakarta</strong>, it provides easy access to business centers,
                shopping malls, and entertainment venues.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Area Development" image="https://picsum.photos/seed/5/320/240">
            <x-slot:description>
                The presence of Permata Hijau Apartment played a key role in the <strong>development of Permata
                    Hijau</strong> into a
                premium residential district favored by the upper-middle class.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Facilities" image="https://picsum.photos/seed/6/320/240" reverse>
            <x-slot:description>
                In addition to spacious residential units, the complex is equipped with <strong>comprehensive
                    facilities</strong> to
                support a modern and convenient lifestyle.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Elite Living" image="https://picsum.photos/seed/7/320/240">
            <x-slot:description>
                Permata Hijau Apartment remains a <strong>sought-after</strong> residence in Jakarta, attracting
                professionals, expatriates, and families seeking comfortable, prestigious living.
            </x-slot:description>
        </x-split-grid>
    </section>
</x-frontend.layouts.app>
