import anchor from "@alpinejs/anchor";
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";
import { animate, stagger } from "animejs";

window.anime = { animate, stagger };

document.addEventListener("DOMContentLoaded", () => {
    Flux.dark = false;
    Flux.appearance = "light";
});

document.addEventListener(
    "alpine:init",
    () => {
        window.Alpine.plugin(collapse);
        window.Alpine.plugin(anchor);
        window.Alpine.plugin(focus);
    },
    { once: true }
);
