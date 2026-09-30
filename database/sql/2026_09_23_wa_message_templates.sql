-- WA message templates (recruitment candidate-facing)
-- Idempotent: CREATE TABLE IF NOT EXISTS + INSERT IGNORE by code
-- Jalankan di MySQL/MariaDB
--
-- Catatan: file SQL ini berisi seed ringan (3 variant/code).
-- Untuk full 50 variant (ref original + wording beda struktur):
--   php artisan db:seed --class=WaMessageTemplateSeeder
--
CREATE TABLE IF NOT EXISTS `wa_message_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `module` varchar(50) NOT NULL DEFAULT 'recruitment',
  `variables` json DEFAULT NULL,
  `description` text DEFAULT NULL,
  `pick_strategy` varchar(30) NOT NULL DEFAULT 'weighted_random',
  `cooldown_hours` smallint unsigned NOT NULL DEFAULT 72,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wa_message_templates_code_unique` (`code`),
  KEY `wa_msg_tpl_module_active_idx` (`module`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_message_template_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `body` text NOT NULL,
  `weight` smallint unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `use_count` int unsigned NOT NULL DEFAULT 0,
  `last_used_at` datetime DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wa_msg_var_tpl_active_idx` (`template_id`, `is_active`),
  CONSTRAINT `wa_msg_var_tpl_fk` FOREIGN KEY (`template_id`) REFERENCES `wa_message_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_message_template_sends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `phone` varchar(32) NOT NULL,
  `context_type` varchar(50) DEFAULT NULL,
  `context_id` varchar(64) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wa_msg_send_phone_idx` (`phone`),
  KEY `wa_msg_send_phone_tpl_sent_idx` (`phone`, `template_id`, `sent_at`),
  CONSTRAINT `wa_msg_send_tpl_fk` FOREIGN KEY (`template_id`) REFERENCES `wa_message_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wa_msg_send_var_fk` FOREIGN KEY (`variant_id`) REFERENCES `wa_message_template_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== SEED TEMPLATES (skip jika code sudah ada) ==========

INSERT IGNORE INTO `wa_message_templates`
(`code`, `name`, `module`, `variables`, `description`, `pick_strategy`, `cooldown_hours`, `is_active`, `created_at`, `updated_at`)
VALUES
('assessment_invitation', 'Undangan Assessment', 'assessment',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','link','label','URL assessment','required',true),
   JSON_OBJECT('key','masa_berlaku','label','Contoh: 2 x 24 jam','required',true)
 ),
 'GenerateMessageAtsWhatsapp::Assessment', 'weighted_random', 72, 1, NOW(), NOW()),

('interview_hrd_invite', 'Undangan Interview HRD', 'recruitment',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','bagian','label','Nama jabatan/bagian','required',true),
   JSON_OBJECT('key','hari','label','Hari','required',true),
   JSON_OBJECT('key','tanggal','label','Tanggal','required',true),
   JSON_OBJECT('key','jam','label','Jam','required',true),
   JSON_OBJECT('key','metode','label','Online / Offline','required',true),
   JSON_OBJECT('key','detail_lokasi','label','Link GMeet ATAU alamat','required',true),
   JSON_OBJECT('key','kode','label','Kode unik offline (boleh kosong)','required',false)
 ),
 'PassedCandidateSelection (ATS+legacy)', 'weighted_random', 72, 1, NOW(), NOW()),

('interview_hrd_reschedule', 'Reschedule Interview HRD', 'recruitment',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','bagian','label','Nama jabatan/bagian','required',true),
   JSON_OBJECT('key','hari','label','Hari','required',true),
   JSON_OBJECT('key','tanggal','label','Tanggal','required',true),
   JSON_OBJECT('key','jam','label','Jam','required',true),
   JSON_OBJECT('key','metode','label','Online / Offline','required',true),
   JSON_OBJECT('key','detail_lokasi','label','Link GMeet ATAU alamat','required',true),
   JSON_OBJECT('key','kode','label','Kode unik offline (boleh kosong)','required',false)
 ),
 'GenerateMessageWhatsapp::RescheduleHRD', 'weighted_random', 72, 1, NOW(), NOW()),

('interview_user_invite', 'Undangan User Interview', 'recruitment',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','waktu','label','Waktu interview','required',true),
   JSON_OBJECT('key','tipe','label','Online / Offline','required',true),
   JSON_OBJECT('key','detail_lokasi','label','Link GMeet atau ruangan','required',true),
   JSON_OBJECT('key','instruksi','label','Instruksi hadir','required',false)
 ),
 'UserInterviewScheduleCandidate / PassedHRD', 'weighted_random', 72, 1, NOW(), NOW()),

('interview_user_reschedule', 'Reschedule User Interview', 'recruitment',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','waktu','label','Waktu interview','required',true),
   JSON_OBJECT('key','tipe','label','Online / Offline','required',true),
   JSON_OBJECT('key','detail_lokasi','label','Link GMeet atau ruangan','required',true),
   JSON_OBJECT('key','instruksi','label','Instruksi hadir','required',false)
 ),
 'GenerateMessageWhatsapp::RescheduleUser', 'weighted_random', 72, 1, NOW(), NOW()),

('complete_profile', 'Lengkapi Data Diri', 'recruitment',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','link','label','URL complete profile','required',true)
 ),
 'CompleteProfileCandidate', 'weighted_random', 72, 1, NOW(), NOW()),

('candidate_rejection', 'Penolakan Kandidat', 'recruitment',
 JSON_ARRAY(
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true)
 ),
 'RejectedCandidateSelection / RejectedHRD', 'weighted_random', 72, 1, NOW(), NOW()),

('salary_offering_letter', 'Salary Offering Letter', 'hrd',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','gaji','label','Penawaran gaji (Rp)','required',true),
   JSON_OBJECT('key','email','label','Email tujuan PDF','required',false)
 ),
 'SalaryOfferingLetter', 'weighted_random', 72, 1, NOW(), NOW()),

('hiring_letter', 'Hiring Letter', 'hrd',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','gaji','label','Gaji pokok','required',true),
   JSON_OBJECT('key','tanggal_mulai','label','Tanggal mulai kerja','required',true),
   JSON_OBJECT('key','email','label','Email tujuan PDF','required',false)
 ),
 'HiringLetter', 'weighted_random', 72, 1, NOW(), NOW()),

('onboarding_start_work', 'Pemberitahuan Mulai Kerja', 'hrd',
 JSON_ARRAY(
   JSON_OBJECT('key','sapaan','label','Sapaan waktu','required',true),
   JSON_OBJECT('key','nama','label','Nama kandidat','required',true),
   JSON_OBJECT('key','posisi','label','Posisi dilamar','required',true),
   JSON_OBJECT('key','hari','label','Hari hadir','required',true),
   JSON_OBJECT('key','tanggal','label','Tanggal mulai','required',true),
   JSON_OBJECT('key','jam','label','Jam hadir','required',true),
   JSON_OBJECT('key','alamat','label','Alamat perusahaan','required',true)
 ),
 'GenerateMessageWhatsapp::PassedOS', 'weighted_random', 72, 1, NOW(), NOW());

-- ========== SEED VARIANTS (hanya jika template belum punya variant) ==========

INSERT INTO `wa_message_template_variants`
(`template_id`, `label`, `body`, `weight`, `is_active`, `use_count`, `created_at`, `updated_at`)
SELECT t.id, v.label, v.body, 1, 1, 0, NOW(), NOW()
FROM `wa_message_templates` t
JOIN (
  SELECT 'assessment_invitation' AS code, 'v01 formal' AS label,
    CONCAT('{{sapaan}}, Yth. Bapak/Ibu *{{nama}}*\n\nTerima kasih telah melamar posisi *{{posisi}}* di *PT Inti Surya Laboratorium*.\n\nSilakan lanjutkan *Career Assessment*:\n{{link}}\n\nBerlaku *{{masa_berlaku}}*. Kerjakan hingga selesai.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*') AS body
  UNION ALL SELECT 'assessment_invitation', 'v02 ringkas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nUntuk seleksi *{{posisi}}*, mohon selesaikan assessment di:\n{{link}}\n\nMasa berlaku *{{masa_berlaku}}*.\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'assessment_invitation', 'v03 deadline',
    CONCAT('{{sapaan}}, *{{nama}}*\n\nUndangan *assessment online* posisi *{{posisi}}*.\n\n{{link}}\n\nSegera dikerjakan — berlaku *{{masa_berlaku}}*.\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'interview_hrd_invite', 'v01 klasik',
    CONCAT('{{sapaan}} {{nama}}\n\nTerima kasih atas lamaran posisi {{posisi}}\n*Bagian : {{bagian}}*\n{{kode}}\nKami mengundang Anda *interview HRD*:\n\n*Hari : {{hari}} / {{tanggal}}*\n*Jam : {{jam}}*\n*Sistem : {{metode}}*\n{{detail_lokasi}}\n\nKonfirmasi: *Bersedia / Tidak Bersedia_Nama Lengkap*\n\nTerima kasih.')
  UNION ALL SELECT 'interview_hrd_invite', 'v02 jadwal',
    CONCAT('{{sapaan}}, Yth. *{{nama}}*\n\nAnda lolos tahap berikutnya untuk *{{posisi}}* ({{bagian}}).\n{{kode}}\n*Interview HRD*\n- {{hari}}, {{tanggal}}\n- Pukul {{jam}} WIB\n- {{metode}}\n{{detail_lokasi}}\n\nMohon konfirmasi kehadiran di chat ini.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'interview_hrd_invite', 'v03 singkat',
    CONCAT('{{sapaan}} *{{nama}}*,\n\n*Interview HRD* — {{posisi}}.\n{{hari}}, {{tanggal}} | {{jam}} WIB | {{metode}}\n{{detail_lokasi}}\n{{kode}}\nBalas *Bersedia / Tidak Bersedia_Nama Lengkap*.\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'interview_hrd_reschedule', 'v01 formal',
    CONCAT('{{sapaan}} {{nama}}\n\nTerdapat *perubahan jadwal interview HRD* untuk posisi {{posisi}} ({{bagian}}).\n\nJadwal baru:\nHari : {{hari}} / {{tanggal}}\nJam : {{jam}}\nSistem : {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKonfirmasi: Bersedia / Tidak Bersedia_Nama Lengkap\n\nTerima kasih.')
  UNION ALL SELECT 'interview_hrd_reschedule', 'v02 tegas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nJadwal *Interview HRD* Anda diubah.\n\nPosisi: *{{posisi}}*\n{{hari}}, {{tanggal}} | {{jam}} WIB | {{metode}}\n{{detail_lokasi}}\n\nMohon konfirmasi ulang kehadiran.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'interview_hrd_reschedule', 'v03 ringkas',
    CONCAT('{{sapaan}} {{nama}},\n\nReschedule Interview HRD ({{posisi}}):\n{{hari}}/{{tanggal}} {{jam}} — {{metode}}\n{{detail_lokasi}}\n\nBalas konfirmasi ke chat ini.\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'interview_user_invite', 'v01 formal',
    CONCAT('{{sapaan}}, Yth. Bapak/Ibu *{{nama}}*\n\nBerikut jadwal *User Interview* posisi *{{posisi}}*:\n\n*Waktu:* {{waktu}}\n*Tipe:* {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'interview_user_invite', 'v02 jelas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nAnda diundang *User Interview* untuk *{{posisi}}*.\n\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'interview_user_invite', 'v03 singkat',
    CONCAT('{{sapaan}} {{nama}},\n\nUser Interview — {{posisi}}\n{{waktu}} | {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'interview_user_reschedule', 'v01 formal',
    CONCAT('{{sapaan}} {{nama}}\n\nTerdapat *perubahan jadwal User Interview* untuk posisi *{{posisi}}*.\n\nJadwal baru: {{waktu}}\nTipe: {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nMohon konfirmasi ulang.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'interview_user_reschedule', 'v02 tegas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nReschedule *User Interview* ({{posisi}}).\n{{waktu}} | {{tipe}}\n{{detail_lokasi}}\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'interview_user_reschedule', 'v03 ringkas',
    CONCAT('{{sapaan}} {{nama}},\n\nJadwal User Interview diubah:\n{{waktu}} — {{tipe}}\n{{detail_lokasi}}\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'complete_profile', 'v01 formal',
    CONCAT('{{sapaan}}, Yth. Bapak/Ibu *{{nama}}*\n\nSehubungan dengan rekrutmen *{{posisi}}*, mohon lengkapi *Data Diri & Berkas* melalui:\n\n{{link}}\n\nTanpa kelengkapan data, proses tidak dapat dilanjutkan.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'complete_profile', 'v02 ringkas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nMohon lengkapi data diri untuk posisi *{{posisi}}*:\n{{link}}\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'complete_profile', 'v03 checklist',
    CONCAT('{{sapaan}} {{nama}},\n\nLengkapi data rekrutmen *{{posisi}}* di tautan berikut:\n{{link}}\n\nSiapkan: KTP, KK, NPWP, ijazah, transkrip, CV.\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'candidate_rejection', 'v01 formal',
    CONCAT('Yth. Bapak/Ibu *{{nama}}*,\n\nTerima kasih atas partisipasi Anda pada proses rekrutmen *{{posisi}}*.\n\nSetelah evaluasi, kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya.\n\nKami menghargai minat Anda dan mendoakan yang terbaik.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'candidate_rejection', 'v02 hangat',
    CONCAT('Halo *{{nama}}*,\n\nTerima kasih sudah mengikuti seleksi *{{posisi}}*.\n\nSaat ini kami belum bisa melanjutkan proses Anda. Keputusan terkait kebutuhan posisi saat ini.\n\nSemoga kesempatan terbaik segera datang.\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'candidate_rejection', 'v03 ringkas',
    CONCAT('*{{nama}}*,\n\nTerima kasih atas lamaran *{{posisi}}*. Mohon maaf, proses belum dapat kami lanjutkan.\n\nSukses selalu.\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'salary_offering_letter', 'v01 formal',
    CONCAT('{{sapaan}}, Yth. Bapak/Ibu *{{nama}}*\n\n*Surat Penawaran Gaji* untuk posisi *{{posisi}}* telah dikirim ke email *{{email}}*.\n\n- Posisi: {{posisi}}\n- Penawaran: {{gaji}}\n\nMohon cek email (termasuk spam). Ini belum keputusan penerimaan resmi.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'salary_offering_letter', 'v02 ringkas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nPenawaran gaji *{{posisi}}* ({{gaji}}) sudah dikirim ke {{email}}.\n\nSilakan tinjau dokumen PDF-nya.\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'salary_offering_letter', 'v03 tegas',
    CONCAT('{{sapaan}} {{nama}},\n\nOffering Letter *{{posisi}}* — {{gaji}} — telah kami emailkan ke {{email}}.\n\nMohon dibaca dan ditindaklanjuti.\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'hiring_letter', 'v01 formal',
    CONCAT('{{sapaan}}, Yth. Bapak/Ibu *{{nama}}*\n\nSelamat! Anda *diterima* di *PT Inti Surya Laboratorium* posisi *{{posisi}}*.\n\n- Gaji Pokok: {{gaji}}\n- Mulai Kerja: {{tanggal_mulai}}\n\nHiring Letter dikirim ke *{{email}}*. Konfirmasi paling lambat *1 x 24 jam*.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'hiring_letter', 'v02 senang',
    CONCAT('{{sapaan}} *{{nama}}*\n\nAnda resmi diterima sebagai *{{posisi}}*.\nMulai: {{tanggal_mulai}} | Gaji: {{gaji}}\n\nCek email {{email}} untuk Hiring Letter.\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'hiring_letter', 'v03 ringkas',
    CONCAT('{{sapaan}} {{nama}},\n\nKeputusan: *DITERIMA* — {{posisi}}.\n{{tanggal_mulai}} | {{gaji}}\nPDF di email {{email}}.\n\nTerima kasih.\n*Recruitment Team*\n*PT Inti Surya Laboratorium*')

  UNION ALL SELECT 'onboarding_start_work', 'v01 formal',
    CONCAT('{{sapaan}} {{nama}}\n\nAnda diterima bergabung posisi *{{posisi}}*, terhitung {{tanggal}}.\n\nWajib hadir:\nHari : {{hari}} / {{tanggal}}\nJam : {{jam}}\nAlamat : {{alamat}}\n\nPertanyaan: WA HRD 0811-1254-0719.\n\nDemikian, terima kasih.')
  UNION ALL SELECT 'onboarding_start_work', 'v02 jelas',
    CONCAT('{{sapaan}} *{{nama}}*,\n\nSelamat bergabung sebagai *{{posisi}}*.\n\n{{hari}}, {{tanggal}}\n{{jam}}\n{{alamat}}\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*')
  UNION ALL SELECT 'onboarding_start_work', 'v03 ringkas',
    CONCAT('{{sapaan}} {{nama}},\n\nMulai kerja *{{posisi}}*: {{hari}}/{{tanggal}} pukul {{jam}}.\nLokasi: {{alamat}}\n\nHormat kami,\n*HRD Recruitment — PT Inti Surya Laboratorium*')
) v ON v.code = t.code
WHERE NOT EXISTS (
  SELECT 1 FROM `wa_message_template_variants` x WHERE x.template_id = t.id
);
