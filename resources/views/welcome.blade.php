<x-frontend.layouts.app :title="__('Welcome')">
    <x-home.banner />

    @if (count($events))
        <x-home.article-update title="Upcoming Event" variant="basic" :data="$events" />
    @endif

    @if (count($news))
        <x-home.residence-news :data="$news" />
    @endif

    <x-home.tabs />

</x-frontend.layouts.app>
