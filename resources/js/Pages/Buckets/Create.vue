<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    provider?: string;
}>();

const form = useForm({
    name: '',
    provider: props.provider || 'r2',
    bucket_name: '',
    region: '',
    access_key_id: '',
    secret_access_key: '',
    endpoint: '',
    public_url: '',
});

const corsPolicy = `[
  {
    "AllowedOrigins": [
      "https://prismai.up.railway.app"
    ],
    "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]`;

const submit = () => {
    form.post(route('buckets.store'), {
        onFinish: () => {
            if (!form.hasErrors) {
                form.reset('access_key_id', 'secret_access_key');
            }
        },
    });
};

const copied = ref(false);

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(corsPolicy);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy text: ', err);
    }
};
</script>

<template>
    <Head title="Add Bucket" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Add {{ form.provider === 'r2' ? 'Cloudflare R2' : 'Amazon S3' }} Bucket
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Provider Badge -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Provider:</span>
                            <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full"
                                :class="{
                                    'bg-orange-100 text-orange-800': form.provider === 'r2',
                                    'bg-green-100 text-green-800': form.provider === 's3'
                                }">
                                {{ form.provider === 'r2' ? 'Cloudflare R2' : 'Amazon S3' }}
                            </span>
                        </div>
                    </div>

                    <!-- User Configuration Container -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-base font-medium text-gray-900">Your Configuration</h3>
                        </div>
                        <div class="p-4 space-y-4">
                            <!-- Friendly Name -->
                            <div>
                                <InputLabel for="name" value="Friendly Name" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    placeholder="My Storage Bucket"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                                <p class="mt-1 text-sm text-gray-600">
                                    A name to help you identify this bucket in Prism AI
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Provider Credentials Container -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-base font-medium text-gray-900">
                                {{ form.provider === 'r2' ? 'Cloudflare R2' : 'AWS S3' }} Credentials
                            </h3>
                        </div>
                        <div class="p-4 space-y-4">
                            <!-- Bucket Name -->
                            <div>
                                <InputLabel for="bucket_name" value="Bucket Name" />
                                <TextInput
                                    id="bucket_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.bucket_name"
                                    required
                                    placeholder="my-bucket-name"
                                />
                                <InputError class="mt-2" :message="form.errors.bucket_name" />
                                <p class="mt-1 text-sm text-gray-600">
                                    The exact bucket name you created in {{ form.provider === 'r2' ? 'Cloudflare R2' : 'AWS S3' }}
                                </p>
                            </div>

                            <!-- Region (S3 only) -->
                            <div v-if="form.provider === 's3'">
                                <InputLabel for="region" value="Region" />
                                <TextInput
                                    id="region"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.region"
                                    :required="form.provider === 's3'"
                                    placeholder="us-east-1"
                                />
                                <InputError class="mt-2" :message="form.errors.region" />
                                <p class="mt-1 text-sm text-gray-600">
                                    The AWS region where your bucket is located
                                </p>
                            </div>

                            <!-- Access Key ID -->
                            <div>
                                <InputLabel for="access_key_id" value="Access Key ID" />
                                <TextInput
                                    id="access_key_id"
                                    type="text"
                                    class="mt-1 block w-full font-mono text-sm"
                                    v-model="form.access_key_id"
                                    required
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.access_key_id" />
                                <p class="mt-1 text-sm text-gray-600">
                                    From your {{ form.provider === 'r2' ? 'R2 API token' : 'AWS IAM credentials' }}
                                </p>
                            </div>

                            <!-- Secret Access Key -->
                            <div>
                                <InputLabel for="secret_access_key" value="Secret Access Key" />
                                <TextInput
                                    id="secret_access_key"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    v-model="form.secret_access_key"
                                    required
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.secret_access_key" />
                                <p class="mt-1 text-sm text-gray-600">
                                    From your {{ form.provider === 'r2' ? 'R2 API token' : 'AWS IAM credentials' }}
                                </p>
                            </div>

                            <!-- Endpoint (R2 only) -->
                            <div v-if="form.provider === 'r2'">
                                <InputLabel for="endpoint" value="Endpoint URL" />
                                <TextInput
                                    id="endpoint"
                                    type="url"
                                    class="mt-1 block w-full"
                                    v-model="form.endpoint"
                                    :required="form.provider === 'r2'"
                                    placeholder="https://account-id.r2.cloudflarestorage.com"
                                />
                                <InputError class="mt-2" :message="form.errors.endpoint" />
                                <p class="mt-1 text-sm text-gray-600">
                                    Found in R2 → Settings → S3 API
                                </p>
                            </div>

                            <!-- Public URL (Optional) -->
                            <div>
                                <InputLabel for="public_url" value="Public URL (Optional)" />
                                <TextInput
                                    id="public_url"
                                    type="url"
                                    class="mt-1 block w-full"
                                    v-model="form.public_url"
                                    placeholder="https://your-public-domain.com"
                                />
                                <InputError class="mt-2" :message="form.errors.public_url" />
                                <p class="mt-1 text-sm text-gray-600">
                                    If you have a custom domain configured for your bucket
                                </p>
                            </div>

                            <!-- Connection Error -->
                            <InputError class="mt-2" :message="form.errors.connection" />

                            <!-- Form Actions -->
                            <div class="pt-4 mt-4">
                                <div class="flex items-center justify-end gap-3">
                                    <PrimaryButton :disabled="form.processing" class="px-4 py-2">
                                        {{ form.processing ? 'Testing Connection...' : 'Add Bucket' }}
                                    </PrimaryButton>

                                    <Link
                                        :href="route('buckets.index')"
                                        class="inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-md font-semibold text-xs text-red-700 uppercase tracking-widest hover:border-red-400 hover:text-red-800 focus:outline-none focus:border-red-500 focus:ring focus:ring-red-200 transition ease-in-out duration-150"
                                    >
                                        Cancel
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Configuration Help Container -->
                <div class="bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg border border-blue-200">
                    <div class="p-4 bg-blue-50 border-b border-blue-200">
                        <h3 class="text-base font-medium text-blue-900">
                            {{ form.provider === 'r2' ? 'Cloudflare R2' : 'Amazon S3' }} Configuration Help
                        </h3>
                    </div>
                    <div class="p-4">
                        <div v-if="form.provider === 'r2'" class="text-sm text-blue-700 space-y-1">
                            <p class="font-medium text-blue-600 mb-2">To get your R2 credentials:</p>
                            <ol class="list-decimal list-inside space-y-1 text-xs">
                                <li>Go to Cloudflare Dashboard → R2 → Manage R2 API Tokens</li>
                                <li>Create a token with Object Read & Write permissions</li>
                                <li>Copy the Access Key ID and Secret Access Key</li>
                                <li>Find your endpoint URL in R2 → Settings → S3 API</li>
                            </ol>
                        </div>
                        <div v-else class="text-sm text-blue-700 space-y-1">
                            <p class="font-medium text-blue-600 mb-2">To get your S3 credentials:</p>
                            <ol class="list-decimal list-inside space-y-1 text-xs">
                                <li>Go to AWS IAM Console → Users → Security credentials</li>
                                <li>Create an access key for programmatic access</li>
                                <li>Ensure the user has S3 read/write permissions</li>
                                <li>Use your bucket's region (e.g., us-east-1, eu-west-1)</li>
                            </ol>
                        </div>

                        <!-- CORS Policy for R2 -->
                        <div v-if="form.provider === 'r2'" class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-200">
                            <h3 class="text-xs font-medium text-amber-800 mb-1">
                                Required CORS Policy for R2
                            </h3>
                            <p class="text-xs text-amber-700 mb-2">
                                Configure this CORS policy in R2 → Your Bucket → Settings → CORS Policy:
                            </p>
                            <div class="bg-gray-900 rounded-md overflow-x-auto relative">
                                <div class="flex items-center justify-between p-2 border-b border-gray-700">
                                    <span class="text-xs text-gray-400">CORS Configuration</span>
                                    <button
                                        type="button"
                                        @click="copyToClipboard"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-300 bg-gray-800 rounded hover:bg-gray-700 transition-colors"
                                    >
                                        <svg v-if="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ copied ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                                <pre class="text-xs text-gray-100 p-3">{{ corsPolicy }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>