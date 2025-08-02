<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import FileViewer from '@/Components/FileViewer.vue';
import axios from 'axios';
import { downloadZip } from 'client-zip';

const props = defineProps({
    files: Array,
    folders: Array,
    activeBucket: Object,
    currentPath: String,
    breadcrumb: Array,
    hasMore: Boolean,
    nextToken: String,
});

const fileInput = ref(null);
const uploading = ref(false);
const uploadProgress = ref(0);
const totalFilesToUpload = ref(0);
const filesUploaded = ref(0);
const selectedItems = ref(new Set());
const bulkDeleting = ref(false);
const viewerOpen = ref(false);
const selectedFile = ref(null);
const isDragging = ref(false);
const dragCounter = ref(0);
const downloadingFolder = ref(null);
const downloadProgress = ref(0);
const showCreateFolderModal = ref(false);
const newFolderName = ref('');
const creatingFolder = ref(false);
const showRenameModal = ref(false);
const renamingItem = ref(null);
const newItemName = ref('');
const renaming = ref(false);
const showMoveModal = ref(false);
const movingItems = ref([]);
const destinationPath = ref('');
const moving = ref(false);
const folderTree = ref([]);
const loadingFolders = ref(false);
const deletingItems = ref(new Set());
const navigating = ref(false);
const loadingMore = ref(false);
const allFiles = ref([...props.files]);
const allFolders = ref([...props.folders]);
const hasMoreItems = ref(props.hasMore || false);
const currentNextToken = ref(props.nextToken || null);
const downloadingSelected = ref(false);

// Computed property to ensure reactivity
const showLoadMore = computed(() => hasMoreItems.value);

const navigateToFolder = (path) => {
    navigating.value = true;
    router.get(route('files.index', { bucket: props.activeBucket.id, prefix: path }), {}, {
        onFinish: () => navigating.value = false
    });
};

const navigateUp = () => {
    if (props.currentPath) {
        navigating.value = true;
        const parts = props.currentPath.split('/').filter(p => p);
        parts.pop();
        const newPath = parts.length > 0 ? parts.join('/') + '/' : '';
        router.get(route('files.index', { bucket: props.activeBucket.id, prefix: newPath }), {}, {
            onFinish: () => navigating.value = false
        });
    }
};

const triggerFileUpload = () => {
    fileInput.value.click();
};

const handleFileSelect = async (event) => {
    const files = Array.from(event.target.files);
    if (files.length === 0) return;
    
    // Reset progress tracking
    totalFilesToUpload.value = files.length;
    filesUploaded.value = 0;
    uploadProgress.value = 0;
    uploading.value = true;
    
    let successCount = 0;
    let failedFiles = [];
    
    for (const file of files) {
        try {
            await uploadFile(file);
            successCount++;
            filesUploaded.value++;
            uploadProgress.value = Math.round((filesUploaded.value / totalFilesToUpload.value) * 100);
        } catch (error) {
            failedFiles.push(file.name);
            filesUploaded.value++;
            uploadProgress.value = Math.round((filesUploaded.value / totalFilesToUpload.value) * 100);
        }
    }
    
    // Clear the input
    event.target.value = '';
    uploading.value = false;
    uploadProgress.value = 0;
    
    // Clear selections after upload
    selectedItems.value.clear();
    
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

const uploadFile = async (file, relativePath = '') => {
    try {
        // Construct the full path including folder structure
        const fullPath = relativePath || file.name;
        const filename = fullPath.split('/').pop();
        const folderPath = fullPath.substring(0, fullPath.lastIndexOf('/'));
        
        // Step 1: Get pre-signed upload URL from our server
        const response = await axios.post(route('files.upload-url', { bucket: props.activeBucket.id }), {
            filename: fullPath,
            content_type: file.type || 'application/octet-stream',
            prefix: props.currentPath,
        });
        
        const { upload_url, key } = response.data;
        
        // Step 2: Upload file directly to S3/R2 using the pre-signed URL
        await axios.put(upload_url, file, {
            headers: {
                'Content-Type': file.type || 'application/octet-stream',
            },
        });
        
        // Upload successful, no need to reload here as it will be done in handleFileSelect
        
    } catch (error) {
        console.error('Upload failed:', error);
        console.error('Error details:', error.response);
        alert('Upload error: ' + (error.response?.data?.error || error.response?.data?.message || error.message));
        throw error; // Re-throw to be caught by handleFileSelect
    }
};

const downloadFile = (file) => {
    window.location.href = route('files.download', {
        bucket: props.activeBucket.id,
        key: file.path
    });
};

const viewFile = (file) => {
    selectedFile.value = file;
    viewerOpen.value = true;
};

const navigateToPrevFile = () => {
    const currentIndex = props.files.findIndex(f => f.path === selectedFile.value?.path);
    if (currentIndex > 0) {
        selectedFile.value = props.files[currentIndex - 1];
    }
};

const navigateToNextFile = () => {
    const currentIndex = props.files.findIndex(f => f.path === selectedFile.value?.path);
    if (currentIndex < props.files.length - 1) {
        selectedFile.value = props.files[currentIndex + 1];
    }
};

const deleteFile = (file) => {
    if (confirm(`Are you sure you want to delete ${file.name}?`)) {
        deletingItems.value.add(file.path);
        router.delete(route('files.destroy', {
            bucket: props.activeBucket.id,
            key: file.path
        }), {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => deletingItems.value.delete(file.path)
        });
    }
};

const deleteFolder = (folder) => {
    if (confirm(`Are you sure you want to delete the folder "${folder.name}" and all its contents?`)) {
        deletingItems.value.add(folder.path);
        router.delete(route('files.destroy', {
            bucket: props.activeBucket.id,
            key: folder.path
        }), {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => deletingItems.value.delete(folder.path)
        });
    }
};

const downloadFolder = async (folder) => {
    try {
        downloadingFolder.value = folder.path;
        downloadProgress.value = 0;
        
        // Get pre-signed URLs for all files in the folder
        const response = await axios.post(route('files.folder-download-urls', { bucket: props.activeBucket.id }), {
            folder_path: folder.path
        });
        
        const { files, folder_name, total_size, file_count } = response.data;
        
        if (files.length === 0) {
            alert('This folder is empty.');
            return;
        }
        
        // Prepare files for download
        const downloadFiles = await Promise.all(
            files.map(async (file, index) => {
                // Download each file
                const response = await fetch(file.url);
                
                // Update progress
                downloadProgress.value = Math.round(((index + 1) / files.length) * 100);
                
                return {
                    name: file.path,
                    input: response
                };
            })
        );
        
        // Create and download ZIP
        const blob = await downloadZip(downloadFiles).blob();
        
        // Create download link
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${folder_name}.zip`;
        link.click();
        
        // Clean up
        URL.revokeObjectURL(link.href);
        
    } catch (error) {
        console.error('Failed to download folder:', error);
        alert('Failed to download folder: ' + (error.response?.data?.error || error.message));
    } finally {
        downloadingFolder.value = null;
        downloadProgress.value = 0;
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const createFolder = async () => {
    if (!newFolderName.value.trim()) return;
    
    creatingFolder.value = true;
    
    try {
        await axios.post(route('files.create-folder', { bucket: props.activeBucket.id }), {
            folder_name: newFolderName.value.trim(),
            prefix: props.currentPath,
        });
        
        // Close modal and reset
        showCreateFolderModal.value = false;
        newFolderName.value = '';
        
        // Refresh the file list
        router.reload({
            only: ['files', 'folders'],
        });
    } catch (error) {
        console.error('Failed to create folder:', error);
        alert('Failed to create folder: ' + (error.response?.data?.error || error.message));
    } finally {
        creatingFolder.value = false;
    }
};

const showRename = (item) => {
    renamingItem.value = item;
    newItemName.value = item.name;
    showRenameModal.value = true;
};

const renameItem = async () => {
    if (!newItemName.value.trim() || newItemName.value === renamingItem.value.name) {
        showRenameModal.value = false;
        return;
    }
    
    renaming.value = true;
    
    try {
        await axios.post(route('files.rename', { bucket: props.activeBucket.id }), {
            old_path: renamingItem.value.path,
            new_name: newItemName.value.trim(),
            is_folder: renamingItem.value.type === 'folder',
        });
        
        // Close modal and reset
        showRenameModal.value = false;
        renamingItem.value = null;
        newItemName.value = '';
        
        // Refresh the file list
        router.reload({
            only: ['files', 'folders'],
        });
    } catch (error) {
        console.error('Failed to rename:', error);
        alert('Failed to rename: ' + (error.response?.data?.error || error.message));
    } finally {
        renaming.value = false;
    }
};

// Move functionality
const showMove = () => {
    if (selectedItems.value.size === 0) return;
    
    movingItems.value = Array.from(selectedItems.value);
    destinationPath.value = '';
    showMoveModal.value = true;
    loadFolderTree();
};

const loadFolderTree = async () => {
    loadingFolders.value = true;
    try {
        const response = await axios.get(route('files.folder-tree', { bucket: props.activeBucket.id }));
        folderTree.value = response.data.folders;
    } catch (error) {
        console.error('Failed to load folder tree:', error);
        alert('Failed to load folders: ' + (error.response?.data?.error || error.message));
    } finally {
        loadingFolders.value = false;
    }
};

const moveItems = async () => {
    if (moving.value || movingItems.value.length === 0) return;
    
    moving.value = true;
    
    try {
        await axios.post(route('files.move', { bucket: props.activeBucket.id }), {
            items: movingItems.value,
            destination: destinationPath.value,
            current_path: props.currentPath,
        });
        
        // Close modal and reset
        showMoveModal.value = false;
        movingItems.value = [];
        destinationPath.value = '';
        selectedItems.value.clear();
        
        // Refresh the file list
        router.reload({
            only: ['files', 'folders'],
        });
    } catch (error) {
        console.error('Failed to move items:', error);
        alert('Failed to move items: ' + (error.response?.data?.error || error.message));
    } finally {
        moving.value = false;
    }
};

// Checkbox selection handlers
const toggleItemSelection = (item) => {
    if (selectedItems.value.has(item.path)) {
        selectedItems.value.delete(item.path);
    } else {
        selectedItems.value.add(item.path);
    }
    selectedItems.value = new Set(selectedItems.value); // Trigger reactivity
};

const selectAll = () => {
    const totalItems = props.files.length + props.folders.length;
    if (selectedItems.value.size === totalItems) {
        selectedItems.value.clear();
    } else {
        const allPaths = [
            ...props.files.map(f => f.path),
            ...props.folders.map(f => f.path)
        ];
        selectedItems.value = new Set(allPaths);
    }
};

const deleteSelected = async () => {
    if (selectedItems.value.size === 0) return;
    
    if (!confirm(`Are you sure you want to delete ${selectedItems.value.size} item(s)?`)) {
        return;
    }
    
    bulkDeleting.value = true;
    
    for (const itemPath of selectedItems.value) {
        await router.delete(route('files.destroy', {
            bucket: props.activeBucket.id,
            key: itemPath
        }), {
            preserveState: false,
            preserveScroll: true,
        });
    }
    
    selectedItems.value.clear();
    bulkDeleting.value = false;
    router.reload();
};

const downloadSelected = async () => {
    if (selectedItems.value.size === 0) return;
    
    downloadingSelected.value = true;
    
    try {
        // Get all selected items and categorize them
        const selectedPaths = Array.from(selectedItems.value);
        const itemsToDownload = [];
        
        // Categorize selected items as files or folders
        for (const path of selectedPaths) {
            const folder = allFolders.value.find(f => f.path === path);
            const file = allFiles.value.find(f => f.path === path);
            
            if (folder) {
                itemsToDownload.push({ type: 'folder', item: folder });
            } else if (file) {
                itemsToDownload.push({ type: 'file', item: file });
            }
        }
        
        // Process downloads with a small delay between each to avoid browser blocking
        for (const { type, item } of itemsToDownload) {
            if (type === 'folder') {
                // Use existing downloadFolder function
                await downloadFolder(item);
            } else {
                // Use existing downloadFile function
                downloadFile(item);
            }
            
            // Small delay between downloads to avoid browser popup blocking
            if (itemsToDownload.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 500));
            }
        }
    } catch (error) {
        console.error('Failed to download selected items:', error);
        alert('Failed to download some items. Please try again.');
    } finally {
        downloadingSelected.value = false;
    }
};

// Get file icon based on extension
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
        // Code - comprehensive list
        js: 'code', ts: 'code', jsx: 'code', tsx: 'code',
        py: 'code', java: 'code', cpp: 'code', c: 'code', h: 'code', hpp: 'code',
        html: 'code', css: 'code', scss: 'code', sass: 'code', less: 'code',
        json: 'code', xml: 'code', yaml: 'code', yml: 'code',
        php: 'code', rb: 'code', go: 'code', rs: 'code', swift: 'code',
        sql: 'code', sh: 'code', bash: 'code', ps1: 'code', bat: 'code',
        vue: 'code', jsx: 'code', tsx: 'code',
        env: 'code', gitignore: 'code', dockerfile: 'code',
    };
    
    return iconMap[ext] || 'file';
};

// Check if file is previewable
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

// Drag and drop handlers
const handleDragEnter = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value++;
    isDragging.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value--;
    if (dragCounter.value === 0) {
        isDragging.value = false;
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
};

const handleDrop = async (e) => {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = false;
    dragCounter.value = 0;
    
    const items = Array.from(e.dataTransfer.items);
    const files = [];
    
    // Process all items to collect files
    for (const item of items) {
        if (item.kind === 'file') {
            const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : item.getAsEntry();
            if (entry) {
                await processEntry(entry, files);
            } else {
                // Fallback for browsers that don't support directory upload
                const file = item.getAsFile();
                if (file) {
                    files.push({ file, path: file.name });
                }
            }
        }
    }
    
    // Upload all collected files
    if (files.length > 0) {
        await uploadDroppedFiles(files);
    }
};

// Process file system entries recursively
const processEntry = async (entry, files, path = '') => {
    if (entry.isFile) {
        const file = await new Promise((resolve) => entry.file(resolve));
        files.push({ file, path: path + file.name });
    } else if (entry.isDirectory) {
        const reader = entry.createReader();
        const entries = await new Promise((resolve) => {
            reader.readEntries(resolve);
        });
        
        for (const childEntry of entries) {
            await processEntry(childEntry, files, path + entry.name + '/');
        }
    }
};

// Upload dropped files
const uploadDroppedFiles = async (files) => {
    // Reset progress tracking
    totalFilesToUpload.value = files.length;
    filesUploaded.value = 0;
    uploadProgress.value = 0;
    uploading.value = true;
    
    let successCount = 0;
    let failedFiles = [];
    
    for (const { file, path } of files) {
        try {
            await uploadFile(file, path);
            successCount++;
            filesUploaded.value++;
            uploadProgress.value = Math.round((filesUploaded.value / totalFilesToUpload.value) * 100);
        } catch (error) {
            failedFiles.push(path);
            filesUploaded.value++;
            uploadProgress.value = Math.round((filesUploaded.value / totalFilesToUpload.value) * 100);
        }
    }
    
    uploading.value = false;
    uploadProgress.value = 0;
    
    // Clear selections after upload
    selectedItems.value.clear();
    
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

const loadMore = async () => {
    if (!currentNextToken.value || loadingMore.value) return;
    
    loadingMore.value = true;
    
    try {
        const response = await axios.get(route('files.index', { 
            bucket: props.activeBucket.id, 
            prefix: props.currentPath,
            continuation_token: currentNextToken.value 
        }));
        
        if (response.data.props) {
            allFiles.value.push(...response.data.props.files);
            allFolders.value.push(...response.data.props.folders);
            currentNextToken.value = response.data.props.nextToken;
            hasMoreItems.value = response.data.props.hasMore;
        }
    } catch (error) {
        console.error('Failed to load more items:', error);
    } finally {
        loadingMore.value = false;
    }
};
</script>

<template>
    <Head title="Files" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Files
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Browsing: {{ activeBucket.name }} ({{ activeBucket.provider }})
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <SecondaryButton v-if="selectedItems.size === 0" @click="showCreateFolderModal = true">
                        <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        </svg>
                        <span>New Folder</span>
                    </SecondaryButton>
                    <PrimaryButton v-if="selectedItems.size === 0" @click="triggerFileUpload" :disabled="uploading">
                        <svg v-if="!uploading" class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span v-if="uploading">{{ uploadProgress }}%</span>
                        <span v-else>Upload</span>
                    </PrimaryButton>
                    <SecondaryButton 
                        v-if="selectedItems.size > 0" 
                        @click="downloadSelected"
                        :disabled="downloadingSelected"
                    >
                        <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Download</span> ({{ selectedItems.size }})
                    </SecondaryButton>
                    <SecondaryButton 
                        v-if="selectedItems.size > 0" 
                        @click="showMove"
                        :disabled="moving"
                    >
                        <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                        <span>Move</span> ({{ selectedItems.size }})
                    </SecondaryButton>
                    <DangerButton 
                        v-if="selectedItems.size > 0" 
                        @click="deleteSelected"
                        :disabled="bulkDeleting"
                    >
                        <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Delete</span> ({{ selectedItems.size }})
                    </DangerButton>
                </div>
            </div>
        </template>

        <div 
            class="py-6 min-h-screen"
            @dragenter.prevent="handleDragEnter"
            @dragleave.prevent="handleDragLeave"
            @dragover.prevent="handleDragOver"
            @drop.prevent="handleDrop"
        >
            <!-- Drag overlay -->
            <div
                v-if="isDragging"
                class="fixed inset-0 z-50 bg-indigo-50 bg-opacity-90 flex items-center justify-center pointer-events-none"
            >
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="mt-2 text-lg font-medium text-indigo-900">Drop files or folders here</p>
                    <p class="text-sm text-indigo-700">Files will be uploaded to the current folder</p>
                </div>
            </div>
            
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg relative">
                    <!-- Loading overlay -->
                    <div v-if="navigating" class="absolute inset-0 bg-white bg-opacity-75 z-10 flex items-center justify-center">
                        <div class="flex items-center">
                            <svg class="animate-spin h-8 w-8 text-indigo-600 mr-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-gray-600">Loading...</span>
                        </div>
                    </div>
                    
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
                        <div v-if="allFiles.length === 0 && allFolders.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No files</h3>
                            <p class="mt-1 text-sm text-gray-500">This folder is empty.</p>
                            <div class="mt-6">
                                <p class="text-sm text-gray-500">
                                    Drag and drop files or folders here, or
                                </p>
                                <button
                                    @click="triggerFileUpload"
                                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-500"
                                >
                                    browse to upload
                                </button>
                            </div>
                        </div>

                        <div v-else>
                            <!-- Desktop view header - Hidden on mobile -->
                            <div class="hidden sm:block bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center">
                                    <div class="w-12 px-2">
                                        <input
                                            type="checkbox"
                                            @change="selectAll"
                                            :checked="(files.length + folders.length) > 0 && selectedItems.size === (files.length + folders.length)"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div class="flex-1 grid grid-cols-12 gap-4 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <div class="col-span-5">Name</div>
                                        <div class="col-span-2">Size</div>
                                        <div class="col-span-3">Modified</div>
                                        <div class="col-span-2 text-right pr-4">Actions</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mobile select all bar -->
                            <div class="sm:hidden bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        @change="selectAll"
                                        :checked="(files.length + folders.length) > 0 && selectedItems.size === (files.length + folders.length)"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Select All</span>
                                </label>
                                <span class="text-sm text-gray-500">{{ files.length + folders.length }} items</span>
                            </div>
                            
                            <div class="bg-white divide-y divide-gray-200">
                                <!-- Up navigation -->
                                <div v-if="currentPath" class="hover:bg-gray-50 cursor-pointer px-4 py-3 sm:py-4" @click="navigateUp">
                                    <div class="flex items-center">
                                        <div class="hidden sm:block w-12 px-2"></div>
                                        <div class="flex items-center flex-1">
                                            <svg class="h-5 w-5 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900">..</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Folders -->
                                <div v-for="folder in allFolders" :key="folder.path" class="hover:bg-gray-50 px-4 py-3 sm:py-4">
                                    <!-- Desktop layout -->
                                    <div class="hidden sm:flex items-center">
                                        <div class="w-12 px-2">
                                            <input
                                                type="checkbox"
                                                :checked="selectedItems.has(folder.path)"
                                                @change="toggleItemSelection(folder)"
                                                @click.stop
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div class="flex-1 grid grid-cols-12 gap-4 items-center">
                                            <div class="col-span-5">
                                                <div class="flex items-center group">
                                                    <button @click="navigateToFolder(folder.path)" :disabled="navigating" class="flex items-center" :class="navigating ? 'text-gray-400 cursor-not-allowed' : 'hover:text-indigo-600'">
                                                        <svg class="h-5 w-5 mr-3" :class="navigating ? 'text-gray-400' : 'text-blue-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                        </svg>
                                                        <span class="text-sm font-medium hover:underline">{{ folder.name }}</span>
                                                    </button>
                                                    <button
                                                        @click.stop="showRename(folder)"
                                                        class="ml-2 p-1 text-gray-500 hover:text-gray-700 opacity-0 group-hover:opacity-100 transition-opacity duration-75"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-span-2 text-sm text-gray-500">-</div>
                                            <div class="col-span-3 text-sm text-gray-500">-</div>
                                            <div class="col-span-2">
                                                <div class="flex items-center justify-end space-x-1">
                                                    <button
                                                        @click="downloadFolder(folder)"
                                                        :disabled="downloadingFolder === folder.path"
                                                        class="p-1.5 transition-colors rounded-md"
                                                        :class="downloadingFolder === folder.path ? 'text-gray-300 cursor-not-allowed' : 'text-blue-500 hover:text-blue-700 hover:bg-blue-50'"
                                                        title="Download as ZIP"
                                                    >
                                                        <svg v-if="downloadingFolder !== folder.path" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                        <svg v-else class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="deleteFolder(folder)"
                                                        :disabled="deletingItems.has(folder.path)"
                                                        class="p-1.5 rounded-md transition-colors"
                                                        :class="deletingItems.has(folder.path) ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700 hover:bg-red-50'"
                                                        title="Delete Folder"
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
                                                :checked="selectedItems.has(folder.path)"
                                                @change="toggleItemSelection(folder)"
                                                @click.stop
                                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <button @click="navigateToFolder(folder.path)" :disabled="navigating" class="flex items-center w-full text-left" :class="navigating ? 'text-gray-400 cursor-not-allowed' : ''">
                                                    <svg class="h-5 w-5 mr-2 flex-shrink-0" :class="navigating ? 'text-gray-400' : 'text-blue-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                    </svg>
                                                    <span class="text-sm font-medium text-gray-900 truncate">{{ folder.name }}</span>
                                                </button>
                                                <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                                    <span>Folder</span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col space-y-1">
                                                <button
                                                    @click="downloadFolder(folder)"
                                                    :disabled="downloadingFolder === folder.path"
                                                    class="p-1.5 transition-colors rounded-md"
                                                    :class="downloadingFolder === folder.path ? 'text-gray-300 cursor-not-allowed' : 'text-blue-500'"
                                                >
                                                    <svg v-if="downloadingFolder !== folder.path" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    <svg v-else class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="deleteFolder(folder)"
                                                    :disabled="deletingItems.has(folder.path)"
                                                    class="p-1.5 rounded-md transition-colors"
                                                    :class="deletingItems.has(folder.path) ? 'text-gray-300 cursor-not-allowed' : 'text-red-500'"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Files -->
                                <div v-for="file in allFiles" :key="file.path" class="hover:bg-gray-50 px-4 py-3 sm:py-4">
                                    <!-- Desktop layout -->
                                    <div class="hidden sm:flex items-center">
                                        <div class="w-12 px-2">
                                            <input
                                                type="checkbox"
                                                :checked="selectedItems.has(file.path)"
                                                @change="toggleItemSelection(file)"
                                                @click.stop
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div class="flex-1 grid grid-cols-12 gap-4 items-center">
                                            <div class="col-span-5">
                                                <div class="flex items-center group">
                                                    <button 
                                                        @click="isFilePreviewable(file.name) ? viewFile(file) : null" 
                                                        class="flex items-center"
                                                        :class="isFilePreviewable(file.name) ? 'hover:text-indigo-600 cursor-pointer' : 'cursor-default'"
                                                    >
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
                                                            <svg class="h-5 w-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                            </svg>
                                                        </template>
                                                        <span class="text-sm font-medium" :class="isFilePreviewable(file.name) ? 'hover:underline' : ''">{{ file.name }}</span>
                                                    </button>
                                                    <button
                                                        @click.stop="showRename(file)"
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
                                                        v-if="isFilePreviewable(file.name)"
                                                        @click="viewFile(file)"
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
                                                        :disabled="deletingItems.has(file.path)"
                                                        class="p-1.5 rounded-md transition-colors"
                                                        :class="deletingItems.has(file.path) ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700 hover:bg-red-50'"
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
                                                :checked="selectedItems.has(file.path)"
                                                @change="toggleItemSelection(file)"
                                                @click.stop
                                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <button 
                                                    @click="isFilePreviewable(file.name) ? viewFile(file) : null" 
                                                    class="flex items-center w-full text-left"
                                                    :class="isFilePreviewable(file.name) ? 'active:text-indigo-600' : ''"
                                                >
                                                    <!-- File type icons simplified for mobile -->
                                                    <template v-if="getFileIcon(file.name) === 'image'">
                                                        <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                        </svg>
                                                    </template>
                                                    <template v-else-if="getFileIcon(file.name) === 'video'">
                                                        <svg class="h-5 w-5 text-purple-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
                                                        </svg>
                                                    </template>
                                                    <template v-else-if="getFileIcon(file.name) === 'pdf'">
                                                        <svg class="h-5 w-5 text-red-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                        </svg>
                                                    </template>
                                                    <template v-else-if="getFileIcon(file.name) === 'code'">
                                                        <svg class="h-5 w-5 text-indigo-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </template>
                                                    <template v-else>
                                                        <svg class="h-5 w-5 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                        </svg>
                                                    </template>
                                                    <span class="text-sm font-medium text-gray-900 truncate">{{ file.name }}</span>
                                                </button>
                                                <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500">
                                                    <span>{{ formatFileSize(file.size) }}</span>
                                                    <span>{{ file.last_modified }}</span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col space-y-1">
                                                <button
                                                    v-if="isFilePreviewable(file.name)"
                                                    @click="viewFile(file)"
                                                    class="p-1.5 text-gray-500 active:text-gray-700"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="downloadFile(file)"
                                                    class="p-1.5 text-blue-500"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="deleteFile(file)"
                                                    :disabled="deletingItems.has(file.path)"
                                                    class="p-1.5"
                                                    :class="deletingItems.has(file.path) ? 'text-gray-300' : 'text-red-500'"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Load More Button -->
                        <div v-if="showLoadMore" class="p-4 text-center border-t">
                            <button 
                                @click="loadMore" 
                                :disabled="loadingMore"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="loadingMore" class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="loadingMore">Loading...</span>
                                <span v-else>Load More</span>
                            </button>
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
        
        <!-- File Viewer Modal -->
        <FileViewer 
            v-model="viewerOpen"
            :file="selectedFile"
            :bucket-id="activeBucket.id"
            @navigate-prev="navigateToPrevFile"
            @navigate-next="navigateToNextFile"
        />
        
        <!-- Create Folder Modal -->
        <Teleport to="body">
            <div v-if="showCreateFolderModal" class="fixed inset-0 overflow-y-auto z-50">
                <div class="flex items-end sm:items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showCreateFolderModal = false">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    
                    <div class="inline-block align-bottom bg-white rounded-t-lg sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Create New Folder
                                    </h3>
                                    <div class="mt-4">
                                        <input
                                            v-model="newFolderName"
                                            @keyup.enter="createFolder"
                                            type="text"
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                            placeholder="Folder name"
                                            :disabled="creatingFolder"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <PrimaryButton
                                @click="createFolder"
                                :disabled="!newFolderName.trim() || creatingFolder"
                                class="w-full sm:ml-3 sm:w-auto"
                            >
                                {{ creatingFolder ? 'Creating...' : 'Create' }}
                            </PrimaryButton>
                            <SecondaryButton
                                @click="showCreateFolderModal = false; newFolderName = ''"
                                :disabled="creatingFolder"
                                class="mt-3 w-full sm:mt-0 sm:ml-3 sm:w-auto"
                            >
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
        
        <!-- Rename Modal -->
        <Teleport to="body">
            <div v-if="showRenameModal" class="fixed inset-0 overflow-y-auto z-50">
                <div class="flex items-end sm:items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showRenameModal = false">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    
                    <div class="inline-block align-bottom bg-white rounded-t-lg sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Rename {{ renamingItem?.type === 'folder' ? 'Folder' : 'File' }}
                                    </h3>
                                    <div class="mt-4">
                                        <input
                                            v-model="newItemName"
                                            @keyup.enter="renameItem"
                                            type="text"
                                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                            :placeholder="renamingItem?.name"
                                            :disabled="renaming"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <PrimaryButton
                                @click="renameItem"
                                :disabled="!newItemName.trim() || newItemName === renamingItem?.name || renaming"
                                class="w-full sm:ml-3 sm:w-auto"
                            >
                                {{ renaming ? 'Renaming...' : 'Rename' }}
                            </PrimaryButton>
                            <SecondaryButton
                                @click="showRenameModal = false; renamingItem = null; newItemName = ''"
                                :disabled="renaming"
                                class="mt-3 w-full sm:mt-0 sm:ml-3 sm:w-auto"
                            >
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
        
        <!-- Move Modal -->
        <Teleport to="body">
            <div v-if="showMoveModal" class="fixed inset-0 overflow-y-auto z-50">
                <div class="flex items-end sm:items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showMoveModal = false">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    
                    <div class="inline-block align-bottom bg-white rounded-t-lg sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Move {{ movingItems.length }} Item{{ movingItems.length > 1 ? 's' : '' }}
                                    </h3>
                                    <div class="mt-4">
                                        <p class="text-sm text-gray-500 mb-3">Select destination folder:</p>
                                        
                                        <div v-if="loadingFolders" class="text-center py-4">
                                            <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>
                                        
                                        <div v-else class="max-h-64 overflow-y-auto border border-gray-300 rounded-md">
                                            <!-- Root folder option -->
                                            <div 
                                                @click="destinationPath = ''"
                                                class="px-3 py-2 hover:bg-gray-50 cursor-pointer flex items-center"
                                                :class="{ 'bg-blue-50': destinationPath === '' }"
                                            >
                                                <svg class="h-5 w-5 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                                </svg>
                                                <span class="text-sm">{{ activeBucket.name }} (root)</span>
                                            </div>
                                            
                                            <!-- Folder tree -->
                                            <div v-for="folder in folderTree" :key="folder.path">
                                                <div 
                                                    @click="destinationPath = folder.path"
                                                    class="px-3 py-2 hover:bg-gray-50 cursor-pointer flex items-center"
                                                    :class="{ 'bg-blue-50': destinationPath === folder.path }"
                                                    :style="{ paddingLeft: (folder.level * 20 + 12) + 'px' }"
                                                >
                                                    <svg class="h-5 w-5 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                    </svg>
                                                    <span class="text-sm">{{ folder.name }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <p v-if="destinationPath" class="mt-2 text-sm text-gray-600">
                                            Moving to: <span class="font-medium">{{ destinationPath || 'Root' }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <PrimaryButton
                                @click="moveItems"
                                :disabled="moving || loadingFolders"
                                class="w-full sm:ml-3 sm:w-auto"
                            >
                                {{ moving ? 'Moving...' : 'Move' }}
                            </PrimaryButton>
                            <SecondaryButton
                                @click="showMoveModal = false; movingItems = []; destinationPath = ''"
                                :disabled="moving"
                                class="mt-3 w-full sm:mt-0 sm:ml-3 sm:w-auto"
                            >
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>