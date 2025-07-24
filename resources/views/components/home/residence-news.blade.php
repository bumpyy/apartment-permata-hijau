@props(['data' => []])

@pushOnce('scripts')
    @vite(['resources/js/home.js'])
@endPushOnce

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
                        <x-news-card :image="$item['image']" :title="$item['title']" :date="$item['date']"
                            excerpt="Lorem ipsum dolor sit amet, consectetur adipiscing elit." />
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="glide__arrows" data-glide-el="controls">
            <button class="glide__arrow glide__arrow--left rounded-full border-none shadow-none sm:left-0"
                data-glide-dir="<">
                <x-lucide-chevron-left class="size-8 rounded-full bg-white p-1 text-black" />
            </button>

            <button class="glide__arrow glide__arrow--right rounded-full border-none shadow-none sm:right-0"
                data-glide-dir=">">
                <x-lucide-chevron-right class="size-8 rounded-full bg-white p-1 text-black" />
            </button>
        </div>
    </div>
</section>
