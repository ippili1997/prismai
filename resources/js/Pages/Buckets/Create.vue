<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    provider: 'r2',
    bucket_name: '',
    region: '',
    access_key_id: '',
    secret_access_key: '',
    endpoint: '',
    public_url: '',
});

const submit = () => {
    form.post(route('buckets.store'), {
        onFinish: () => {
            if (!form.hasErrors) {
                form.reset('access_key_id', 'secret_access_key');
            }
        },
    });
};
</script>

<template>
    <Head title="Add Bucket" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Add Storage Bucket
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
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
                            </div>

                            <!-- Provider Selection -->
                            <div>
                                <InputLabel for="provider" value="Storage Provider" />
                                <select
                                    id="provider"
                                    v-model="form.provider"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="r2">Cloudflare R2</option>
                                    <option value="s3">Amazon S3</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.provider" />
                            </div>

                            <!-- Bucket Name -->
                            <div>
                                <InputLabel for="bucket_name" value="Bucket Name (from provider)" />
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
                                    The exact bucket name from {{ form.provider === 'r2' ? 'Cloudflare R2' : 'AWS S3' }}
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
                            </div>

                            <!-- Access Key ID -->
                            <div>
                                <InputLabel for="access_key_id" value="Access Key ID (from provider)" />
                                <TextInput
                                    id="access_key_id"
                                    type="text"
                                    class="mt-1 block w-full font-mono text-sm"
                                    v-model="form.access_key_id"
                                    required
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.access_key_id" />
                            </div>

                            <!-- Secret Access Key -->
                            <div>
                                <InputLabel for="secret_access_key" value="Secret Access Key (from provider)" />
                                <TextInput
                                    id="secret_access_key"
                                    type="password"
                                    class="mt-1 block w-full font-mono text-sm"
                                    v-model="form.secret_access_key"
                                    required
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.secret_access_key" />
                            </div>

                            <!-- Endpoint (R2 only) -->
                            <div v-if="form.provider === 'r2'">
                                <InputLabel for="endpoint" value="Endpoint URL (from R2 dashboard)" />
                                <TextInput
                                    id="endpoint"
                                    type="url"
                                    class="mt-1 block w-full"
                                    v-model="form.endpoint"
                                    :required="form.provider === 'r2'"
                                    placeholder="https://account-id.r2.cloudflarestorage.com"
                                />
                                <InputError class="mt-2" :message="form.errors.endpoint" />
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
                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing">
                                    {{ form.processing ? 'Testing Connection...' : 'Add Bucket' }}
                                </PrimaryButton>

                                <Link
                                    :href="route('buckets.index')"
                                    class="text-sm text-gray-600 hover:text-gray-900"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </form>

                        <!-- Help Text -->
                        <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">
                                {{ form.provider === 'r2' ? 'Cloudflare R2' : 'Amazon S3' }} Configuration Help
                            </h3>
                            <div v-if="form.provider === 'r2'" class="text-sm text-blue-700 space-y-1">
                                <p>1. Go to Cloudflare Dashboard → R2 → Manage R2 API Tokens</p>
                                <p>2. Create a token with Object Read & Write permissions</p>
                                <p>3. Copy the Access Key ID and Secret Access Key</p>
                                <p>4. Find your endpoint URL in R2 → Settings</p>
                            </div>
                            <div v-else class="text-sm text-blue-700 space-y-1">
                                <p>1. Go to AWS IAM Console → Users → Security credentials</p>
                                <p>2. Create an access key for programmatic access</p>
                                <p>3. Ensure the user has S3 read/write permissions</p>
                                <p>4. Use your bucket's region (e.g., us-east-1, eu-west-1)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>