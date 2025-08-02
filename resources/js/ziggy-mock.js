// Mock Ziggy for development environments without vendor access
// Updated: 2025-08-03 - Added route().current() method
export const ZiggyVue = {
    install(app) {
        // Create the route function first
        const route = (name, params = {}, absolute = true) => {
            try {
                // Basic route helper for development
                const baseUrl = window.location.origin;
                
                // Handle missing name parameter
                if (!name) {
                    console.error('Route name is undefined');
                    return '#';
                }
                
                // Debug logging (commented out for production)
                // console.log(`Route called: ${name}`, 'Params:', params);
                
                // Normalize params - if it's a primitive (number/string), convert to object
                if (typeof params === 'number' || typeof params === 'string') {
                    // For routes like buckets.*, files.*, the parameter is usually 'bucket'
                    if (name.startsWith('buckets.') || name.startsWith('files.')) {
                        params = { bucket: params };
                    } else {
                        params = { id: params };
                    }
                }
                
                let url = name;
            
            // Handle common routes
            switch(name) {
                // Bucket routes
                case 'buckets.index':
                    url = '/buckets';
                    break;
                case 'buckets.create':
                    url = '/buckets/create';
                    if (params.provider) url += `?provider=${params.provider}`;
                    break;
                case 'buckets.store':
                    url = '/buckets';
                    break;
                case 'buckets.test':
                    url = `/buckets/${params.bucket}/test`;
                    break;
                case 'buckets.activate':
                    url = `/buckets/${params.bucket}/activate`;
                    break;
                case 'buckets.rename':
                    url = `/buckets/${params.bucket}/rename`;
                    break;
                case 'buckets.destroy':
                    url = `/buckets/${params.bucket}`;
                    break;
                
                // File routes
                case 'files.index':
                    url = `/buckets/${params.bucket}/files`;
                    if (params.prefix) url += `?prefix=${encodeURIComponent(params.prefix)}`;
                    if (params.continuation_token) url += `&continuation_token=${encodeURIComponent(params.continuation_token)}`;
                    break;
                case 'files.destroy':
                    url = `/buckets/${params.bucket}/files`;
                    break;
                case 'files.upload-url':
                    url = `/buckets/${params.bucket}/upload-url`;
                    break;
                case 'files.view-url':
                    url = `/buckets/${params.bucket}/view-url`;
                    break;
                case 'files.folder-download-urls':
                    url = `/buckets/${params.bucket}/folder-download-urls`;
                    break;
                case 'files.rename':
                    url = `/buckets/${params.bucket}/rename`;
                    break;
                case 'files.move':
                    url = `/buckets/${params.bucket}/move`;
                    break;
                case 'files.create-folder':
                    url = `/buckets/${params.bucket}/create-folder`;
                    break;
                case 'files.folder-tree':
                    url = `/buckets/${params.bucket}/folder-tree`;
                    break;
                
                // Profile routes
                case 'profile.edit':
                    url = '/profile';
                    break;
                case 'profile.update':
                    url = '/profile';
                    break;
                case 'profile.destroy':
                    url = '/profile';
                    break;
                
                // Auth routes
                case 'login':
                    url = '/login';
                    break;
                case 'logout':
                    url = '/logout';
                    break;
                case 'register':
                    url = '/register';
                    break;
                case 'password.request':
                    url = '/password/reset';
                    break;
                
                // Deprecated routes (keeping for backward compatibility)
                case 'folders.create':
                    url = `/buckets/${params.bucket}/folders`;
                    break;
                case 'folders.tree':
                    url = `/buckets/${params.bucket}/folders/tree`;
                    break;
                
                default:
                    url = '/' + name.replace(/\./g, '/');
            }
            
                // Check if URL contains undefined
                if (url.includes('undefined')) {
                    console.error(`Route ${name} generated invalid URL: ${url}`, 'Params:', params);
                    throw new Error(`Invalid route parameters for ${name}`);
                }
                
                const finalUrl = absolute ? baseUrl + url : url;
                // console.log(`Route ${name} generated: ${finalUrl}`);
                return finalUrl;
            } catch (error) {
                console.error('Error in route generation:', error, { name, params });
                return '#';
            }
        };
        
        // Add current() method to the route function
        route.current = (pattern) => {
            const currentPath = window.location.pathname;
            
            if (!pattern) {
                return currentPath;
            }
            
            // Handle wildcard patterns like 'buckets.*'
            if (pattern.endsWith('.*')) {
                const prefix = '/' + pattern.slice(0, -2).replace(/\./g, '/');
                return currentPath.startsWith(prefix);
            }
            
            // Handle exact matches
            const routePath = '/' + pattern.replace(/\./g, '/');
            return currentPath === routePath;
        };
        
        // Now assign the complete route function to globalProperties
        app.config.globalProperties.route = route;
    }
};