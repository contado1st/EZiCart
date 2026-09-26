import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/shared/app.css",
                "resources/css/shared/dashboard.css",
                "resources/css/shared/platform-controls.css",
                "resources/css/shared/reports.css",
                "resources/css/shared/disputes.css",
                "resources/css/shared/operations.css",
                "resources/css/storefront/landing.css",
                "resources/css/storefront/marketplace.css",
                "resources/css/auth/register-sorting.css",
                "resources/css/buyer/cart.css",
                "resources/css/buyer/checkout.css",
                "resources/css/buyer/reviews.css",
                "resources/css/admin/riders.css",
                "resources/css/seller/seller-vouchers.css",
                "resources/css/seller/waybill.css",
                "resources/css/logistics/logistics.css",
                "resources/css/courier/courier.css",
                "resources/js/app.js",
            ],
            refresh: true,
            fonts: [
                bunny("Instrument Sans", {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
