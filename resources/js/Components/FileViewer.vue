<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

interface FileInfo {
    name: string;
    path: string;
    size: number;
    type: 'file';
}

interface Props {
    file: FileInfo | null;
    bucketId: number;
    modelValue: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue', 'navigate-prev', 'navigate-next']);

const loading = ref(true);
const error = ref('');
const viewUrl = ref('');
const textContent = ref('');
const isText = ref(false);

// Compute file type based on extension
const fileType = computed(() => {
    if (!props.file) return 'unknown';
    
    const ext = props.file.name.split('.').pop()?.toLowerCase() || '';
    
    const typeMap: Record<string, string> = {
        // Images
        jpg: 'image', jpeg: 'image', png: 'image', gif: 'image', svg: 'image', webp: 'image', bmp: 'image',
        // Videos
        mp4: 'video', webm: 'video', mov: 'video', avi: 'video', mkv: 'video',
        // Audio
        mp3: 'audio', wav: 'audio', ogg: 'audio', m4a: 'audio', flac: 'audio',
        // PDF
        pdf: 'pdf',
        // Text/Code
        txt: 'text', log: 'text', md: 'text',
        js: 'code', ts: 'code', jsx: 'code', tsx: 'code',
        css: 'code', scss: 'code', sass: 'code',
        html: 'code', xml: 'code', json: 'code',
        php: 'code', py: 'code', java: 'code', 
        c: 'code', cpp: 'code', h: 'code',
        sql: 'code', sh: 'code', yml: 'code', yaml: 'code'
    };
    
    return typeMap[ext] || 'unknown';
});

// Check if file is viewable
const isViewable = computed(() => {
    return ['image', 'video', 'audio', 'pdf', 'text', 'code'].includes(fileType.value);
});

// Load view URL when modal opens
const loadViewUrl = async () => {
    if (!props.file || !props.modelValue) return;
    
    loading.value = true;
    error.value = '';
    viewUrl.value = '';
    textContent.value = '';
    isText.value = false;
    
    // Check if file is viewable before making request
    if (!isViewable.value) {
        loading.value = false;
        return;
    }
    
    try {
        const response = await axios.post(route('files.view-url', { bucket: props.bucketId }), {
            key: props.file.path
        });
        
        if (response.data.is_text && response.data.content) {
            textContent.value = response.data.content;
            isText.value = true;
        } else {
            viewUrl.value = response.data.view_url;
            isText.value = false;
        }
    } catch (err: any) {
        console.error('Failed to load view URL:', err);
        error.value = err.response?.data?.error || 'Failed to load file preview';
    } finally {
        loading.value = false;
    }
};

// Watch for modal open
watch(() => props.modelValue, (newValue) => {
    if (newValue && props.file) {
        loadViewUrl();
    }
});

// Watch for file changes (navigation)
watch(() => props.file, (newFile) => {
    if (newFile && props.modelValue) {
        loadViewUrl();
    }
});

// Handle close
const close = () => {
    emit('update:modelValue', false);
    viewUrl.value = '';
    textContent.value = '';
    error.value = '';
    isText.value = false;
};

// Handle keyboard navigation
const handleKeyboard = (e: KeyboardEvent) => {
    if (!props.modelValue) return;
    
    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowLeft') {
        emit('navigate-prev');
    } else if (e.key === 'ArrowRight') {
        emit('navigate-next');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyboard);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyboard);
});

// Format file size
const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <!-- Modal Overlay -->
    <Transition name="modal">
        <div v-if="modelValue" class="fixed inset-0 z-50 overflow-hidden">
            <!-- Background overlay -->
            <div class="absolute inset-0 bg-black bg-opacity-75 cursor-pointer" @click="close"></div>
            
            <!-- Modal Content -->
            <div class="relative h-full flex items-center justify-center p-4" @click.self="close">
                <div class="relative flex items-center">
                    <!-- Previous Button -->
                    <button
                        @click="$emit('navigate-prev')"
                        class="absolute -left-14 top-1/2 -translate-y-1/2 z-10 p-2 bg-white rounded-full shadow-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        title="Previous file (←)"
                    >
                        <svg class="h-6 w-6 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    
                    <!-- Next Button -->
                    <button
                        @click="$emit('navigate-next')"
                        class="absolute -right-14 top-1/2 -translate-y-1/2 z-10 p-2 bg-white rounded-full shadow-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        title="Next file (→)"
                    >
                        <svg class="h-6 w-6 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    
                    <div class="bg-white rounded-lg shadow-xl max-w-6xl max-h-[90vh] w-full min-w-[800px] overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-4 border-b">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ file?.name }}</h3>
                            <p class="text-sm text-gray-500">{{ formatFileSize(file?.size || 0) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Download button -->
                            <a
                                :href="route('files.download', { bucket: bucketId, key: file?.path })"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download
                            </a>
                            <!-- Close button -->
                            <button
                                @click="close"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Content Area -->
                    <div class="relative overflow-auto bg-gray-50 min-h-[600px] h-[calc(90vh-8rem)]">
                        <!-- Loading State -->
                        <div v-if="loading" class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <svg class="animate-spin h-10 w-10 text-gray-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-600">Loading preview...</p>
                            </div>
                        </div>
                        
                        <!-- Error State -->
                        <div v-else-if="error" class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <svg class="h-12 w-12 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-600">{{ error }}</p>
                            </div>
                        </div>
                        
                        <!-- File not viewable -->
                        <div v-else-if="!isViewable" class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <svg class="h-12 w-12 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-600">This file type cannot be previewed</p>
                                <p class="mt-1 text-xs text-gray-500">Please download the file to view it</p>
                            </div>
                        </div>
                        
                        <!-- File Preview -->
                        <div v-else-if="viewUrl || textContent" class="h-full min-h-[600px]">
                            <!-- Image Viewer -->
                            <div v-if="fileType === 'image'" class="h-full min-h-[600px] flex items-center justify-center p-4">
                                <img :src="viewUrl" :alt="file?.name" class="max-w-full max-h-full object-contain" />
                            </div>
                            
                            <!-- PDF Viewer -->
                            <iframe
                                v-else-if="fileType === 'pdf'"
                                :src="viewUrl"
                                class="w-full h-full min-h-[600px]"
                                frameborder="0"
                            ></iframe>
                            
                            <!-- Video Player -->
                            <div v-else-if="fileType === 'video'" class="h-full flex items-center justify-center bg-black">
                                <video
                                    :src="viewUrl"
                                    controls
                                    class="max-w-full max-h-full"
                                >
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                            
                            <!-- Audio Player -->
                            <div v-else-if="fileType === 'audio'" class="h-full flex items-center justify-center">
                                <audio
                                    :src="viewUrl"
                                    controls
                                    class="w-full max-w-md"
                                >
                                    Your browser does not support the audio tag.
                                </audio>
                            </div>
                            
                            <!-- Text/Code Viewer -->
                            <div v-else-if="(fileType === 'text' || fileType === 'code') && isText" class="h-full overflow-auto">
                                <pre class="bg-white p-4 text-sm font-mono whitespace-pre-wrap break-words"><code>{{ textContent }}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.3s ease;
}

.modal-enter-from .relative {
    transform: scale(0.9);
}

.modal-leave-to .relative {
    transform: scale(0.9);
}
</style>