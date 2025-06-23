<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($stats as $stat)
        <div class="{{ $stat['color'] }} rounded-xl p-4 text-white shadow">
            <div class="text-sm font-medium">{{ $stat['title'] }}</div>
            <div class="mt-1 text-2xl font-bold">{{ $stat['value'] }}</div>
        </div>
    @endforeach
</div>
