# How to Add More Kline Intervals

Saat ini sistem hanya menggunakan interval **1 menit (1m)**. Setelah kamu memahami cara kerjanya, kamu bisa menambahkan interval lain dengan mudah.

## 📋 Langkah-langkah

### 1. Edit Config File

Buka file `config/trading.php` dan uncomment interval yang kamu inginkan:

```php
'kline_intervals' => [
    '1m' => 60,      // ✅ Already active
    
    // Uncomment yang kamu butuhkan:
    '3m' => 180,     // 3 minutes
    '5m' => 300,     // 5 minutes
    '15m' => 900,    // 15 minutes
    '1h' => 3600,    // 1 hour
    '4h' => 14400,   // 4 hours
    '1d' => 86400,   // 1 day
],
```

### 2. Update API Validation

Edit file `app/Http/Controllers/Api/KlineController.php`:

```php
// Sebelum:
'interval' => ['required', 'string', 'in:1m'],

// Sesudah (tambahkan interval baru):
'interval' => ['required', 'string', 'in:1m,5m,1h,1d'],
```

### 3. Restart Queue Worker

```bash
# Stop queue worker (Ctrl+C)
# Then restart
php artisan queue:work
```

### 4. Test

```bash
# Test 5-minute interval
curl "http://localhost:8000/api/klines/BTC/USDT?interval=5m"

# Test 1-hour interval
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1h"
```

## 🎯 Itu Saja!

Sistem akan **otomatis** membuat klines untuk semua interval yang aktif setiap kali ada trade.

## 📊 Cara Kerja

Ketika trade execute:
```
1 Trade = Multiple Klines Created
    ├─ 1m kline (bucket: every 60s)
    ├─ 5m kline (bucket: every 300s)
    ├─ 1h kline (bucket: every 3600s)
    └─ 1d kline (bucket: every 86400s)
```

Semua interval menggunakan **data trade yang sama**, tapi di-group ke bucket waktu yang berbeda.

## 💡 Tips

### Start Small
Mulai dengan interval yang sering dipakai:
```php
'1m' => 60,
'5m' => 300,
'15m' => 900,
'1h' => 3600,
'1d' => 86400,
```

### Monitor Performance
Semakin banyak interval, semakin banyak database write. Monitor:
```bash
# Check klines count
php artisan tinker
>>> Kline::count();

# Check by interval
>>> Kline::where('interval', '1m')->count();
>>> Kline::where('interval', '5m')->count();
```

### Fill Historical Data
Jika sudah ada trades tapi belum ada klines:
```bash
php artisan klines:fill-gaps "BTC/USDT" 5m --start=1752000000 --end=1752043600
php artisan klines:fill-gaps "BTC/USDT" 1h --start=1752000000 --end=1752043600
```

## 🚀 Advanced: Custom Interval

Bisa juga buat interval custom:

```php
'2m' => 120,      // 2 minutes
'10m' => 600,     // 10 minutes
'6h' => 21600,    // 6 hours
```

Tapi pastikan interval tersebut memang dibutuhkan oleh user!

## ❓ FAQ

**Q: Apakah perlu restart server?**  
A: Tidak, cukup restart queue worker.

**Q: Apakah data historical akan otomatis terisi?**  
A: Tidak, gunakan command `klines:fill-gaps` untuk historical data.

**Q: Berapa banyak interval yang direkomendasikan?**  
A: Untuk production, 5-7 interval sudah cukup (1m, 5m, 15m, 1h, 4h, 1d).

**Q: Apakah bisa disable interval yang sudah aktif?**  
A: Ya, comment saja di config dan restart queue worker. Data lama tetap tersimpan di database.
