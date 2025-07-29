<x-frontend.layouts.app>
    <section class="bg-secondary py-12">
        <div class="container flex gap-4">

            <x-section-title position=""></x-section-title>

            <div>
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.3087004367744!2d106.78199297575883!3d-6.222964993765096!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f147082a8cc7%3A0x6f5e73c022ed3b66!2sApartemen%20Permata%20Hijau!5e0!3m2!1sen!2sid!4v1753756749938!5m2!1sen!2sid"
                    width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>

            <form class="flex w-full flex-col gap-4">
                <div class="flex w-full flex-col">
                    <label class="" for="name">Name</label>
                    <input class="bg-white" id="name" type="text" name="name" required>
                </div>
                <div class="flex w-full flex-col">
                    <label class="" for="phone">Phone</label>
                    <input class="bg-white" id="phone" type="tel" name="phone" required>
                </div>
                <div class="flex w-full flex-col">
                    <label class="" for="email">Email</label>
                    <input class="bg-white" id="email" type="email" name="email" required>
                </div>
                <div class="flex w-full flex-col">
                    <label class="" for="message">Message</label>
                    <textarea class="bg-white" id="message" name="message" rows="4" required></textarea>
                </div>
            </form>
        </div>
    </section>
</x-frontend.layouts.app>
