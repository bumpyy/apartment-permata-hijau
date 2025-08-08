<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
    @metadata
</head>

<body class="font-montserrat relative bg-white">
    <header class="relative bg-white max-md:z-[31]">
        <div class="bg-primary">
            <div class="container flex flex-wrap items-center justify-between py-1 text-gray-100">
                <time datetime="{{ now()->format('Y-m-d H:i') }}" x-data="{ time: new Date() }" x-init="setInterval(() => time = new Date(), 1000)"
                    x-text="time.toLocaleString('id-ID', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })"></time>
                <div class="flex flex-col items-end">
                    <div class="flex items-center gap-2 py-2 text-xs">
                        @auth
                            <span class="text-xs">
                                @php
                                    $hour = date('G');
                                @endphp

                                @if ($hour < 12)
                                    Good morning,
                                @elseif ($hour < 17)
                                    Good afternoon,
                                @elseif ($hour < 20)
                                    Good evening,
                                @else
                                    Good night,
                                @endif

                                {{ auth()->user()->name }}
                            </span>
                        @endauth


                        <a href="{{ route('login') }}">
                            @auth
                                <a href="{{ route('tenant.dashboard') }}">
                                    <x-button variant="primary">Dashboard</x-button>
                                </a>
                            @else
                                <a href="{{ route('login') }}">
                                    <x-button variant="link">Login</x-button>
                                </a>
                            @endauth
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-col items-center gap-1 text-center">
            <x-site-logo-long class="max-w-lg px-4" />
            <h1 class="text-primary font-imbue sr-only text-4xl">
                {{ config('app.name', 'Apartemen Permata Hijau') }}
            </h1>
        </div>
    </header>

    <nav class="border-outline sticky top-0 z-20 flex min-h-14 items-center justify-between border-b bg-white px-6 py-4"
        x-data="{ mobileMenuIsOpen: false }" x-on:click.away="mobileMenuIsOpen = false" aria-label="penguin ui menu">
        <!-- Desktop Menu -->
        <ul
            class="text-primary font-imbue container hidden flex-wrap justify-center gap-2 text-lg uppercase tracking-wider md:flex">
            @foreach ([
        'home' => 'home',
        'about' => 'about',
        'facilities' => 'facilities.index',
        'news' => 'news.index',
        'event' => 'event',
        'committee' => 'committee',
        'contact' => 'contact',
    ] as $item => $route)
                <li @class([
                    'py-2 px-3',
                    ' text-white bg-primary' =>
                        request()->routeIs($route) || request()->routeIs("{$route}.*"),
                ])>
                    @if (Route::has($route))
                        <a href="{{ route($route) }}">{{ $item }}</a>
                    @else
                        <span class="text-gray-400">{{ $item }}</span>
                    @endif
                </li>
            @endforeach

        </ul>
        <!-- Mobile Menu Button -->

        <!-- Mobile Menu -->
        <ul class="divide-outline rounded-b-radius border-outline bg-surface-alt absolute inset-x-0 top-0 z-30 flex max-h-svh flex-col divide-y overflow-y-auto border-b bg-white px-6 pb-6 pt-20 uppercase md:hidden"
            id="mobileMenu" x-cloak x-show="mobileMenuIsOpen"
            x-transition:enter="transition motion-reduce:transition-none ease-out duration-300"
            x-transition:enter-start="-translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition motion-reduce:transition-none ease-out duration-300"
            x-transition:leave-start="translate-y-0" x-transition:leave-end="-translate-y-full">

            @foreach ([
        'home' => 'home',
        'about' => 'about',
        'facilities' => 'facilities.index',
        'news' => 'news.index',
        'event' => 'event',
        'committee' => 'committee',
        'contact' => 'contact',
    ] as $item => $route)
                <li class="py-4">

                    @if (Route::has($route))
                        <a class="text-primary w-full text-lg font-bold focus:underline"
                            href="{{ route($route) }}">{{ $item }}</a>
                    @else
                        <span class="text-gray-400">{{ $item }}</span>
                    @endif
                </li>
            @endforeach

        </ul>
        <button class="absolute right-4 top-4 z-30 flex md:hidden" x-on:click="mobileMenuIsOpen = !mobileMenuIsOpen"
            x-bind:aria-expanded="mobileMenuIsOpen" type="button" aria-label="mobile menu" aria-controls="mobileMenu">
            <svg class="size-6" x-cloak x-show="!mobileMenuIsOpen" xmlns="http://www.w3.org/2000/svg" fill="none"
                aria-hidden="true" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <svg class="size-6" x-cloak x-show="mobileMenuIsOpen" xmlns="http://www.w3.org/2000/svg" fill="none"
                aria-hidden="true" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </nav>


    {{-- <nav class="sticky top-0 z-30 flex w-full flex-col items-center justify-between gap-8 bg-white py-4 shadow-lg">
        <ul class="container flex flex-wrap justify-center gap-2 uppercase">
            @foreach ([
        'home' => 'home',
        'about' => 'about',
        'facilities' => 'facilities.index',
        'news' => 'news.index',
        'event' => 'event',
        'committee' => 'committee',
        'contact' => 'contact',
    ] as $item => $route)
                <li @class([
                    'py-2 px-4',
                    'font-bold text-white bg-primary' =>
                        request()->routeIs($route) || request()->routeIs("{$route}.*"),
                ])>
                    @if (Route::has($route))
                        <a href="{{ route($route) }}">{{ $item }}</a>
                    @else
                        <span class="text-gray-400">{{ $item }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav> --}}

    {{ $slot }}

    <footer class="bg-primary font-gotham relative">

        @unless (request()->routeIs('facilities.*'))
            <button
                class="bg-primary fixed bottom-6 right-6 flex items-center justify-center rounded-full p-2 text-white transition-all duration-200 hover:scale-110"
                id="back-to-top">
                <x-lucide-chevron-up class="size-6" />
            </button>
        @endunless



        <div class="container flex flex-col gap-4 py-8 text-white">
            <div class="flex flex-wrap gap-6">
                <x-site-logo class="basis-12 brightness-0 invert" />
                <p class="shrink basis-3/4">Jl. Permata Hijau No.8 Blok B, RT.008/RW.2, Grogol Utara, Kec.
                    Kby. Lama,
                    Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12210</p>
            </div>

            <ul class="flex flex-col items-start justify-center gap-2">
                <li class="flex flex-wrap items-center gap-2">
                    <x-lucide-message-circle class="text-primary/90 size-6 mix-blend-screen" />
                    <a class="text-white" href="tel:+628161308888">+62 816 130 8888</a>
                </li>
                <li class="flex flex-wrap items-center gap-2">
                    <x-lucide-mail class="text-primary/90 size-6 mix-blend-screen" />
                    <a class="text-white"
                        href="mailto:pprs@apartmentpermatahijau.com">pprs@apartmentpermatahijau.com</a>
                </li>
                <li class="flex flex-wrap items-center gap-2">
                    <x-lucide-phone class="text-primary/90 size-6 mix-blend-screen" />
                    <a class="text-white" href="tel:(021) 5320809">(021) 5320809</a>
                </li>
            </ul>

            <a @class([
                'absolute right-6 flex items-center justify-center drop-shadow-2xl transition-all duration-200 hover:scale-110',
                'fixed bottom-28' => !request()->routeIs('facilities.*'),
                '-top-8' => request()->routeIs('facilities.*'),
            ]) href="https://wa.me/1234567890" target="_blank">
                <span class="left-0 -mr-4 inline-block w-fit bg-white p-1 pl-3 pr-4 text-black">
                    Need Help? Chat with us
                </span>
                <x-icons.wa class="size-16" />
            </a>
        </div>
    </footer>

    @stack('scripts')
    <script>
        const backToTopButton = document.getElementById('back-to-top')

        if (backToTopButton) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 500) {
                    backToTopButton.classList.remove('opacity-0')
                    backToTopButton.classList.add('opacity-100')
                } else {
                    backToTopButton.classList.remove('opacity-100')
                    backToTopButton.classList.add('opacity-0')
                }
            })

            backToTopButton.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                })
            })
        }
    </script>
    @fluxScripts
</body>

</html>
