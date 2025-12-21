<x-frontend.layouts.app>
    <x-home.article-update columnNumber="2" title="Upcoming Event" variant="basic" :data="$events" />

    {{ $events->links() }}
</x-frontend.layouts.app>
