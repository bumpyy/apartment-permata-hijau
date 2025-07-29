<section x-data="{
    selectedId: null,
    init() {
        // Set the first available tab on the page on page load.
        this.$nextTick(() => this.select(this.$id('tab', 1)))
    },
    select(id) {
        this.selectedId = id
    },
    isSelected(id) {
        return this.selectedId === id
    },
    whichChild(el, parent) {
        return Array.from(parent.children).indexOf(el) + 1
    }
}" x-id="['tab']">

    <div class="text-primary container w-max">
        <h2 class="font-imbue w-max text-center text-3xl font-thin">Facilities</h2>
        <hr class="bg-primary mx-auto my-6 h-0.5 w-3/4 border-0" />
    </div>

    <!-- Tab List -->
    <ul class="font-imbue container mb-6 flex items-stretch justify-center max-sm:flex-col max-sm:items-center"
        x-ref="tablist" @keydown.right.prevent.stop="$focus.wrap().next()" @keydown.home.prevent.stop="$focus.first()"
        @keydown.page-up.prevent.stop="$focus.first()" @keydown.left.prevent.stop="$focus.wrap().prev()"
        @keydown.end.prevent.stop="$focus.last()" @keydown.page-down.prevent.stop="$focus.last()" role="tablist">
        <!-- Tab -->


        @php
            $tabs = ['Tennis', 'Basketball', 'Swimming Pool', 'Table Tennis', 'Mini Golf', 'BBQ'];
            $routes = [
                'Tennis' => 'facilities.index',
                'Basketball' => 'facilities.index',
                'Swimming Pool' => 'facilities.index',
                'Table Tennis' => 'facilities.index',
                'Mini Golf' => 'facilities.index',
                'BBQ' => 'facilities.index',
            ];
        @endphp

        @foreach ($tabs as $text)
            <li>
                <button class="inline-flex px-5 py-2.5" :id="$id('tab', whichChild($el.parentElement, $refs.tablist))"
                    @click="select($el.id)" @mousedown.prevent @focus="select($el.id)" type="button"
                    :tabindex="isSelected($el.id) ? 0 : -1" :aria-selected="isSelected($el.id)"
                    :class="isSelected($el.id) ? 'bg-primary text-white' : ''"
                    role="tab">{{ $text }}</button>
            </li>
        @endforeach
    </ul>

    <!-- Panels -->
    <div class="bg-secondary font-montserrat text-black" role="tabpanels">
        <!-- Panels -->
        @foreach ($tabs as $index => $text)
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))" role="tabpanel">
                <img class="h-full w-full object-cover" src="https://picsum.photos/id/{{ $index + 1 }}/1920/1080"
                    alt="">
                <div class="space-y-4 px-4 pb-32 pt-8">

                    <div class="text-primary">
                        <h3 class="font-imbue text-4xl font-bold">{{ $text }}</h3>
                        <hr class="bg-primary my-6 h-0.5 w-1/3 border-0" />
                    </div>

                    <p>
                        <strong>
                            Enjoy premium-quality tennis courts built to international
                            standards, exclusively for residents. Featuring two well-maintained
                            courts with night lighting available for evening play.
                        </strong>
                    </p>

                    <p>
                        Please note: an additional fee applies for light usage after dark. Perfect for
                        both casual and serious players seeking convenience and performance.
                    </p>
                    <a class="bg-primary font-imbue mt-5 rounded-md border border-gray-200 px-4 py-2 text-sm text-white"
                        href="{{ !empty($routes[$text]) ? route($routes[$text]) : '#' }}">Book
                        Now</a>
                </div>
            </section>
        @endforeach
    </div>
</section>
