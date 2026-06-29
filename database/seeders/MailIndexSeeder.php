<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class MailIndexSeeder extends Seeder
{
    public function run()
    {
        // Fetch real active and inactive employees from the database
        $activeEmp1 = DB::table('master_karyawan')->where('is_active', 1)->first();
        $activeEmp2 = DB::table('master_karyawan')->where('is_active', 1)->skip(1)->first();
        $inactiveEmp = DB::table('master_karyawan')->where('is_active', 0)->first();

        // Fallback to defaults if they don't exist
        $id1 = $activeEmp1 ? $activeEmp1->id : 1;
        $email1 = $activeEmp1 ? $activeEmp1->email : 'admin@example.com';

        $id2 = $activeEmp2 ? $activeEmp2->id : 2;
        $email2 = $activeEmp2 ? $activeEmp2->email : 'admin1@example.com';

        $id3 = $inactiveEmp ? $inactiveEmp->id : 3;
        $email3 = $inactiveEmp ? $inactiveEmp->email : 'vidya@intilab.com';

        // Clean existing mail tables
        DB::table('mail_list_index')->truncate();
        DB::table('mail_folder_meta')->truncate();

        // Seed mail_folder_meta
        $folderMetas = [
            // Employee 1 (Active)
            ['id_karyawan' => $id1, 'folder' => 'inbox', 'total' => 5, 'unread_count' => 2, 'uidnext' => 6, 'last_uid' => 5, 'min_seq' => 1, 'max_seq' => 5, 'indexed_count' => 5, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id1, 'folder' => 'outbox', 'total' => 2, 'unread_count' => 0, 'uidnext' => 3, 'last_uid' => 2, 'min_seq' => 1, 'max_seq' => 2, 'indexed_count' => 2, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id1, 'folder' => 'spam', 'total' => 1, 'unread_count' => 1, 'uidnext' => 2, 'last_uid' => 1, 'min_seq' => 1, 'max_seq' => 1, 'indexed_count' => 1, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id1, 'folder' => 'trash', 'total' => 1, 'unread_count' => 0, 'uidnext' => 2, 'last_uid' => 1, 'min_seq' => 1, 'max_seq' => 1, 'indexed_count' => 1, 'synced_at' => date('Y-m-d H:i:s')],

            // Employee 2 (Active)
            ['id_karyawan' => $id2, 'folder' => 'inbox', 'total' => 3, 'unread_count' => 1, 'uidnext' => 4, 'last_uid' => 3, 'min_seq' => 1, 'max_seq' => 3, 'indexed_count' => 3, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id2, 'folder' => 'outbox', 'total' => 1, 'unread_count' => 0, 'uidnext' => 2, 'last_uid' => 1, 'min_seq' => 1, 'max_seq' => 1, 'indexed_count' => 1, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id2, 'folder' => 'spam', 'total' => 0, 'unread_count' => 0, 'uidnext' => 1, 'last_uid' => 0, 'min_seq' => 0, 'max_seq' => 0, 'indexed_count' => 0, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id2, 'folder' => 'trash', 'total' => 0, 'unread_count' => 0, 'uidnext' => 1, 'last_uid' => 0, 'min_seq' => 0, 'max_seq' => 0, 'indexed_count' => 0, 'synced_at' => date('Y-m-d H:i:s')],

            // Employee 3 (Inactive)
            ['id_karyawan' => $id3, 'folder' => 'inbox', 'total' => 4, 'unread_count' => 2, 'uidnext' => 5, 'last_uid' => 4, 'min_seq' => 1, 'max_seq' => 4, 'indexed_count' => 4, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id3, 'folder' => 'outbox', 'total' => 1, 'unread_count' => 0, 'uidnext' => 2, 'last_uid' => 1, 'min_seq' => 1, 'max_seq' => 1, 'indexed_count' => 1, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id3, 'folder' => 'spam', 'total' => 1, 'unread_count' => 1, 'uidnext' => 2, 'last_uid' => 1, 'min_seq' => 1, 'max_seq' => 1, 'indexed_count' => 1, 'synced_at' => date('Y-m-d H:i:s')],
            ['id_karyawan' => $id3, 'folder' => 'trash', 'total' => 1, 'unread_count' => 0, 'uidnext' => 2, 'last_uid' => 1, 'min_seq' => 1, 'max_seq' => 1, 'indexed_count' => 1, 'synced_at' => date('Y-m-d H:i:s')],
        ];

        DB::table('mail_folder_meta')->insert($folderMetas);

        // Seed mail_list_index
        $emails = [
            // Employee 1 - Inbox
            [
                'id_karyawan' => $id1, 'folder' => 'inbox', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'hrd@perusahaan.com', 'to_addr' => $email1,
                'subject' => 'Pemberitahuan Cuti Bersama 2026', 'email_date' => '2026-06-20 09:00:00',
                'size_bytes' => 15000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id1, 'folder' => 'inbox', 'uid' => 2, 'seq_num' => 2,
                'from_addr' => 'billing@indihome.co.id', 'to_addr' => $email1,
                'subject' => 'Tagihan Internet Bulan Juni 2026', 'email_date' => '2026-06-22 10:30:00',
                'size_bytes' => 45000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id1, 'folder' => 'inbox', 'uid' => 3, 'seq_num' => 3,
                'from_addr' => 'developer@lims.com', 'to_addr' => $email1,
                'subject' => 'Laporan Mingguan Proyek LIMS', 'email_date' => '2026-06-25 17:00:00',
                'size_bytes' => 120000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id1, 'folder' => 'inbox', 'uid' => 4, 'seq_num' => 4,
                'from_addr' => 'security@google.com', 'to_addr' => $email1,
                'subject' => 'Peringatan Keamanan: Login Baru Terdeteksi', 'email_date' => '2026-06-26 08:15:00',
                'size_bytes' => 8500, 'is_seen' => 0
            ],
            [
                'id_karyawan' => $id1, 'folder' => 'inbox', 'uid' => 5, 'seq_num' => 5,
                'from_addr' => 'client@mitra.com', 'to_addr' => $email1,
                'subject' => 'Re: Permintaan Dokumen Legalitas Kerja Sama', 'email_date' => '2026-06-26 14:00:00',
                'size_bytes' => 312000, 'is_seen' => 0
            ],

            // Employee 1 - Outbox
            [
                'id_karyawan' => $id1, 'folder' => 'outbox', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => $email1, 'to_addr' => 'client@mitra.com',
                'subject' => 'Permintaan Dokumen Legalitas Kerja Sama', 'email_date' => '2026-06-24 11:00:00',
                'size_bytes' => 25000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id1, 'folder' => 'outbox', 'uid' => 2, 'seq_num' => 2,
                'from_addr' => $email1, 'to_addr' => 'hrd@perusahaan.com',
                'subject' => 'Pengajuan Cuti Tahunan', 'email_date' => '2026-06-25 09:30:00',
                'size_bytes' => 12500, 'is_seen' => 1
            ],

            // Employee 1 - Spam
            [
                'id_karyawan' => $id1, 'folder' => 'spam', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'promo@menangmilyaran.com', 'to_addr' => $email1,
                'subject' => '🎉 Selamat! Anda Memenangkan Hadiah Utama 1 Miliar Rupiah!', 'email_date' => '2026-06-26 01:00:00',
                'size_bytes' => 50000, 'is_seen' => 0
            ],

            // Employee 1 - Trash
            [
                'id_karyawan' => $id1, 'folder' => 'trash', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'newsletter@medium.com', 'to_addr' => $email1,
                'subject' => 'Rekomendasi Bacaan Teknologi Hari Ini', 'email_date' => '2026-06-21 07:00:00',
                'size_bytes' => 32000, 'is_seen' => 1
            ],

            // Employee 2 - Inbox
            [
                'id_karyawan' => $id2, 'folder' => 'inbox', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'manager@divisi.com', 'to_addr' => $email2,
                'subject' => 'Undangan Evaluasi Kinerja Bulanan', 'email_date' => '2026-06-23 14:00:00',
                'size_bytes' => 18000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id2, 'folder' => 'inbox', 'uid' => 2, 'seq_num' => 2,
                'from_addr' => 'security@perusahaan.com', 'to_addr' => $email2,
                'subject' => 'Sosialisasi Protokol Keamanan Gedung Baru', 'email_date' => '2026-06-24 08:30:00',
                'size_bytes' => 22000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id2, 'folder' => 'inbox', 'uid' => 3, 'seq_num' => 3,
                'from_addr' => 'rekan@kerja.com', 'to_addr' => $email2,
                'subject' => 'Bahan Presentasi Meeting Besok Pagi', 'email_date' => '2026-06-26 15:00:00',
                'size_bytes' => 520000, 'is_seen' => 0
            ],

            // Employee 2 - Outbox
            [
                'id_karyawan' => $id2, 'folder' => 'outbox', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => $email2, 'to_addr' => 'manager@divisi.com',
                'subject' => 'Re: Undangan Evaluasi Kinerja Bulanan', 'email_date' => '2026-06-23 16:30:00',
                'size_bytes' => 14000, 'is_seen' => 1
            ],

            // Employee 3 (Inactive) - Inbox
            [
                'id_karyawan' => $id3, 'folder' => 'inbox', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'direktur@perusahaan.com', 'to_addr' => $email3,
                'subject' => 'Keputusan Pemberhentian Hubungan Kerja (PHK)', 'email_date' => '2026-06-10 10:00:00',
                'size_bytes' => 75000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id3, 'folder' => 'inbox', 'uid' => 2, 'seq_num' => 2,
                'from_addr' => 'hrd@perusahaan.com', 'to_addr' => $email3,
                'subject' => 'Formulir Pengurusan Uang Pesangon & Sisa Cuti', 'email_date' => '2026-06-12 11:30:00',
                'size_bytes' => 110000, 'is_seen' => 1
            ],
            [
                'id_karyawan' => $id3, 'folder' => 'inbox', 'uid' => 3, 'seq_num' => 3,
                'from_addr' => 'finance@perusahaan.com', 'to_addr' => $email3,
                'subject' => 'Slip Gaji Terakhir - Juni 2026', 'email_date' => '2026-06-25 15:00:00',
                'size_bytes' => 35000, 'is_seen' => 0
            ],
            [
                'id_karyawan' => $id3, 'folder' => 'inbox', 'uid' => 4, 'seq_num' => 4,
                'from_addr' => 'alumni@perusahaan.com', 'to_addr' => $email3,
                'subject' => 'Selamat Bergabung di Grup Alumni Karyawan', 'email_date' => '2026-06-26 09:00:00',
                'size_bytes' => 18000, 'is_seen' => 0
            ],

            // Employee 3 (Inactive) - Outbox
            [
                'id_karyawan' => $id3, 'folder' => 'outbox', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => $email3, 'to_addr' => 'hrd@perusahaan.com',
                'subject' => 'Tanda Terima Berkas Keputusan PHK', 'email_date' => '2026-06-11 09:00:00',
                'size_bytes' => 42000, 'is_seen' => 1
            ],

            // Employee 3 (Inactive) - Spam
            [
                'id_karyawan' => $id3, 'folder' => 'spam', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'casino@judionline.com', 'to_addr' => $email3,
                'subject' => '🎰 Dapatkan Bonus Deposit Pertama 200% Sekarang!', 'email_date' => '2026-06-26 03:00:00',
                'size_bytes' => 64000, 'is_seen' => 0
            ],

            // Employee 3 (Inactive) - Trash
            [
                'id_karyawan' => $id3, 'folder' => 'trash', 'uid' => 1, 'seq_num' => 1,
                'from_addr' => 'info@newsletter.com', 'to_addr' => $email3,
                'subject' => 'Katalog Belanja Akhir Pekan', 'email_date' => '2026-06-15 12:00:00',
                'size_bytes' => 28000, 'is_seen' => 1
            ]
        ];

        DB::table('mail_list_index')->insert($emails);

        $this->command->info("MailIndexSeeder: Berhasil men-seed data dengan ID karyawan asli: {$id1}, {$id2}, dan {$id3}!");
    }
}
