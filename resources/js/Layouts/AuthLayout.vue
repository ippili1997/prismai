<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left side - Form -->
        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <slot name="form" />
            </div>
        </div>

        <!-- Right side - Gradient background with info -->
        <div class="relative hidden w-0 flex-1 lg:block">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900">
                <div class="flex h-full items-center justify-center p-12">
                    <div class="max-w-lg text-white">
                        <slot name="info" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>