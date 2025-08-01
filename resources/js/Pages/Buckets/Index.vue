<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

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

const testConnection = (bucketId: number) => {
    useForm({}).post(route('buckets.test', bucketId), {
        preserveScroll: true,
    });
};

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
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-orange-500">
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 bg-orange-100 rounded-full flex items-center justify-center">
                                    <svg class="w-10 h-10 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Amazon S3</h3>
                                <p class="text-sm text-gray-600">Connect your AWS S3 buckets for scalable cloud storage</p>
                                <div class="mt-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                        Add S3 Bucket →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>

                    <Link :href="route('buckets.create', { provider: 'r2' })" class="block">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-blue-500">
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-10 h-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cloudflare R2</h3>
                                <p class="text-sm text-gray-600">Connect your R2 buckets for S3-compatible storage at the edge</p>
                                <div class="mt-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        Add R2 Bucket →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
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

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Provider
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Bucket
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Last Connected
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="bucket in buckets" :key="bucket.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link
                                                v-if="bucket.is_active"
                                                :href="route('files.index', { bucket: bucket.id })"
                                                class="text-sm font-medium text-indigo-600 hover:text-indigo-900 hover:underline cursor-pointer"
                                            >
                                                {{ bucket.name }}
                                            </Link>
                                            <button
                                                v-else
                                                @click="activateAndBrowse(bucket.id)"
                                                class="text-sm font-medium text-gray-600 hover:text-indigo-600 hover:underline cursor-pointer text-left"
                                            >
                                                {{ bucket.name }}
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="{
                                                    'bg-blue-100 text-blue-800': bucket.provider === 'r2',
                                                    'bg-orange-100 text-orange-800': bucket.provider === 's3'
                                                }">
                                                {{ bucket.provider.toUpperCase() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ bucket.bucket_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="bucket.is_active" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                            <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Inactive
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ bucket.last_connected_at || 'Never' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <SecondaryButton
                                                    @click="testConnection(bucket.id)"
                                                    class="text-xs"
                                                >
                                                    Test
                                                </SecondaryButton>
                                                <SecondaryButton
                                                    v-if="!bucket.is_active"
                                                    @click="activateBucket(bucket.id)"
                                                    class="text-xs"
                                                >
                                                    Activate
                                                </SecondaryButton>
                                                <DangerButton
                                                    @click="deleteBucket(bucket.id)"
                                                    :disabled="deletingBucket === bucket.id"
                                                    class="text-xs"
                                                >
                                                    Remove
                                                </DangerButton>
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
    </AuthenticatedLayout>
</template>