@pushOnce('scripts')
    @vite(['resources/js/home.js'])
@endPushOnce

@props(['data' => []])

<section class="bg-primary/75 mb-20 bg-[url('../img/slider-bg.png')] py-20 bg-blend-multiply">

    <div class="container mb-12 w-max text-white">
        <h2 class="w-max text-center text-3xl font-bold">Residence News</h2>
        <hr class="mx-auto my-6 h-0.5 w-1/3 border-0 bg-white" />
    </div>

    <div class="glide container" id="news-slider">
        <div class="glide__track" data-glide-el="track">
            <ul class="glide__slides">
                @foreach ($data as $item)
                    <li class="glide__slide">
                        <article
                            class="relative isolate flex flex-col justify-end overflow-hidden rounded-2xl bg-gray-400 px-8 pb-8 pt-80 sm:pt-48 lg:pt-80">
                            <img class="absolute inset-0 -z-10 size-full object-cover" src="{{ $item['image'] }}"
                                alt="">
                            <div class="bg-linear-to-t absolute inset-0 -z-10 from-gray-900 via-gray-900/40"></div>
                            <div class="absolute inset-0 -z-10 rounded-2xl ring-1 ring-inset ring-gray-900/10"></div>

                            <div class="flex flex-wrap items-center gap-y-1 overflow-hidden text-sm/6 text-gray-300">
                                <time class="mr-8" datetime="{{ $item['date'] }}">{{ $item['date'] }}</time>
                            </div>
                            <h3 class="mt-3 text-lg/6 font-semibold text-white">
                                <a href="#">
                                    <span class="absolute inset-0"></span>
                                    {{ $item['title'] }}
                                </a>
                            </h3>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="glide__arrows" data-glide-el="controls">
            <button
                class="glide__arrow glide__arrow--left -left-4 rounded-full border-none bg-white shadow-none sm:left-0"
                data-glide-dir="<">
                <x-lucide-chevron-left class="size-8 text-black" />
            </button>

            <button
                class="glide__arrow glide__arrow--right -right-4 rounded-full border-none bg-white shadow-none sm:right-0"
                data-glide-dir=">">
                <x-lucide-chevron-right class="size-8 text-black" />
            </button>
        </div>
    </div>
</section>
