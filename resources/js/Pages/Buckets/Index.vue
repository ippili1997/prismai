<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

interface Bucket {
    id: number;
    name: string;
    provider: 'r2' | 's3';
    bucket_name: string;
    is_active: boolean;
    last_connected_at: string | null;
    created_at: string;
}

const props = defineProps<{
    buckets: Bucket[];
}>();

const deletingBucket = ref<number | null>(null);
const showRenameModal = ref(false);
const renamingBucket = ref<Bucket | null>(null);
const newBucketName = ref('');
const renaming = ref(false);


const activateBucket = (bucketId: number) => {
    useForm({}).post(route('buckets.activate', bucketId), {
        preserveScroll: true,
    });
};

const activateAndBrowse = (bucketId: number) => {
    useForm({}).post(route('buckets.activate', bucketId), {
        preserveScroll: true,
        onSuccess: () => {
            window.location.href = route('files.index', { bucket: bucketId });
        }
    });
};

const deleteBucket = (bucketId: number) => {
    if (confirm('Are you sure you want to remove this bucket configuration?')) {
        deletingBucket.value = bucketId;
        useForm({}).delete(route('buckets.destroy', bucketId), {
            preserveScroll: true,
            onFinish: () => {
                deletingBucket.value = null;
            },
        });
    }
};

const showRenameBucket = (bucket: Bucket) => {
    renamingBucket.value = bucket;
    newBucketName.value = bucket.name;
    showRenameModal.value = true;
};

const renameBucket = () => {
    if (!newBucketName.value.trim() || newBucketName.value === renamingBucket.value?.name) {
        showRenameModal.value = false;
        return;
    }
    
    renaming.value = true;
    
    useForm({ name: newBucketName.value.trim() }).patch(route('buckets.rename', renamingBucket.value?.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRenameModal.value = false;
            renamingBucket.value = null;
            newBucketName.value = '';
        },
        onFinish: () => {
            renaming.value = false;
        },
    });
};
</script>

<template>
    <Head title="Buckets" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Storage Buckets
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Provider Selection Cards -->
                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <Link :href="route('buckets.create', { provider: 's3' })" class="block">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-green-500">
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7c0 2.21-3.582 4-8 4S4 9.21 4 7s3.582-4 8-4 8 1.79 8 4z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Amazon S3</h3>
                                <p class="text-sm text-gray-600">Connect your AWS S3 buckets for scalable cloud storage</p>
                                <div class="mt-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        Add →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>

                    <Link :href="route('buckets.create', { provider: 'r2' })" class="block">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-orange-500">
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 bg-orange-100 rounded-full flex items-center justify-center">
                                    <svg class="w-10 h-10 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cloudflare R2</h3>
                                <p class="text-sm text-gray-600">Connect your R2 buckets for S3-compatible storage at the edge</p>
                                <div class="mt-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                        Add →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 overflow-visible">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium">Your Connected Buckets</h3>
                        </div>

                        <div v-if="buckets.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No buckets connected</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by connecting your first storage bucket using the options above.</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div v-for="bucket in buckets" :key="bucket.id" 
                                class="group relative border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all duration-200 cursor-pointer border-l-4"
                                :class="{
                                    '!border-l-green-500': bucket.provider === 's3',
                                    '!border-l-orange-500': bucket.provider === 'r2'
                                }"
                                @click="bucket.is_active ? $inertia.visit(route('files.index', { bucket: bucket.id })) : null">
                                <!-- Hover overlay -->
                                <div v-if="bucket.is_active" 
                                    class="absolute inset-0 bg-gray-900 bg-opacity-0 group-hover:bg-opacity-5 transition-all duration-300 flex items-center justify-center">
                                    <div class="text-gray-700 font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform scale-95 group-hover:scale-100">
                                        Click to browse files
                                    </div>
                                </div>
                                <div class="relative flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center gap-2 group/title">
                                                <span class="relative flex h-2 w-2">
                                                    <span v-if="bucket.is_active" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2"
                                                        :class="bucket.is_active ? 'bg-green-500' : 'bg-red-500'">
                                                    </span>
                                                </span>
                                                <h3 class="text-base font-semibold text-gray-900">
                                                    {{ bucket.name }}
                                                </h3>
                                                <button
                                                    @click.stop="showRenameBucket(bucket)"
                                                    class="p-1 text-gray-400 hover:text-gray-700 opacity-0 group-hover/title:opacity-100 transition-opacity duration-75"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                                :class="{
                                                    'bg-orange-100 text-orange-700': bucket.provider === 'r2',
                                                    'bg-green-100 text-green-700': bucket.provider === 's3'
                                                }">
                                                {{ bucket.provider.toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="mt-1.5 ml-4 space-y-0.5">
                                            <div class="text-sm text-gray-600">
                                                <span class="font-mono">{{ bucket.bucket_name }}</span>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                Last connected: {{ bucket.last_connected_at || 'Never' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="bucket.is_active"
                                            @click.stop="activateBucket(bucket.id)"
                                            class="px-3 py-1 text-xs text-gray-600 hover:text-gray-900 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                                        >
                                            Disconnect
                                        </button>
                                        <button
                                            v-else
                                            @click.stop="activateBucket(bucket.id)"
                                            class="px-3 py-1 text-xs text-gray-600 hover:text-gray-900 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                                        >
                                            Connect
                                        </button>
                                        <div class="relative group/tooltip">
                                            <button
                                                @click.stop="deleteBucket(bucket.id)"
                                                :disabled="deletingBucket === bucket.id"
                                                class="p-1.5 text-gray-400 hover:text-red-600 transition-colors rounded hover:bg-red-50"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-gray-700 bg-white rounded shadow-md border border-gray-200 opacity-0 group-hover/tooltip:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                                Remove bucket
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rename Modal -->
        <Modal :show="showRenameModal" @close="showRenameModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Rename Bucket
                </h2>
                
                <div class="mt-6">
                    <TextInput
                        v-model="newBucketName"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Enter new bucket name"
                        @keyup.enter="renameBucket"
                    />
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showRenameModal = false">
                        Cancel
                    </SecondaryButton>
                    
                    <PrimaryButton
                        @click="renameBucket"
                        :disabled="!newBucketName.trim() || newBucketName === renamingBucket?.name || renaming"
                    >
                        {{ renaming ? 'Renaming...' : 'Rename' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>