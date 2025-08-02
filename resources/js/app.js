import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Enhanced route helper with better error handling
const enhancedRoute = (app) => {
    const originalRoute = app.config.globalProperties.route;
    
    app.config.globalProperties.route = function(name, params = {}, absolute = true) {
        // Add null/undefined checks
        if (!name) {
            console.warn('Route name is undefined');
            return '#';
        }
        
        // Convert name to string if it's not already
        const routeName = String(name);
        
        try {
            // Try to use the original route function if available
            if (originalRoute && typeof originalRoute === 'function') {
                return originalRoute.call(this, routeName, params, absolute);
            }
            
            // Fallback route generation
            console.warn('Using fallback route generation for:', routeName);
            const baseUrl = window.location.origin;
            let url = '/' + routeName.replace(/\./g, '/');
            
            // Handle common route patterns
            if (routeName.includes('files.') && params.bucket) {
                url = `/buckets/${params.bucket}/files`;
                if (params.prefix) url += `?prefix=${encodeURIComponent(params.prefix)}`;
            }
            
            return absolute ? baseUrl + url : url;
        } catch (error) {
            console.error('Error generating route:', error, { name: routeName, params });
            return '#';
        }
    };
    
    // Also add a current() method if it doesn't exist
    if (!app.config.globalProperties.route.current) {
        app.config.globalProperties.route.current = function(name) {
            const currentPath = window.location.pathname;
            if (!name) return false;
            
            const routeName = String(name);
            
            // Simple pattern matching
            if (routeName.includes('*')) {
                const pattern = routeName.replace('*', '');
                return currentPath.includes(pattern.replace(/\./g, '/'));
            }
            
            return currentPath.includes(routeName.replace(/\./g, '/'));
        };
    }
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);
        
        // Enhance the route helper
        enhancedRoute(app);
        
        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
