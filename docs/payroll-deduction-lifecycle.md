# Payroll: pencadangan, kasbon, dan denda

Perbaikan berlaku pada submit/cancel baru di Kalkulasi Payroll. Tidak mengubah saldo
atau riwayat payroll lama secara otomatis.

## Perilaku

- Pencadangan dipotong sebanyak tenor, kemudian dikembalikan sebanyak tenor.
  `setelah_potongan` langsung masuk fase pengembalian; `setelah_kontrak` menunggu
  status karyawan **Contract**. Pembayaran dari fase 0 dihitung sebagai pengembalian
  pertama. Setelah pengembalian dimulai, perubahan status karyawan tidak menundanya.
- Kasbon/denda: saldo dikurangi nominal payroll aktual. Tenor tersisa dihitung
  `ceil(saldo / cicilan normal)`; saldo nol menjadi `END`. Preview cicilan terakhir
  dibatasi sisa saldo. Submit dengan potongan melebihi saldo ditolak, bukan diubah diam-diam.
- Potongan nol tidak mengubah saldo maupun fase. THP saat submit disimpan dari nilai
  akhir yang dikirim frontend (format angka dinormalisasi), tanpa dihitung ulang dari
  komponen payroll. Kalkulasi preview tetap dilakukan oleh showData.
- Submit mengunci karyawan dan sumber potongan dalam transaksi, serta menolak payroll
  aktif lain untuk karyawan/periode yang sama.
- `payroll.deduction_snapshot` menyimpan ID sumber, nominal, kondisi sebelum dan
  sesudah submit. Cancel berulang tidak mengembalikan saldo dua kali. Cancel
  pencadangan memulihkan fase persis sebelum submit; kasbon/denda mengembalikan
  nominal payroll aktual lalu menghitung ulang tenor/status.
- Cancel harus dimulai dari payroll berpotongan yang paling baru. Sumber yang telah
  diedit/diganti atau lebih dari satu sumber aktif yang cocok memerlukan pemeriksaan.
  Submit potongan ke periode sebelum payroll berpotongan yang masih aktif juga ditolak.
- Payroll lama tanpa snapshot dan nominal pencadangan/kasbon/denda bukan nol tidak
  dapat dicancel otomatis. Rekonsiliasi riwayat diperlukan untuk memastikan sumber
  dan fase yang benar; jangan mengisi snapshot dengan perkiraan. Payroll lama tanpa
  ketiga komponen tersebut tetap dapat dicancel.
- Form master pencadangan menyediakan radio tipe pengembalian. Default data baru
  `setelah_potongan`; pilihan tersimpan ditampilkan saat edit/detail. API menolak tipe
  selain `setelah_potongan` dan `setelah_kontrak`, serta mempertahankan tipe lama
  bila klien tidak mengirim tipe saat edit.

## Deployment

Jalankan kedua migration sebelum memakai kode baru:

```sh
php artisan migrate --path=database/migrations/2026_10_08_120000_add_tipe_pengembalian_to_pencadangan_upah_table.php
php artisan migrate --path=database/migrations/2026_10_08_130000_add_deduction_snapshot_to_payroll.php
```

Migration tidak memperbaiki kekurangan/kelebihan pengembalian historis. Jangan drop
kolom snapshot setelah sudah dipakai transaksi baru karena diperlukan untuk cancel.

## Validasi

```sh
php -d extension=pdo_sqlite vendor/bin/phpunit tests/Unit/PayrollDeductionServiceTest.php
```

Pengujian menggunakan SQLite memory, model Eloquent, migration up, dan transaksi
service asli. Meliputi siklus tenor 1/3/6 untuk kedua tipe pencadangan, cancel/resubmit
setiap fase, menunggu Contract, potongan sebagian/terakhir/nol, duplikasi, rollback
atomik, konflik sumber, urutan periode, dan perlindungan payroll legacy.

SQLite tidak membuktikan perilaku row lock/concurrency MySQL. Full query showData,
rollback migration pada MySQL, browser, dan deployment produksi perlu diverifikasi
di staging. Migration belum dijalankan pada database aplikasi.
