<div class="mb-4 flex gap-2 overflow-x-auto pb-2">
    <button
        class="{{ $courtFilter === '' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} whitespace-nowrap rounded px-4 py-2 font-medium focus:outline-none"
        wire:click="filterByCourt('')">All</button>
    @foreach ($this->courts as $court)
        <button
            class="{{ $courtFilter == $court->id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} whitespace-nowrap rounded px-4 py-2 font-medium focus:outline-none"
            wire:click="filterByCourt('{{ $court->id }}')">{{ $court->name }}</button>
    @endforeach
</div>
