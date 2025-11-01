import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

// Import flatpickr styles
import "flatpickr/dist/flatpickr.css";

// Import custom directive
import flatpickrDirective from './directives/flatpickr';

// Import DevExtreme
import 'devextreme/dist/css/dx.light.css';

import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const appName = import.meta.env.VITE_APP_NAME || "FO Cloud 2027";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('flatpickr', flatpickrDirective)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
