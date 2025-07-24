import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/home.js",
                "resources/js/editor.js",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
