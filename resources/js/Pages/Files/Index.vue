<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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

const navigateToFolder = (path) => {
    router.get(route('files.index', { prefix: path }));
};

const navigateUp = () => {
    if (props.currentPath) {
        const parts = props.currentPath.split('/').filter(p => p);
        parts.pop();
        const newPath = parts.length > 0 ? parts.join('/') + '/' : '';
        router.get(route('files.index', { prefix: newPath }));
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
        const response = await axios.post(route('files.upload-url'), {
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
                <div class="flex space-x-2">
                    <PrimaryButton @click="triggerFileUpload" :disabled="uploading">
                        <svg v-if="!uploading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span v-if="uploading">Uploading... {{ uploadProgress }}%</span>
                        <span v-else>Upload Files</span>
                    </PrimaryButton>
                    <Link
                        :href="route('buckets.index')"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        Manage Buckets
                    </Link>
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
                                :href="route('files.index')"
                                class="text-gray-600 hover:text-gray-900"
                            >
                                {{ activeBucket.name }}
                            </Link>
                            <template v-for="(crumb, index) in breadcrumb" :key="index">
                                <span class="text-gray-400">/</span>
                                <Link
                                    :href="route('files.index', { prefix: crumb.path })"
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Size
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Modified
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Up navigation -->
                                    <tr v-if="currentPath" class="hover:bg-gray-50 cursor-pointer" @click="navigateUp">
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
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"></td>
                                    </tr>

                                    <!-- Folders -->
                                    <tr v-for="folder in folders" :key="folder.path" class="hover:bg-gray-50 cursor-pointer" @click="navigateToFolder(folder.path)">
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
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"></td>
                                    </tr>

                                    <!-- Files -->
                                    <tr v-for="file in files" :key="file.path" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                {{ file.name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatFileSize(file.size) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ file.last_modified }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                @click="downloadFile(file)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                                            >
                                                Download
                                            </button>
                                            <button
                                                @click="deleteFile(file)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Delete
                                            </button>
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