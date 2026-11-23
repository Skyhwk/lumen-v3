# Catatan Rilis — Jadwal Service Mobil (GA)

**Tanggal rilis (target):** 2026-11-20  
**Modul:** General Affair → Jadwal Service Mobil & Penggunaan Mobil  
**Referensi teknis:** [ROADMAP-JADWAL-SERVICE-MOBIL-SCHEMA.md](./ROADMAP-JADWAL-SERVICE-MOBIL-SCHEMA.md)

---

## Ringkasan untuk pengguna

1. **Data service lebih ringkas** — hasil pekerjaan dan rincian biaya disimpan di satu baris jadwal (bukan tabel detail terpisah).
2. **Merk/tipe mobil** ditampilkan dari **Daftar Mobil** (master); plat tetap tercatat di jadwal.
3. **Mobil yang dijadwalkan service atau sedang di bengkel tidak bisa dipinjam** di menu Penggunaan Mobil pada tanggal yang bentrok.
4. **Form selesai service** mengirim langsung **rincian pekerjaan + biaya** (bukan struktur `details[]` lama).
5. **Estimasi selesai** wajib diisi saat buat/edit jadwal (field `tanggal_selesai_estimasi`).

---

## Perilaku penting

| Situasi | Yang terjadi |
|---------|----------------|
| Buat jadwal service | Mobil tidak muncul di opsi jadwal lain selama rentang overlap aktif |
| Coba pakai mobil yang sedang service | Penggunaan ditolak dengan pesan konflik |
| Pilih tanggal di form Penggunaan Mobil | Daftar mobil disaring: yang bentrok service hilang dari dropdown |
| Selesaikan service | Status **Selesai**; biaya & lampiran tersimpan; mobil bisa dipakai lagi |

---

## Panduan in-app

Tombol **Panduan Penggunaan** di halaman Jadwal Service Mobil.

---

## Setelah deploy

- Tim GA menjalankan checklist UAT: [UAT-JADWAL-SERVICE-MOBIL-CHECKLIST.md](./UAT-JADWAL-SERVICE-MOBIL-CHECKLIST.md)
- Monitor error API `JadwalServiceMobilController` / `PenggunaanMobilController` (422/500) ±7 hari

---

## Rollback (hanya jika darurat)

Urutan `migrate:rollback` dari migrasi terbaru ke terlama (lihat roadmap §10). Rollback tidak mengembalikan konten log historis yang sudah dipindah.