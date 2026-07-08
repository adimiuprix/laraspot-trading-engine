<script setup lang="ts">
interface Trade {
    price: string;
    amount: string;
    side: 'buy' | 'sell';
    time: string;
}

defineProps<{
    trades: Trade[];
    loading: boolean;
}>();

function formatTime(isoString: string) {
    const date = new Date(isoString);

    return date.toLocaleTimeString('en-US', { hour12: false });
}
</script>

<template>
    <div class="h-64 border-t border-gray-700 bg-gray-800">
        <div class="border-b border-gray-700 px-4 py-2">
            <h3 class="text-sm font-semibold">Recent Trades</h3>
        </div>

        <div
            v-if="loading"
            class="flex h-[calc(100%-2.5rem)] items-center justify-center"
        >
            <div class="text-center text-xs text-gray-500">
                <div class="animate-pulse">Loading trades...</div>
            </div>
        </div>

        <div v-else class="h-[calc(100%-2.5rem)] overflow-y-auto">
            <table class="w-full text-xs">
                <thead
                    class="sticky top-0 border-b border-gray-700 bg-gray-800 text-gray-500"
                >
                    <tr>
                        <th class="px-4 py-2 text-left font-normal">
                            Price (USD)
                        </th>
                        <th class="px-4 py-2 text-right font-normal">
                            Amount (BTC)
                        </th>
                        <th class="px-4 py-2 text-right font-normal">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(trade, index) in trades"
                        :key="index"
                        class="hover:bg-gray-750 border-b border-gray-700/50"
                    >
                        <td
                            class="px-4 py-2"
                            :class="
                                trade.side === 'buy'
                                    ? 'text-green-400'
                                    : 'text-red-400'
                            "
                        >
                            {{ parseFloat(trade.price).toLocaleString() }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-300">
                            {{ trade.amount }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-500">
                            {{ formatTime(trade.time) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.bg-gray-750 {
    background-color: #2d3748;
}
</style>
