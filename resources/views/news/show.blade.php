<x-frontend.layouts.app>

    <div class="mb-4">
        <a class="mx-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            href="{{ route('news.index') }}">
            Back to News
        </a>
    </div>

    <section class="prose mx-auto">
        <div class="text-center">
            <h1 class="font-imbue text-primary mb-0 mt-2 text-4xl font-bold">
                {{ $news->title }}
            </h1>

            <p class="mt-0 text-sm text-gray-500">{{ $news->created_at->format('F j, Y') }}</p>

            <hr class="border-primary mx-auto w-1/6 border-t-2 py-8" />
        </div>

        <div>{!! $news->content !!}</div>
    </section>
</x-frontend.layouts.app>
