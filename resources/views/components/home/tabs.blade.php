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

    <x-section-title>
        Facilities
    </x-section-title>

    <!-- Tab List -->
    <ul class="font-imbue container mb-6 flex items-stretch justify-center tracking-wider max-sm:flex-col max-sm:items-center"
        x-ref="tablist" @keydown.right.prevent.stop="$focus.wrap().next()" @keydown.home.prevent.stop="$focus.first()"
        @keydown.page-up.prevent.stop="$focus.first()" @keydown.left.prevent.stop="$focus.wrap().prev()"
        @keydown.end.prevent.stop="$focus.last()" @keydown.page-down.prevent.stop="$focus.last()" role="tablist">
        <!-- Tab -->

        @php
            $tabs = [
                'Tennis' => [
                    'route' => 'facilities.index',
                    'image' => asset('img/facilities/tennis-1.jpg'),
                    'description' => '
                        <p>
                            Enjoy premium-quality tennis courts built to international standards, exclusively for residents. Featuring two well-maintained courts with night lighting available for evening play.
                        </p>
                        <p class="text-xs">
                            Please note: an additional fee applies for light usage after dark. Perfect for both casual and serious players seeking convenience and performance.
                        </p>
                    ',
                ],
                'Basketball' => [
                    'route' => '',
                    'image' => 'https://picsum.photos/id/2/1920/1080',
                    'description' => '
                        <p>
                            Enjoy an active lifestyle with the exclusive outdoor basketball court at Permata Hijau Apartment. Designed for both casual play and serious games, it’s a great spot for residents to stay fit, socialize, and unwind - all just steps from home.
                        </p>
                    ',
                ],
                'Swimming Pool' => [
                    'route' => '',
                    'image' => 'https://picsum.photos/id/3/1920/1080',
                    'description' => '
                        <p>
                            Relax and refresh in the beautifully designed swimming pool at Permata Hijau Apartment. Surrounded by lush greenery, the pool offers a serene escape for both leisure and fitness, right in the heart of the residence.
                        </p>
                    ',
                ],
                'Table Tennis' => [
                    'route' => '',
                    'image' => 'https://picsum.photos/id/4/1920/1080',
                    'description' => '
                        <p>
                            Enjoy a fun and energetic game at the dedicated table tennis area in Permata Hijau Apartment - perfect for friendly matches, staying active, and building connections with fellow residents.
                        </p>
                    ',
                ],
                'Mini Golf' => [
                    'route' => '',
                    'image' => 'https://picsum.photos/id/5/1920/1080',
                    'description' => '
                        <p>
                            Experience leisure and relaxation at the mini golf area in Permata Hijau Apartment - a charming outdoor space where residents can unwind, have fun, and enjoy a casual game amidst a lush and peaceful environment.
                        </p>
                    ',
                ],
                'BBQ' => [
                    'route' => '',
                    'image' => 'https://picsum.photos/id/6/1920/1080',
                    'description' => '
                        <p>
                            Enjoy quality time with family and friends at the BBQ area in Permata Hijau Apartment - a cozy outdoor space perfect for gatherings, grilling, and creating memorable moments in a relaxed, open-air setting.
                        </p>
                    ',
                ],
            ];
        @endphp

        @foreach ($tabs as $text => $tab)
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
        @foreach ($tabs as $index => $tab)
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))" role="tabpanel">
                <div class="max-h-80">
                    <img class="size-full object-cover" src="{{ $tab['image'] }}" alt="">
                </div>
                <div class="space-y-4 px-4 pb-4 pt-8">

                    <div class="text-primary">
                        <h3 class="font-imbue text-4xl font-bold">{{ $index }}</h3>
                        <hr class="bg-primary my-6 h-0.5 w-1/3 border-0" />
                    </div>

                    <div class="space-y-4 text-sm">
                        {!! $tab['description'] !!}
                    </div>

                    <a class="bg-primary font-imbue mt-5 rounded-md border border-gray-200 px-4 py-2 text-white"
                        href="{{ !empty($tab['route']) ? route($tab['route']) : '#' }}">Book
                        Now</a>
                </div>
            </section>
        @endforeach
    </div>
</section>
