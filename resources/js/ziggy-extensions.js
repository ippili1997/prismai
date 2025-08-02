// Extensions for Ziggy to ensure compatibility
export function extendZiggy(route) {
    // Only add current() method if it doesn't already exist
    if (!route.current) {
        route.current = function(pattern) {
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
    }
    
    return route;
}