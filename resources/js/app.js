import anchor from "@alpinejs/anchor";
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";
import { animate } from "animejs";

window.animate = animate;

Flux.dark = false;

document.addEventListener(
    "alpine:init",
    () => {
        window.Alpine.plugin(collapse);
        window.Alpine.plugin(anchor);
        window.Alpine.plugin(focus);
    },
    { once: true }
);
