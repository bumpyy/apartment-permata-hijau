<x-frontend.layouts.app>
    <section class="bg-secondary py-12">

        <x-section-title>
            Contact Us
        </x-section-title>

        <div class="text-primary font-imbue container mt-4 grid gap-8 text-lg md:grid-cols-[35%_1fr]">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.3087004367744!2d106.78199297575883!3d-6.222964993765096!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f147082a8cc7%3A0x6f5e73c022ed3b66!2sApartemen%20Permata%20Hijau!5e0!3m2!1sen!2sid!4v1753756749938!5m2!1sen!2sid"
                height="100%" style="border:0;" allowfullscreen="" width="100%" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>

            <form class="flex w-full flex-col gap-4" action="{{ route('contact.store') }}" method="POST">
                @csrf

                @if (session('success'))
                    <div class="relative rounded border border-green-400 bg-green-100 px-4 py-3 text-green-700"
                        role="alert">
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="relative rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700"
                        role="alert">
                        <strong class="font-bold">Error!</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex w-full flex-col gap-y-1">
                    <label class="" for="name">Name</label>
                    <input class="bg-white" id="name" type="text" name="name" required>
                </div>
                <div class="flex w-full flex-col gap-y-1">
                    <label class="" for="phone">Phone</label>
                    <input class="bg-white" id="phone" name="phone" required>
                </div>
                <div class="flex w-full flex-col gap-y-1">
                    <label class="" for="email">Email</label>
                    <input class="bg-white" id="email" type="email" name="email" required>
                </div>
                <div class="flex w-full flex-col gap-y-1">
                    <label class="" for="message">Message</label>
                    <textarea class="bg-white" id="message" name="message" rows="4" required></textarea>
                </div>

                <button class="bg-primary hover:bg-primary-dark ml-auto w-fit px-8 py-1 text-white transition-colors"
                    type="submit">
                    Submit
                </button>
            </form>
        </div>
    </section>
</x-frontend.layouts.app>
