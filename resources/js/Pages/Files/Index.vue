<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import axios from 'axios';

const props = defineProps({
    files: Array,
    folders: Array,
    activeBucket: Object,
    currentPath: String,
    breadcrumb: Array,
});

const fileInput = ref(null);
const uploading = ref(false);
const uploadProgress = ref(0);
const selectedFiles = ref(new Set());
const bulkDeleting = ref(false);

const navigateToFolder = (path) => {
    router.get(route('files.index', { bucket: props.activeBucket.id, prefix: path }));
};

const navigateUp = () => {
    if (props.currentPath) {
        const parts = props.currentPath.split('/').filter(p => p);
        parts.pop();
        const newPath = parts.length > 0 ? parts.join('/') + '/' : '';
        router.get(route('files.index', { bucket: props.activeBucket.id, prefix: newPath }));
    }
};

const triggerFileUpload = () => {
    fileInput.value.click();
};

const handleFileSelect = async (event) => {
    const files = Array.from(event.target.files);
    if (files.length === 0) return;
    
    let successCount = 0;
    let failedFiles = [];
    
    for (const file of files) {
        try {
            await uploadFile(file);
            successCount++;
        } catch (error) {
            failedFiles.push(file.name);
        }
    }
    
    // Clear the input
    event.target.value = '';
    
    // Refresh the file list with appropriate message
    if (successCount > 0) {
        router.reload({ 
            only: ['files', 'folders'],
            onSuccess: () => {
                if (failedFiles.length > 0) {
                    alert(`${successCount} file(s) uploaded successfully. Failed to upload: ${failedFiles.join(', ')}`);
                }
            }
        });
    } else if (failedFiles.length > 0) {
        alert(`Failed to upload: ${failedFiles.join(', ')}`);
    }
};

const uploadFile = async (file) => {
    uploading.value = true;
    uploadProgress.value = 0;
    
    try {
        // Step 1: Get pre-signed upload URL from our server
        const response = await axios.post(route('files.upload-url', { bucket: props.activeBucket.id }), {
            filename: file.name,
            content_type: file.type || 'application/octet-stream',
            prefix: props.currentPath,
        });
        
        const { upload_url, key } = response.data;
        
        // Step 2: Upload file directly to S3/R2 using the pre-signed URL
        await axios.put(upload_url, file, {
            headers: {
                'Content-Type': file.type || 'application/octet-stream',
            },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            },
        });
        
        // Upload successful, no need to reload here as it will be done in handleFileSelect
        
    } catch (error) {
        console.error('Upload failed:', error);
        console.error('Error details:', error.response);
        alert('Upload error: ' + (error.response?.data?.error || error.response?.data?.message || error.message));
        throw error; // Re-throw to be caught by handleFileSelect
    } finally {
        uploading.value = false;
        uploadProgress.value = 0;
    }
};

const downloadFile = (file) => {
    window.location.href = route('files.download', {
        bucket: props.activeBucket.id,
        key: file.path
    });
};

const deleteFile = (file) => {
    if (confirm(`Are you sure you want to delete ${file.name}?`)) {
        router.delete(route('files.destroy', {
            bucket: props.activeBucket.id,
            key: file.path
        }), {
            preserveState: true,
            preserveScroll: true,
        });
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Checkbox selection handlers
const toggleFileSelection = (file) => {
    if (selectedFiles.value.has(file.path)) {
        selectedFiles.value.delete(file.path);
    } else {
        selectedFiles.value.add(file.path);
    }
    selectedFiles.value = new Set(selectedFiles.value); // Trigger reactivity
};

const selectAll = () => {
    if (selectedFiles.value.size === props.files.length) {
        selectedFiles.value.clear();
    } else {
        selectedFiles.value = new Set(props.files.map(f => f.path));
    }
};

const deleteSelected = async () => {
    if (selectedFiles.value.size === 0) return;
    
    if (!confirm(`Are you sure you want to delete ${selectedFiles.value.size} file(s)?`)) {
        return;
    }
    
    bulkDeleting.value = true;
    
    for (const filePath of selectedFiles.value) {
        await router.delete(route('files.destroy', {
            bucket: props.activeBucket.id,
            key: filePath
        }), {
            preserveState: false,
            preserveScroll: true,
        });
    }
    
    selectedFiles.value.clear();
    bulkDeleting.value = false;
    router.reload();
};

// Get file icon based on extension
const getFileIcon = (filename) => {
    const ext = filename.split('.').pop().toLowerCase();
    const iconMap = {
        // Images
        jpg: 'image', jpeg: 'image', png: 'image', gif: 'image', svg: 'image', webp: 'image',
        // Videos
        mp4: 'video', avi: 'video', mkv: 'video', mov: 'video', wmv: 'video',
        // Documents
        pdf: 'pdf',
        doc: 'word', docx: 'word',
        xls: 'excel', xlsx: 'excel', csv: 'excel',
        ppt: 'powerpoint', pptx: 'powerpoint',
        txt: 'text',
        // Archives
        zip: 'archive', rar: 'archive', tar: 'archive', gz: 'archive',
        // Code
        js: 'code', ts: 'code', py: 'code', java: 'code', cpp: 'code', c: 'code',
        html: 'code', css: 'code', json: 'code', xml: 'code',
    };
    
    return iconMap[ext] || 'file';
};
</script>

<template>
    <Head title="Files" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Files
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Browsing: {{ activeBucket.name }} ({{ activeBucket.provider }})
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <PrimaryButton @click="triggerFileUpload" :disabled="uploading">
                        <svg v-if="!uploading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span v-if="uploading">Uploading... {{ uploadProgress }}%</span>
                        <span v-else>Upload Files</span>
                    </PrimaryButton>
                    <DangerButton 
                        v-if="selectedFiles.size > 0" 
                        @click="deleteSelected"
                        :disabled="bulkDeleting"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Selected ({{ selectedFiles.size }})
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Breadcrumb -->
                    <div class="p-4 border-b border-gray-200">
                        <nav class="flex items-center space-x-2 text-sm">
                            <Link
                                :href="route('files.index', { bucket: activeBucket.id })"
                                class="text-gray-600 hover:text-gray-900"
                            >
                                {{ activeBucket.name }}
                            </Link>
                            <template v-for="(crumb, index) in breadcrumb" :key="index">
                                <span class="text-gray-400">/</span>
                                <Link
                                    :href="route('files.index', { bucket: activeBucket.id, prefix: crumb.path })"
                                    class="text-gray-600 hover:text-gray-900"
                                >
                                    {{ crumb.name }}
                                </Link>
                            </template>
                        </nav>
                    </div>

                    <!-- Files Table -->
                    <div class="p-6">
                        <div v-if="files.length === 0 && folders.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No files</h3>
                            <p class="mt-1 text-sm text-gray-500">This folder is empty.</p>
                        </div>

                        <div v-else class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="w-12 px-6 py-3">
                                            <input
                                                type="checkbox"
                                                @change="selectAll"
                                                :checked="files.length > 0 && selectedFiles.size === files.length"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Size
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Modified
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Up navigation -->
                                    <tr v-if="currentPath" class="hover:bg-gray-50 cursor-pointer" @click="navigateUp">
                                        <td class="px-6 py-4"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                </svg>
                                                ..
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"></td>
                                    </tr>

                                    <!-- Folders -->
                                    <tr v-for="folder in folders" :key="folder.path" class="hover:bg-gray-50 cursor-pointer" @click="navigateToFolder(folder.path)">
                                        <td class="px-6 py-4"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-blue-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                </svg>
                                                {{ folder.name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"></td>
                                    </tr>

                                    <!-- Files -->
                                    <tr v-for="file in files" :key="file.path" class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <input
                                                type="checkbox"
                                                :checked="selectedFiles.has(file.path)"
                                                @change="toggleFileSelection(file)"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center">
                                                <!-- File type icons with unique shapes -->
                                                <template v-if="getFileIcon(file.name) === 'image'">
                                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'video'">
                                                    <svg class="h-5 w-5 text-purple-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'pdf'">
                                                    <svg class="h-5 w-5 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6M9 13h6M9 9h4" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'excel'">
                                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'word'">
                                                    <svg class="h-5 w-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 4a1 1 0 011 1v1h2V7a1 1 0 112 0v6a1 1 0 11-2 0v-2H9v2a1 1 0 11-2 0V7a1 1 0 011-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'archive'">
                                                    <svg class="h-5 w-5 text-yellow-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                                        <path fill-rule="evenodd" d="M2 12a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm6 1a1 1 0 100 2h4a1 1 0 100-2H8z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'code'">
                                                    <svg class="h-5 w-5 text-indigo-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                <template v-else-if="getFileIcon(file.name) === 'text'">
                                                    <svg class="h-5 w-5 text-gray-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0h8v12H6V4z" clip-rule="evenodd" />
                                                        <path d="M8 7h4v2H8V7zm0 4h4v2H8v-2z" />
                                                    </svg>
                                                </template>
                                                <template v-else>
                                                    <svg class="h-5 w-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 00-2 2v6a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2 1 1 0 100-2 2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                {{ file.name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatFileSize(file.size) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ file.last_modified }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button
                                                    @click="downloadFile(file)"
                                                    class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors"
                                                    title="Download"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="deleteFile(file)"
                                                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors"
                                                    title="Delete"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hidden file input -->
        <input
            ref="fileInput"
            type="file"
            multiple
            @change="handleFileSelect"
            class="hidden"
        />
    </AuthenticatedLayout>
</template>