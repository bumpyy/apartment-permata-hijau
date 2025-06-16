<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')

    @stack('scripts')
</head>

<body class="min-h-screen bg-white">
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

        <nav class="container flex flex-col items-center justify-between gap-8 py-4">

            @if (request()->routeIs('home'))
                <div class="flex flex-col items-center gap-1 text-center">
                    <x-site-logo class="size-8" />
                    <h1 class="text-primary text-4xl">{{ config('app.name', 'Apartemen Permata Hijau') }}</h1>
                </div>
            @endif

            <ul class="flex flex-wrap gap-2 uppercase">
                @foreach ([
        'home' => 'home',
        'about' => 'about',
        'facilities' => 'facilities',
        'news' => 'news.index',
        'event' => 'event.index',
        'committee' => 'committee.index',
        'contact' => 'contact',
    ] as $item => $route)
                    <li @class([
                        'py-2 px-4',
                        'font-bold text-white bg-primary' => request()->routeIs($route),
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
    </header>

    {{ $slot }}

    <footer class="bg-primary relative">
        <div class="container flex flex-col gap-4 py-8 text-white">
            <div class="flex flex-wrap gap-6">
                <x-site-logo class="basis-12 mix-blend-screen" />
                <p class="shrink basis-3/4">Jl. Permata Hijau No.8 Blok B, RT.008/RW.2, Grogol Utara, Kec. Kby. Lama,
                    Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12210</p>
            </div>

            <ul class="flex flex-col items-start justify-center gap-2">
                <li class="flex flex-wrap items-center gap-2">
                    <x-dynamic-component class="text-primary/90 size-6 mix-blend-screen"
                        component="lucide-message-circle" />
                    <a class="text-white" href="tel:+628161308888">+62 816 130 8888</a>
                </li>
                <li class="flex flex-wrap items-center gap-2">
                    <x-dynamic-component class="text-primary/90 size-6 mix-blend-screen" component="lucide-mail" />
                    <a class="text-white"
                        href="mailto:pprs@apartmentpermatahijau.com">pprs@apartmentpermatahijau.com</a>
                </li>
                <li class="flex flex-wrap items-center gap-2">
                    <x-dynamic-component class="text-primary/90 size-6 mix-blend-screen" component="lucide-phone" />
                    <a class="text-white" href="tel:(021) 5320809">(021) 5320809</a>
                </li>

            </ul>

            <a class="absolute -top-8 right-6 inline-block size-16 drop-shadow-lg transition-all duration-200 hover:scale-110"
                href="https://wa.me/1234567890" target="_blank">
                <x-icons.wa />
            </a>
        </div>
    </footer>

    @fluxScripts

</body>

</html>
