<script setup>
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

const remember = ref(false);
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8"
    >
        <div class="w-full max-w-md space-y-8">
            <div>
                <h2 class="text-center text-3xl font-bold text-gray-900">
                    Scavence
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Sign in to your account
                </p>
            </div>

            <Form
                action="/login"
                method="post"
                class="mt-8 space-y-6"
                #default="{ errors, processing }"
            >
                <div class="space-y-4 rounded-md shadow-sm">
                    <div>
                        <label for="email" class="sr-only">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            class="relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none sm:text-sm"
                            :class="errors.email ? 'border-red-300' : ''"
                            placeholder="Email address"
                        />
                        <p
                            v-if="errors.email"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none sm:text-sm"
                            :class="errors.password ? 'border-red-300' : ''"
                            placeholder="Password"
                        />
                        <p
                            v-if="errors.password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.password }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        v-model="remember"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label
                        for="remember"
                        class="ml-2 block text-sm text-gray-900"
                    >
                        Remember me
                    </label>
                </div>

                <div>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="group relative flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </div>
            </Form>
        </div>
    </div>
</template>
