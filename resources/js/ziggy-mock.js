// Mock Ziggy for development environments without vendor access
export const ZiggyVue = {
    install(app) {
        app.config.globalProperties.route = (name, params = {}, absolute = true) => {
            // Basic route helper for development
            const baseUrl = window.location.origin;
            let url = name;
            
            // Handle common routes
            switch(name) {
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
                case 'folders.create':
                    url = `/buckets/${params.bucket}/folders`;
                    break;
                case 'folders.tree':
                    url = `/buckets/${params.bucket}/folders/tree`;
                    break;
                default:
                    url = '/' + name.replace(/\./g, '/');
            }
            
            return absolute ? baseUrl + url : url;
        };
    }
};