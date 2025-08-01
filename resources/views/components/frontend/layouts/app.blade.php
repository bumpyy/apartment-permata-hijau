<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="font-montserrat relative bg-white">
    <header>
        <div class="bg-primary">
            <div class="container flex items-center justify-between py-1 text-gray-100">
                <time x-data="{ time: new Date() }" x-init="setInterval(() => time = new Date(), 1000)"
                    x-text="time.toLocaleString([], { month: 'long', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true })"></time>
                <div class="flex flex-col items-end">
                    <div class="flex items-center gap-2 py-2 text-xs">
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
            <x-site-logo-long class="max-w-lg" />
            <h1 class="text-primary font-imbue sr-only text-4xl">
                {{ config('app.name', 'Apartemen Permata Hijau') }}
            </h1>
        </div>
    </header>

    <nav class="sticky top-0 z-30 flex w-full flex-col items-center justify-between gap-8 bg-white py-4 shadow-lg">
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
    </nav>

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

            <a class="absolute -top-8 right-6 flex items-center justify-center drop-shadow-2xl transition-all duration-200 hover:scale-110"
                href="https://wa.me/1234567890" target="_blank">
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
