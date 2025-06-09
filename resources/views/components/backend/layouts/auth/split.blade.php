<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="dark:bg-linear-to-b min-h-screen bg-white antialiased dark:from-neutral-950 dark:to-neutral-900">
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div
            class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
            <div class="absolute inset-0 bg-neutral-900"></div>
            <a class="relative z-20 flex items-center text-lg font-medium" href="{{ route('home') }}" wire:navigate>
                <span class="flex h-10 w-10 items-center justify-center rounded-md">
                    <x-site-logo class="me-2 h-7 fill-current text-white" />
                </span>
                {{ config('app.name', 'Laravel') }}
            </a>

            <img class="absolute inset-0 h-full w-full object-cover" src="https://picsum.photos/seed/1/1920/1080"
                alt="">
        </div>

        <div class="w-full lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <a class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" href="{{ route('home') }}"
                    wire:navigate>

                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-site-logo /> class="size-9 fill-current text-black dark:text-white" />
                    </span>

                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                {{ $slot }}
            </div>
        </div>
    </div>
    @fluxScripts
</body>

</html>
