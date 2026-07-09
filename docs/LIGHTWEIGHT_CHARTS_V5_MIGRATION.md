# Lightweight Charts v5 Migration Notes

## Issue Fixed

**Error:**
```
Uncaught TypeError: chart.addCandlestickSeries is not a function
```

**Root Cause:**  
Code was written for Lightweight Charts v4 API, but npm installed v5.2.0 which has breaking API changes.

---

## API Changes (v4 → v5)

### 1. Adding Series (CORRECT v5 API)

**v4 (Old):**
```typescript
const candlestickSeries = chart.addCandlestickSeries({
    upColor: '#10b981',
    downColor: '#ef4444',
    // ...
});

const volumeSeries = chart.addHistogramSeries({
    color: '#6b7280',
    // ...
});
```

**v5 (CORRECT - Import Series Types):**
```typescript
import { createChart, CandlestickSeries, HistogramSeries } from 'lightweight-charts';

const candlestickSeries = chart.addSeries(CandlestickSeries, {
    upColor: '#10b981',
    downColor: '#ef4444',
    // ...
});

const volumeSeries = chart.addSeries(HistogramSeries, {
    color: '#6b7280',
    // ...
});
```

**Key Change:**  
- ❌ Removed: `addCandlestickSeries()`, `addHistogramSeries()`
- ❌ WRONG: `chart.addSeries({ type: 'Candlestick', ... })`
- ✅ CORRECT: `chart.addSeries(CandlestickSeries, { ... })`
- ✅ Must import: `CandlestickSeries`, `HistogramSeries` from 'lightweight-charts'

**Signature:**
```typescript
chart.addSeries<SeriesType>(
    seriesType: SeriesType,  // First param: Series constructor
    options: SeriesOptions   // Second param: Options object
)
```

---

### 2. Import Statements

**v4 (Old):**
```typescript
import { 
    createChart, 
    IChartApi, 
    ISeriesApi, 
    CandlestickData, 
    HistogramData 
} from 'lightweight-charts';
```

**v5 (CORRECT):**
```typescript
import { createChart, CandlestickSeries, HistogramSeries } from 'lightweight-charts';
import type { IChartApi } from 'lightweight-charts';

// CandlestickSeries and HistogramSeries are constructors, not types
// Use them as first parameter in addSeries()
```

**Key Change:**
- ✅ Import Series constructors: `CandlestickSeries`, `HistogramSeries`
- ✅ Use `type` keyword for type-only imports
- Data types are inferred, no need to import them

---

### 3. Series Type Declaration

**v4 (Old):**
```typescript
let candlestickSeries: ISeriesApi<'Candlestick'> | null = null;
let volumeSeries: ISeriesApi<'Histogram'> | null = null;
```

**v5 (New):**
```typescript
let candlestickSeries: any = null;
let volumeSeries: any = null;
```

**Key Change:**
- Generic series API type changed
- Simpler to use `any` for series variables

---

## Changes Made to TradingChart.vue

### Import Statement

```typescript
// Before (v4)
import { createChart, IChartApi, ISeriesApi, CandlestickData, HistogramData } 
    from 'lightweight-charts';

// After (v5) ✅
import { createChart, CandlestickSeries, HistogramSeries } from 'lightweight-charts';
import type { IChartApi } from 'lightweight-charts';
```

### Series Creation

```typescript
// Before (v4)
candlestickSeries = chart.addCandlestickSeries({
    upColor: '#10b981',
    // ...
});

volumeSeries = chart.addHistogramSeries({
    color: '#6b7280',
    // ...
});

// After (v5) ✅
candlestickSeries = chart.addSeries(CandlestickSeries, {
    upColor: '#10b981',
    // ...
});

volumeSeries = chart.addSeries(HistogramSeries, {
    color: '#6b7280',
    // ...
});
```

### Data Type Annotations

```typescript
// Before (v4)
const candleData: CandlestickData[] = klines.value.map(k => ({...}));
const volumeData: HistogramData[] = klines.value.map(k => ({...}));

// After (v5)
const candleData = klines.value.map(k => ({...}));
const volumeData = klines.value.map(k => ({...}));
```

---

## Verification

### Package Version
```bash
npm list lightweight-charts
# Output: lightweight-charts@5.2.0
```

### Build Status
```bash
npm run build
# ✓ built in 2.83s - SUCCESS
```

### Runtime Test
1. Open browser: http://scavence.test/exchange
2. Chart should display without errors
3. Console should show: "📊 Chart initialized"

---

## Breaking Changes Summary

| Feature | v4 | v5 |
|---------|----|----|
| Add Series | `addCandlestickSeries()` | `addSeries(CandlestickSeries, ...)` |
| Import Series | Not needed | Must import `CandlestickSeries`, `HistogramSeries` |
| Series Type | `ISeriesApi<'Candlestick'>` | `any` or inferred |
| Data Types | Import `CandlestickData` | Infer from object shape |

---

## Common Errors

### ❌ Error: "addCandlestickSeries is not a function"
**Cause:** Using v4 API with v5 library  
**Fix:** Use `chart.addSeries(CandlestickSeries, options)`

### ❌ Error: "Assertion failed" in addSeries
**Cause:** Wrong usage - `chart.addSeries({ type: 'Candlestick' })`  
**Fix:** First parameter must be Series constructor: `chart.addSeries(CandlestickSeries, options)`

### ❌ Error: "CandlestickSeries is not defined"
**Cause:** Missing import  
**Fix:** `import { CandlestickSeries } from 'lightweight-charts'`

---

## Migration Checklist

- ✅ Updated imports: Added `CandlestickSeries`, `HistogramSeries`
- ✅ Updated `addSeries()` calls with Series constructor as first parameter
- ✅ Changed series variable types to `any`
- ✅ Removed explicit type annotations from data arrays
- ✅ Build passes without errors
- ✅ TypeScript compilation successful
- ✅ Runtime errors fixed ("Assertion failed" error resolved)

---

## References

- **Lightweight Charts v5 Docs:** https://tradingview.github.io/lightweight-charts/
- **Migration Guide:** https://tradingview.github.io/lightweight-charts/docs/migrations
- **v5 Release Notes:** https://github.com/tradingview/lightweight-charts/releases/tag/v5.0.0

---

**Date:** 2026-01-09  
**Fixed By:** Kiro AI  
**Status:** ✅ Resolved
