<template>
    <!-- Desktop layout -->
    <div class="hidden sm:flex items-center">
        <div class="w-12 px-2">
            <input
                type="checkbox"
                :checked="isSelected"
                @change="$emit('toggle-selection')"
                @click.stop
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
        </div>
        <div class="flex-1 grid grid-cols-12 gap-4 items-center">
            <div class="col-span-5">
                <div class="flex items-center group">
                    <button 
                        @click="handleClick" 
                        class="flex items-center"
                        :class="isPreviewable ? 'hover:text-indigo-600 cursor-pointer' : 'cursor-default'"
                    >
                        <component :is="fileIcon" class="h-5 w-5 mr-3 flex-shrink-0" :class="fileIconColor" />
                        <span class="text-sm font-medium" :class="isPreviewable ? 'hover:underline' : ''">{{ file.name }}</span>
                    </button>
                    <button
                        @click.stop="$emit('rename')"
                        class="ml-2 p-1 text-gray-500 hover:text-gray-700 opacity-0 group-hover:opacity-100 transition-opacity duration-75"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="col-span-2 text-sm text-gray-500">{{ formatFileSize(file.size) }}</div>
            <div class="col-span-3 text-sm text-gray-500">{{ file.last_modified }}</div>
            <div class="col-span-2">
                <div class="flex items-center justify-end space-x-1">
                    <button
                        v-if="isPreviewable"
                        @click="$emit('preview')"
                        class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-md transition-colors"
                        title="Preview"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                    <button
                        v-else
                        disabled
                        class="p-1.5 text-gray-300 cursor-not-allowed rounded-md"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                    <button
                        @click="$emit('download')"
                        class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors"
                        title="Download"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </button>
                    <button
                        @click="$emit('delete')"
                        :disabled="isDeleting"
                        class="p-1.5 rounded-md transition-colors"
                        :class="isDeleting ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700 hover:bg-red-50'"
                        title="Delete"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile layout -->
    <div class="sm:hidden">
        <div class="flex items-start space-x-3">
            <input
                type="checkbox"
                :checked="isSelected"
                @change="$emit('toggle-selection')"
                @click.stop
                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <div class="flex-1 min-w-0">
                <button 
                    @click="handleClick" 
                    class="flex items-center w-full text-left"
                    :class="isPreviewable ? 'active:text-indigo-600' : ''"
                >
                    <component :is="fileIcon" class="h-5 w-5 mr-2 flex-shrink-0" :class="fileIconColor" />
                    <span class="text-sm font-medium text-gray-900 truncate">{{ file.name }}</span>
                </button>
                <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500">
                    <span>{{ formatFileSize(file.size) }}</span>
                    <span>{{ file.last_modified }}</span>
                </div>
            </div>
            <div class="flex flex-col space-y-1">
                <button
                    v-if="isPreviewable"
                    @click="$emit('preview')"
                    class="p-1.5 text-gray-500 active:text-gray-700"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
                <button
                    @click="$emit('download')"
                    class="p-1.5 text-blue-500"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </button>
                <button
                    @click="$emit('delete')"
                    :disabled="isDeleting"
                    class="p-1.5"
                    :class="isDeleting ? 'text-gray-300' : 'text-red-500'"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    file: Object,
    isSelected: Boolean,
    isDeleting: Boolean,
});

const emit = defineEmits(['toggle-selection', 'rename', 'preview', 'download', 'delete']);

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const getFileIcon = (filename) => {
    const ext = filename.split('.').pop().toLowerCase();
    const iconMap = {
        // Images
        jpg: 'image', jpeg: 'image', png: 'image', gif: 'image', svg: 'image', webp: 'image', bmp: 'image',
        // Videos
        mp4: 'video', avi: 'video', mkv: 'video', mov: 'video', wmv: 'video', webm: 'video',
        // Documents
        pdf: 'pdf',
        doc: 'word', docx: 'word',
        xls: 'excel', xlsx: 'excel', csv: 'excel',
        ppt: 'powerpoint', pptx: 'powerpoint',
        txt: 'text', log: 'text', md: 'text',
        // Archives
        zip: 'archive', rar: 'archive', tar: 'archive', gz: 'archive', '7z': 'archive',
        // Code
        js: 'code', ts: 'code', jsx: 'code', tsx: 'code',
        py: 'code', java: 'code', cpp: 'code', c: 'code', h: 'code', hpp: 'code',
        html: 'code', css: 'code', scss: 'code', sass: 'code', less: 'code',
        json: 'code', xml: 'code', yaml: 'code', yml: 'code',
        php: 'code', rb: 'code', go: 'code', rs: 'code', swift: 'code',
        sql: 'code', sh: 'code', bash: 'code', ps1: 'code', bat: 'code',
        vue: 'code', env: 'code', gitignore: 'code', dockerfile: 'code',
    };
    
    return iconMap[ext] || 'file';
};

const isFilePreviewable = (filename) => {
    const ext = filename.split('.').pop().toLowerCase();
    const previewableExtensions = [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp',
        // Videos
        'mp4', 'webm', 'mov', 'avi', 'mkv',
        // Audio
        'mp3', 'wav', 'ogg', 'm4a', 'flac',
        // PDF
        'pdf',
        // Text/Code
        'txt', 'log', 'md',
        'js', 'ts', 'jsx', 'tsx',
        'css', 'scss', 'sass',
        'html', 'xml', 'json',
        'php', 'py', 'java', 
        'c', 'cpp', 'h',
        'sql', 'sh', 'yml', 'yaml'
    ];
    
    return previewableExtensions.includes(ext);
};

const fileType = computed(() => getFileIcon(props.file.name));
const isPreviewable = computed(() => isFilePreviewable(props.file.name));

const handleClick = () => {
    if (isPreviewable.value) {
        emit('preview');
    }
};

const fileIcon = computed(() => {
    const icons = {
        'image': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" /></svg>'
        },
        'video': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" /></svg>'
        },
        'pdf': {
            template: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6M9 13h6M9 9h4" /></svg>'
        },
        'excel': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd" /></svg>'
        },
        'word': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 4a1 1 0 011 1v1h2V7a1 1 0 112 0v6a1 1 0 11-2 0v-2H9v2a1 1 0 11-2 0V7a1 1 0 011-1z" clip-rule="evenodd" /></svg>'
        },
        'archive': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /><path fill-rule="evenodd" d="M2 12a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm6 1a1 1 0 100 2h4a1 1 0 100-2H8z" clip-rule="evenodd" /></svg>'
        },
        'code': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>'
        },
        'text': {
            template: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0h8v12H6V4z" clip-rule="evenodd" /><path d="M8 7h4v2H8V7zm0 4h4v2H8v-2z" /></svg>'
        },
        'file': {
            template: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>'
        }
    };
    
    return icons[fileType.value] || icons['file'];
});

const fileIconColor = computed(() => {
    const colors = {
        'image': 'text-green-500',
        'video': 'text-purple-500',
        'pdf': 'text-red-500',
        'excel': 'text-green-600',
        'word': 'text-blue-600',
        'archive': 'text-yellow-600',
        'code': 'text-indigo-500',
        'text': 'text-gray-600',
        'file': 'text-gray-400'
    };
    
    return colors[fileType.value] || 'text-gray-400';
});
</script>