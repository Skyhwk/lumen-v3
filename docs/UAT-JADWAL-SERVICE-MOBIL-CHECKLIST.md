# Checklist UAT — Jadwal Service Mobil

Jalankan di **staging** sebelum produksi. Centang `PASS` / `FAIL` / `N/A`; catat bug di tiket terpisah.

| # | Skenario | Langkah singkat | PASS | FAIL | Catatan |
|---|----------|-----------------|------|------|---------|
| 1 | Buat jadwal mobil A 1–5 Des | Buat jadwal; cek tab Jadwal | | | |
| 2 | Mobil tidak double-book | Buat jadwal kedua mobil A overlap | Harus ditolak | | |
| 3 | Edit estimasi (dijadwalkan) | Ubah `tanggal_selesai_estimasi` | Log/update OK | | |
| 4 | Mulai service hari H | Mulai = hari ini + bengkel | Pindah ke Dalam Perbaikan | | |
| 5 | Mulai bukan hari H | Coba mulai tanpa edit tanggal | Ditolak FE/BE | | |
| 6 | Extend estimasi + alasan | Tab perbaikan → ubah estimasi | Log tercatat | | |
| 7 | Complete + biaya + lampiran | Selesaikan dengan file PDF/gambar | Riwayat tampil biaya | | |
| 8 | Cancel + alasan | Batalkan jadwal | Mobil bisa dijadwalkan lagi | | |
| 9 | Penggunaan overlap service | Ajukan pemakaian tanggal bentrok | Ditolak + pesan jelas | | |
| 10 | Penggunaan tidak overlap | Tanggal di luar service | Lolos | | |
| 11 | Filter mobil pemakaian | Isi tanggal mulai/selesai di form | Mobil busy tidak di dropdown | | |
| 12 | Riwayat detail | Lihat detail selesai | Hasil pekerjaan & biaya | | |

**Disetujui GA:** _______________  **Tanggal:** _______________