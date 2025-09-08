<x-frontend.layouts.app>
    <x-split-grid class="!text-left" image="{{ asset('img/facilities/tennis-1.jpg') }}">
        <x-slot:title>
            <x-section-title class="!mx-0 w-full !text-left" position="left">
                Tennis Court
            </x-section-title>
        </x-slot:title>

        <x-slot:description>
            <div class="flex flex-col gap-8">
                <p>
                    Enjoy premium-quality tennis courts built to international
                    standards, exclusively for residents. Featuring two well-maintained
                    courts with night lighting available for evening play.
                </p>

                <a class="bg-primary w-fit border border-gray-200 px-4 py-2 text-sm text-white"
                    href="{{ route('facilities.tennis') }}">Book Now</a>
            </div>
        </x-slot:description>
    </x-split-grid>

    <x-split-grid class="!text-left" image="{{ asset('img/facilities/basketball.png') }}" reverse>
        <x-slot:title>
            <x-section-title class="!mx-0 w-full !text-left" position="left">
                Basketball Court
            </x-section-title>
        </x-slot:title>

        <x-slot:description>
            <div class="flex flex-col gap-8">
                <p>
                    Enjoy an active lifestyle with the exclusive outdoor basketball court
                    at Permata Hijau Apartment. Designed for both casual play and
                    serious games, it’s a great spot for residents to stay fit, socialize, and
                    unwind—all just steps from home
                </p>

                <a class="bg-primary w-fit border border-gray-200 px-4 py-2 text-sm text-white"
                    href="https://wa.me/{{ $whatsappNumber }}">Book Now</a>
            </div>
        </x-slot:description>
    </x-split-grid>

    <x-split-grid class="!text-left" image="{{ asset('img/facilities/swimming-pool.jpeg') }}">
        <x-slot:title>
            <x-section-title class="!mx-0 w-full !text-left" position="left">
                Swimming Pool
            </x-section-title>
        </x-slot:title>

        <x-slot:description>
            <div class="flex flex-col gap-8">
                <p>
                    Relax and refresh in the beautifully designed swimming pool at
                    Permata Hijau Apartment. Surrounded by lush greenery, the pool
                    offers a serene escape for both leisure and fitness, right in the heart
                    of the residence.
                </p>
            </div>
        </x-slot:description>
    </x-split-grid>

    {{-- <x-split-grid class="!text-left" image="{{ asset('img/facilities/bbq.png') }}" reverse>
        <x-slot:title>
            <x-section-title class="!mx-0 w-full !text-left" position="left">
                BBQ
            </x-section-title>
        </x-slot:title>

        <x-slot:description>
            <div class="flex flex-col gap-8">
                <p>
                    Enjoy quality time with family and friends at the BBQ area in
                    Permata Hijau Apartment — a cozy outdoor space perfect for
                    gatherings, grilling, and creating memorable moments in a relaxed,
                    open-air setting
                </p>

                <a class="bg-primary w-fit border border-gray-200 px-4 py-2 text-sm text-white"
                    href="https://wa.me/{{ $whatsappNumber }}">Book Now</a>
            </div>
        </x-slot:description>
    </x-split-grid> --}}
</x-frontend.layouts.app>
