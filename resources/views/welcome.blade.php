<x-frontend.layouts.app :title="__('Welcome')">
    <x-home.banner />

    <x-home.article-update title="Upcoming Event" variant="basic" :data="[
        [
            'title' => 'Celebrate Independence Day',
            'date' => '2022-12-31',
            'image' => 'https://picsum.photos/seed/1/320/240',
        ],
        [
            'title' => 'New Leadership Team Voting',
            'date' => '2022-12-30',
            'image' => 'https://picsum.photos/seed/2/320/240',
        ],
    ]" />

    <x-home.residence-news :data="[
        ['title' => 'News 1', 'image' => 'https://picsum.photos/seed/3/320/240', 'date' => '2023-01-01'],
        ['title' => 'News 2', 'image' => 'https://picsum.photos/seed/4/320/240', 'date' => '2023-01-02'],
        ['title' => 'News 3', 'image' => 'https://picsum.photos/seed/5/320/240', 'date' => '2023-01-03'],
        ['title' => 'News 4', 'image' => 'https://picsum.photos/seed/6/320/240', 'date' => '2023-01-04'],
        ['title' => 'News 5', 'image' => 'https://picsum.photos/seed/7/320/240', 'date' => '2023-01-05'],
        ['title' => 'News 6', 'image' => 'https://picsum.photos/seed/8/320/240', 'date' => '2023-01-06'],
        ['title' => 'News 7', 'image' => 'https://picsum.photos/seed/9/320/240', 'date' => '2023-01-07'],
    ]" />

    <x-home.tabs />

</x-frontend.layouts.app>
