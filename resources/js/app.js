import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'AI Hành Chính Công';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const path = `./Pages/${name}.vue`;
        const page = pages[path];
        
        if (!page) {
            console.error(`Page not found: ${name} at path: ${path}`);
            console.log('Available pages:', Object.keys(pages));
            throw new Error(`Page not found: ${name}. Make sure the file exists at ${path}`);
        }
        
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#F53003',
    },
});
