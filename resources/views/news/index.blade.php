<x-frontend.layouts.app :title="__('Welcome')">
    <section class="bg-secondary py-16">
        <x-section-title>
            News
        </x-section-title>

        <div class="container">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($news as $item)
                    <x-news-card :image="$item->getFirstMediaUrl()" :title="$item->title" :date="$item->created_at" :url="route('news.show', $item->slug)"
                        :excerpt="$item->excerpt" />
                @endforeach
            </div>

    </section>

</x-frontend.layouts.app>
