<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Sign in - Prism AI" />

        <template #form>
            <div>
                <!-- Logo -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">Prism AI</h1>
                </div>

                <!-- Welcome message -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-900">Sign In</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Access your private cloud storage
                    </p>
                </div>

                <div v-if="status" class="mb-4 text-sm font-medium text-green-600 bg-green-50 p-3 rounded-lg">
                    {{ status }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <InputLabel for="email" value="Email" class="text-sm font-medium text-gray-700" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Enter your email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel for="password" value="Password" class="text-sm font-medium text-gray-700" />
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm text-slate-600 hover:text-slate-500"
                            >
                                Forgot Password?
                            </Link>
                        </div>
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <label class="flex items-center">
                            <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-gray-300 text-slate-600 shadow-sm focus:border-slate-500 focus:ring-slate-500" />
                            <span class="ms-2 text-sm text-gray-600">Remember me</span>
                        </label>
                    </div>

                    <PrimaryButton
                        class="w-full justify-center py-3 bg-gray-900 hover:bg-gray-800 focus:ring-gray-900"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Sign In
                    </PrimaryButton>
                </form>

                <!-- Register link -->
                <div class="mt-6 text-center">
                    <span class="text-sm text-gray-600">Don't have an account?</span>
                    <Link
                        :href="route('register')"
                        class="ml-1 text-sm font-medium text-slate-600 hover:text-slate-500"
                    >
                        Register
                    </Link>
                </div>
            </div>
        </template>

        <template #info>
            <div>
                <h2 class="text-4xl font-bold mb-6">Experience Cloud Storage with Unmatched Privacy</h2>
                
                <p class="text-xl mb-8 leading-relaxed opacity-90">
                    With Prism AI, your files remain yours alone. Utilize your existing AWS S3 or Cloudflare R2 bucket, and let us enhance your experience with a sleek, intuitive interface. Your data, your rules, powered by our technology.
                </p>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 mr-3 mt-0.5 flex-shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <div>
                            <span class="text-lg font-semibold">True Privacy</span>
                            <p class="text-sm opacity-75 mt-1">Your files never touch our servers. Direct bucket access only.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-6 h-6 mr-3 mt-0.5 flex-shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                        </svg>
                        <div>
                            <span class="text-lg font-semibold">Your Storage, Your Rules</span>
                            <p class="text-sm opacity-75 mt-1">Works with existing S3 or R2 buckets. No migration needed.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-6 h-6 mr-3 mt-0.5 flex-shrink-0 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <div>
                            <span class="text-lg font-semibold">Lightning Fast</span>
                            <p class="text-sm opacity-75 mt-1">Direct uploads and downloads. No middleman delays.</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AuthLayout>
</template>