<script setup lang="ts">
import { router } from '@inertiajs/vue3';

defineProps<{
    userName: string;
}>();

function logout() {
    router.post('/logout');
}
</script>

<template>
    <nav class="border-b border-gray-700 bg-gray-800">
        <div class="px-4">
            <div class="flex h-14 justify-between">
                <div class="flex items-center space-x-8">
                    <h1 class="text-xl font-bold text-indigo-400">Scavence</h1>
                    <div class="flex items-center space-x-1">
                        <div class="text-sm">
                            <div class="flex items-baseline space-x-2">
                                <span class="text-2xl font-semibold"
                                    >42,150.50</span
                                >
                                <span class="text-xs text-gray-400">USD</span>
                            </div>
                            <div class="text-xs text-green-400">
                                +2.5% (24h)
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center space-x-4 text-xs text-gray-400"
                    >
                        <div>
                            <div class="text-gray-500">24h High</div>
                            <div class="text-gray-200">42,850.00</div>
                        </div>
                        <div>
                            <div class="text-gray-500">24h Low</div>
                            <div class="text-gray-200">41,200.30</div>
                        </div>
                        <div>
                            <div class="text-gray-500">24h Volume</div>
                            <div class="text-gray-200">15,234 BTC</div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-400">
                        {{ userName }}
                    </span>
                    <button
                        @click="logout"
                        class="rounded bg-gray-700 px-4 py-1.5 text-sm transition hover:bg-gray-600"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>
</template>
