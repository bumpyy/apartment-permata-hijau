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
                    Apartemen Permata Hijau adalah sebuah kompleks apartemen premium yang berlokasi dijalan Arteri
                    Permata Hijau, Kebayoran Lama, Jakarta Selatan. Memiliki 3 tower, apartemen yang dikembangkan
                    secara keseluruhan memiliki 2 tower berlantai 11 dan 1 tower berlantai 12, dengan jumlah unit
                    apartemen yang mencapai 164 unit
                </p>
            </div>

            <div class="bg-primary container flex flex-col gap-8 p-16 text-sm leading-loose tracking-widest text-white">
                <div class="border-b border-b-white/50 pb-8">
                    <h2 class="font-imbue text-2xl">APARTEMEN PERMATA HIJAU</h2>
                    <p>Jl. Raya Permata Hijau Blok B No. 8, Jakarta Selaran</p>
                </div>

                <div class="grid grid-cols-2 border-b border-b-white/50 pb-8">
                    <div>
                        <h2 class="font-imbue text-2xl">High-rise Building</h2>
                        <p>Tower 1: 12 Floors + Basement</p>
                        <p>Tower 2: 11 Floors + Basement</p>
                    </div>

                    <div>
                        <h2 class="font-imbue text-2xl">Building Height</h2>
                        <p>±43.20 meters</p>
                    </div>
                </div>
                <div class="grid grid-cols-2">
                    <div>
                        <h2 class="font-imbue text-2xl">Total building area</h2>
                        <p>±38,179 m²</p>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-4 gap-y-8">
                    @php
                        $apartments = [
                            [
                                'name' => 'Tower 1',
                                'floors' => 'Basement to 11th Floor',
                                'area' => '12 x 750 m² = 9,000 m²',
                            ],
                            [
                                'name' => 'Tower 2',
                                'floors' => 'Basement to 12th Floor',
                                'area' => '13 x 916 m² = 10,992 m²',
                            ],
                            [
                                'name' => 'Tower 3',
                                'floors' => 'Basement to 13th Floor',
                                'area' => '13 x 1,339 m² = 18,187 m²',
                            ],
                        ];
                    @endphp

                    @foreach ($apartments as $apartment)
                        <div class="grid grid-cols-[auto_1fr] gap-4">
                            <x-icons.apt class="size-20" />
                            <div class="">
                                <h3 class="font-imbue text-xl">{{ $apartment['name'] }}</h3>
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

        <x-split-grid title="Developer" image="{{ asset('img/about/about-1.png') }}">
            <x-slot:description>
                Permata Hijau Apartment is a prestigious residential project
                developed by PT Masato Prima. Construction for Towers 1 & 2
                began in February 1992 and was completed in October 1993,
                while Tower 3 was built between May 1992 and October 1993.
                The project had a construction budget of Rp 83.48 billion in
                1993, equivalent to approximately Rp 864 billion in 2024 after
                inflation adjustment. Today, it stands as one of the iconic
                modern living landmarks in the Permata Hijau area, offering
                comfort, quality, and prestige to its residents.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Architectural Style" image="{{ asset('img/about/about-2.png') }}" reverse>
            <x-slot:description>
                Apartemen Permata Hijau dirancang oleh arsitek asal
                America bernama Richard “Randy” Dalrymple,
                bersama tim RSP-Pacific Associates (sekarang Paramita
                Abirama Istasadhya) sebagai architect of record.
                Kompleks ini dirancang pada awal 1990-an dengan
                gaya mediterania tropis, ditandai oleh jendela lebar,
                kolom Etruscan, dan lanskap sejuk yang mengarahkan
                penghuni ke pintu masuk tiap gedung.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Developer" image="{{ asset('img/about/about-3.png') }}">
            <x-slot:description>
                Permata Hijau Apartment, located in the
                prestigious Permata Hijau area of South Jakarta,
                offers a strategic location just minutes away from
                business districts, shopping centers, and
                entertainment hubs.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Location" image="{{ asset('img/about/about-4.png') }}" reverse>
            <x-slot:description>
                The presence of Permata Hijau Apartment has
                contributed to the transformation of the Permata Hijau
                area into an upscale residential neighborhood, home to
                the city’s upper-middle-class community. With its
                expansive land area, low-rise buildings, and limited units,
                Permata Hijau Apartment offers an exclusive living
                experience with a warm, family-friendly atmosphere,
                setting it apart from other apartments.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Area Development" image="{{ asset('img/about/about-5.png') }}">
            <x-slot:description>
                In addition to its spacious apartment units, Permata
                Hijau Apartment offers a complete range of facilities
                to support its residents’ lifestyle, including a
                basketball court, tennis court, BBQ area, swimming
                pool, mini golf, and table tennis.
            </x-slot:description>
        </x-split-grid>

        <x-split-grid title="Facilities" image="{{ asset('img/about/about-6.png') }}" reverse>
            <x-slot:description>
                Permata Hijau Apartment stands as one of Jakarta’s
                elite residential choices, attracting professionals,
                expatriates, and families seeking a comfortable and
                prestigious living environment.
            </x-slot:description>
        </x-split-grid>
    </section>
</x-frontend.layouts.app>
