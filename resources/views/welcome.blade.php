<x-frontend.app :title="__('Welcome')">

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

    <x-home.residence-news />

    <x-home.tabs />

</x-frontend.app>
