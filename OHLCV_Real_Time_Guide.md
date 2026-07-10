# OHLCV Dibentuk dari Data Trade

## Pengertian

OHLCV (Open, High, Low, Close, Volume) dibentuk dari **trade (matched order)**, bukan dari order book.

Setiap trade minimal memiliki data:

| Field | Keterangan |
|---|---|
| trade_id | ID trade |
| symbol | Pasangan aset |
| price | Harga transaksi |
| quantity | Jumlah aset |
| timestamp | Waktu transaksi |

Contoh:

| trade_id | timestamp | symbol | price | quantity |
|---:|---|---|---:|---:|
|1|10:00:05|BTCUSDT|100000|0.5|
|2|10:00:15|BTCUSDT|100050|0.2|
|3|10:00:30|BTCUSDT|99980|0.8|
|4|10:00:45|BTCUSDT|100100|0.3|
|5|10:00:58|BTCUSDT|100020|1.2|

---

## Cara Membentuk Candle

Semua trade dalam satu interval (misalnya 1 menit) digabung menjadi satu candle.

```
10:00:00
hingga
10:00:59.999
```

Nilai candle:

- Open = harga trade pertama
- High = harga tertinggi
- Low = harga terendah
- Close = harga trade terakhir
- Volume = total quantity

Hasil contoh:

```
Open   = 100000
High   = 100100
Low    = 99980
Close  = 100020
Volume = 3.0 BTC
```

---

## Menentukan Bucket Waktu

Gunakan rumus:

```text
bucket = floor(timestamp / interval) * interval
```

Contoh:

```text
timestamp = 1752043517
interval = 60

bucket = 1752043500
```

Semua trade dengan bucket yang sama masuk ke candle yang sama.

---

## Update Candle Secara Real-time

Jika candle belum ada:

```
open  = price
high  = price
low   = price
close = price
volume= quantity
```

Jika candle sudah ada:

```
high   = max(high, price)
low    = min(low, price)
close  = price
volume = volume + quantity
```

Open tidak pernah berubah sampai candle selesai.

---

## Jika Tidak Ada Trade

Jika satu interval tidak memiliki transaksi:

```
Open  = Close candle sebelumnya
High  = Open
Low   = Open
Close = Open
Volume = 0
```

Hal ini menjaga grafik tetap kontinu.

---

## SQL Dasar

Volume, High, Low:

```sql
SELECT
SUM(quantity) AS volume,
MAX(price) AS high,
MIN(price) AS low
FROM trades
WHERE created_at BETWEEN ...;
```

Open:

```sql
SELECT price
FROM trades
ORDER BY created_at ASC
LIMIT 1;
```

Close:

```sql
SELECT price
FROM trades
ORDER BY created_at DESC
LIMIT 1;
```

---

# Algoritma Real-time

```
Trade Baru
      │
      ▼
Hitung bucket waktu
      │
      ▼
Cari candle berdasarkan bucket
      │
 ┌────┴─────┐
 │          │
Ada?      Tidak
 │          │
 ▼          ▼
Update    Buat candle baru
 │          │
 └────┬─────┘
      ▼
Simpan
      ▼
Kirim ke REST/WebSocket
```

---

# Prompt Implementasi

Buat sistem pembentukan OHLCV secara real-time untuk crypto exchange.

Persyaratan:

1. Sumber data hanya dari trade yang sudah matched.
2. Tentukan bucket menggunakan `floor(timestamp / interval) * interval`.
3. Open berasal dari trade pertama pada bucket.
4. High adalah harga maksimum.
5. Low adalah harga minimum.
6. Close adalah harga trade terakhir.
7. Volume adalah penjumlahan seluruh quantity.
8. Jika candle belum ada, buat otomatis.
9. Jika candle sudah ada, update High, Low, Close, dan Volume.
10. Setelah interval selesai, candle dikunci (final) dan trade berikutnya membentuk candle baru.
11. Jika tidak ada trade dalam satu interval, buat candle kosong dengan OHLC sama dengan Close candle sebelumnya dan Volume = 0.
12. Desain agar efisien untuk REST API dan WebSocket tanpa menghitung ulang seluruh histori.
