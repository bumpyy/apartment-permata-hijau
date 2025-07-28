<x-frontend.layouts.app :title="__('Welcome')">
    <section class="bg-secondary py-16">
        <x-section-title>
            News
        </x-section-title>

        <div class="container">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @php
                    $news = [
                        [
                            'title' => 'News 1',
                            'image' => 'https://picsum.photos/seed/3/320/240',
                            'date' => '2023-01-01',
                            'url' => '#',
                            'excerpt' => 'Excerpt for news 1',
                        ],
                        [
                            'title' => 'News 2',
                            'image' => 'https://picsum.photos/seed/4/320/240',
                            'date' => '2023-01-02',
                            'url' => '#',
                            'excerpt' => 'Excerpt for news 2',
                        ],
                        [
                            'title' => 'News 3',
                            'image' => 'https://picsum.photos/seed/5/320/240',
                            'date' => '2023-01-03',
                            'url' => '#',
                            'excerpt' => 'Excerpt for news 3',
                        ],
                        [
                            'title' => 'News 4',
                            'image' => 'https://picsum.photos/seed/6/320/240',
                            'date' => '2023-01-04',
                            'url' => '#',
                            'excerpt' => 'Excerpt for news 4',
                        ],
                        [
                            'title' => 'News 5',
                            'image' => 'https://picsum.photos/seed/7/320/240',
                            'date' => '2023-01-05',
                            'url' => '#',
                            'excerpt' => 'Excerpt for news 5',
                        ],
                        [
                            'title' => 'News 6',
                            'image' => 'https://picsum.photos/seed/8/320/240',
                            'date' => '2023-01-06',
                            'url' => '#',
                            'excerpt' => 'Excerpt for news 6',
                        ],
                    ];
                @endphp

                @foreach ($news as $item)
                    <x-news-card :image="$item['image']" :title="$item['title']" :date="$item['date']" :url="$item['url'] ?? '#'"
                        :excerpt="$item['excerpt'] ?? ''" />
                @endforeach
            </div>

    </section>

</x-frontend.layouts.app>
