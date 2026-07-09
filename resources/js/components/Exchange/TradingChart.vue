<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { createChart, CandlestickSeries, HistogramSeries } from 'lightweight-charts';
import type { IChartApi } from 'lightweight-charts';
import { useKlines, type Kline } from '@/composables/useKlines';

// Props
const props = defineProps<{
    symbol?: string;
    interval?: string;
}>();

// Use props with defaults
const symbol = props.symbol || 'BTCUSDT';
const interval = props.interval || '1m';

// Chart container ref
const chartContainer = ref<HTMLDivElement | null>(null);

// Chart instances
let chart: IChartApi | null = null;
let candlestickSeries: any = null;
let volumeSeries: any = null;

// Composable for klines data
const { klines, loading, error, fetchKlines, subscribeToUpdates } = useKlines(
    symbol,
    interval,
);

/**
 * Initialize chart with Lightweight Charts library
 */
function initChart() {
    if (!chartContainer.value) return;

    // Create chart (v5 API)
    chart = createChart(chartContainer.value, {
        width: chartContainer.value.clientWidth,
        height: chartContainer.value.clientHeight,
        layout: {
            background: { color: '#1a202c' },
            textColor: '#d1d5db',
        },
        grid: {
            vertLines: { color: '#374151' },
            horzLines: { color: '#374151' },
        },
        crosshair: {
            mode: 1, // Normal crosshair
        },
        rightPriceScale: {
            borderColor: '#4b5563',
        },
        timeScale: {
            borderColor: '#4b5563',
            timeVisible: true,
            secondsVisible: false,
        },
    });

    // Add candlestick series (v5 API - first param is Series type)
    candlestickSeries = chart.addSeries(CandlestickSeries, {
        upColor: '#10b981',
        downColor: '#ef4444',
        borderUpColor: '#10b981',
        borderDownColor: '#ef4444',
        wickUpColor: '#10b981',
        wickDownColor: '#ef4444',
    });

    // Add volume histogram series
    volumeSeries = chart.addSeries(HistogramSeries, {
        color: '#6b7280',
        priceFormat: {
            type: 'volume',
        },
        priceScaleId: '',
    });

    // Set volume scale to 25% of chart height
    chart.priceScale('').applyOptions({
        scaleMargins: {
            top: 0.7, // Volume takes bottom 30%
            bottom: 0,
        },
    });

    // Handle window resize
    window.addEventListener('resize', handleResize);

    console.log('📊 Chart initialized');
}

/**
 * Load historical klines data into chart
 */
function loadChartData() {
    if (!candlestickSeries || !volumeSeries || klines.value.length === 0) {
        return;
    }

    // Prepare candlestick data
    const candleData = klines.value.map((k: Kline) => ({
        time: k.time,
        open: k.open,
        high: k.high,
        low: k.low,
        close: k.close,
    }));

    // Prepare volume data
    const volumeData = klines.value.map((k: Kline) => ({
        time: k.time,
        value: k.volume,
        color: k.close >= k.open ? '#10b98166' : '#ef444466', // Semi-transparent
    }));

    // Set data to series
    candlestickSeries.setData(candleData);
    volumeSeries.setData(volumeData);

    console.log(`📈 Loaded ${candleData.length} candles to chart`);
}

/**
 * Update chart with new kline data (real-time)
 */
function updateChart(kline: Kline) {
    if (!candlestickSeries || !volumeSeries) return;

    console.log('📊 Updating chart with kline:', kline.time);

    // Update candlestick
    candlestickSeries.update({
        time: kline.time,
        open: kline.open,
        high: kline.high,
        low: kline.low,
        close: kline.close,
    });

    // Update volume
    volumeSeries.update({
        time: kline.time,
        value: kline.volume,
        color: kline.close >= kline.open ? '#10b98166' : '#ef444466',
    });
}

/**
 * Handle window resize
 */
function handleResize() {
    if (chart && chartContainer.value) {
        chart.applyOptions({
            width: chartContainer.value.clientWidth,
            height: chartContainer.value.clientHeight,
        });
    }
}

/**
 * Cleanup chart
 */
function destroyChart() {
    if (chart) {
        window.removeEventListener('resize', handleResize);
        chart.remove();
        chart = null;
        candlestickSeries = null;
        volumeSeries = null;
        console.log('🗑️ Chart destroyed');
    }
}

// Lifecycle
onMounted(async () => {
    console.log(`🚀 Mounting TradingChart for ${symbol} ${interval}`);

    // Initialize chart
    initChart();

    // Fetch historical data
    await fetchKlines(500);

    // Load initial data to chart
    loadChartData();

    // Subscribe to real-time updates
    subscribeToUpdates((kline) => {
        updateChart(kline);
    });
});

onUnmounted(() => {
    destroyChart();
});
</script>

<template>
    <div class="bg-gray-850 relative flex-1 p-4">
        <!-- Loading State -->
        <div
            v-if="loading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-gray-900/50"
        >
            <div class="text-center">
                <svg
                    class="mx-auto mb-2 h-8 w-8 animate-spin text-blue-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                <p class="text-sm text-gray-400">Loading chart data...</p>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-if="error"
            class="absolute inset-0 z-10 flex items-center justify-center bg-gray-900/50"
        >
            <div class="text-center text-red-500">
                <svg
                    class="mx-auto mb-2 h-12 w-12"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                </svg>
                <p class="font-semibold">Failed to load chart</p>
                <p class="text-sm">{{ error }}</p>
            </div>
        </div>

        <!-- Chart Container -->
        <div
            ref="chartContainer"
            class="h-full w-full rounded border border-gray-700"
        ></div>

        <!-- Chart Info Overlay -->
        <div
            class="absolute left-8 top-8 rounded bg-gray-800/80 px-3 py-2 text-sm backdrop-blur-sm"
        >
            <div class="font-semibold text-white">{{ symbol }}</div>
            <div class="text-gray-400">{{ interval.toUpperCase() }}</div>
        </div>
    </div>
</template>

<style scoped>
.bg-gray-850 {
    background-color: #1a202c;
}
</style>
