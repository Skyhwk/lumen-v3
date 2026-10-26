<?php

namespace Database\Seeders\Data;

/**
 * 50 variant WA per template - tone: corporate & humble.
 * Sopan, rendah hati, profesional. Tidak kaku baku, tidak terlalu santai.
 *
 * Placeholder konsisten per code (lihat WaMessageTemplateSeeder).
 */
class WaMessageTemplateVariants
{
    public static function all()
    {
        return [
            'assessment_invitation' => self::assessment(),
            'interview_hrd_invite' => self::interviewHrdInvite(),
            'interview_hrd_reschedule' => self::interviewHrdReschedule(),
            'interview_user_invite' => self::interviewUserInvite(),
            'interview_user_reschedule' => self::interviewUserReschedule(),
            'complete_profile' => self::completeProfile(),
            'candidate_rejection' => self::candidateRejection(),
            'salary_offering_letter' => self::salaryOffering(),
            'hiring_letter' => self::hiringLetter(),
            'onboarding_start_work' => self::onboarding(),
        ];
    }

    private static function pack(array $items)
    {
        $out = [];
        $i = 1;
        foreach ($items as $item) {
            $out[] = [
                'label' => isset($item['label']) ? $item['label'] : sprintf('v%02d', $i),
                'body' => $item['body'],
            ];
            $i++;
        }

        return $out;
    }

    private static function combine(array $opens, array $mids, array $closes, $target = 50)
    {
        $bodies = [];
        $oi = 0;
        $mi = 0;
        $ci = 0;
        $nOpen = count($opens);
        $nMid = count($mids);
        $nClose = count($closes);

        while (count($bodies) < $target) {
            $bodies[] = $opens[$oi % $nOpen] . $mids[$mi % $nMid] . $closes[$ci % $nClose];
            $oi++;
            $mi++;
            if ($oi % $nOpen === 0) {
                $ci++;
            }
            if (count($bodies) % 3 === 0) {
                $mi += 2;
            }
            if (count($bodies) % 7 === 0) {
                $ci += 1;
            }
        }

        $unique = [];
        $seen = [];
        foreach ($bodies as $b) {
            $h = md5($b);
            if (isset($seen[$h])) {
                continue;
            }
            $seen[$h] = true;
            $unique[] = $b;
        }

        $extra = 0;
        while (count($unique) < $target) {
            $b = $opens[$extra % $nOpen]
                . $mids[($extra * 3) % $nMid]
                . "\n"
                . $closes[($extra * 5) % $nClose];
            $h = md5($b);
            if (!isset($seen[$h])) {
                $seen[$h] = true;
                $unique[] = $b;
            }
            $extra++;
            if ($extra > 500) {
                break;
            }
        }

        return array_slice($unique, 0, $target);
    }

    private static function fromParts(array $refs, array $opens, array $mids, array $closes)
    {
        $need = 50 - count($refs);
        if ($need < 0) {
            $need = 0;
        }
        $generated = self::combine($opens, $mids, $closes, $need);
        $items = $refs;
        $n = 1;
        foreach ($generated as $body) {
            $items[] = [
                'label' => sprintf('v%02d', $n),
                'body' => $body,
            ];
            $n++;
        }

        return self::pack(array_slice($items, 0, 50));
    }

    /** Footer corporate & humble - beberapa variasi, tetap profesional */
    private static function closesCorporate()
    {
        return [
            "Atas perhatian dan kerja sama {{saudara}}, kami ucapkan terima kasih.\n\nSalam hormat,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            "Kami menghargai waktu yang {{saudara}} luangkan.\n\nHormat kami,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            "Terima kasih atas perhatiannya.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            "Semoga informasi ini bermanfaat. Kami siap membantu bila diperlukan.\n\nHormat kami,\n*Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            "Atas kerja sama yang baik, kami sampaikan terima kasih.\n\nSalam hormat,\n*HRD Recruitment*\n*PT Inti Surya Laboratorium*",
            "Demikian yang dapat kami sampaikan. Terima kasih.\n\nSalam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            "Kami tunggu kabar baik dari {{saudara}}.\n\nHormat kami,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
        ];
    }

    private static function opensCorporate()
    {
        return [
            "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n",
            "{{sapaan}}, {{saudara}} *{{nama}}*.\n\n",
            "Yth. {{saudara}} *{{nama}}*, {{sapaan}}.\n\n",
            "{{sapaan}}. Dengan hormat, {{saudara}} *{{nama}}*,\n\n",
            "Yth. {{saudara}} *{{nama}}*,\n\n{{sapaan}}.\n\n",
            "{{sapaan}}, kepada {{saudara}} *{{nama}}*,\n\n",
            "{{saudara}} *{{nama}}*, {{sapaan}}.\n\n",
            "{{sapaan}}. Yth. {{saudara}} *{{nama}}*,\n\n",
            "Dengan hormat,\nYth. {{saudara}} *{{nama}}*,\n\n",
            "{{sapaan}} {{saudara}} *{{nama}}*,\n\n",
        ];
    }

    // ------------------------------------------------------------------
    private static function assessment()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n"
                    . "Terima kasih telah mengirimkan lamaran untuk posisi *{{posisi}}* di *PT Inti Surya Laboratorium*.\n\n"
                    . "Lamaran Anda telah kami terima. Silakan lanjutkan ke tahap *Career Assessment* melalui tautan resmi berikut:\n\n"
                    . "*Mulai Career Assessment:*\n{{link}}\n\n"
                    . "Link assessment berlaku selama *{{masa_berlaku}}*. Pastikan koneksi internet stabil dan kerjakan assessment hingga selesai.\n\n"
                    . "Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
        ];

        $mids = [
            "Terima kasih atas minat {{saudara}} untuk bergabung pada posisi *{{posisi}}*.\n\nDengan rendah hati, kami mengundang {{saudara}} untuk menyelesaikan *Career Assessment* melalui tautan berikut:\n\n{{link}}\n\nTautan tersedia selama *{{masa_berlaku}}*. Mohon dikerjakan hingga selesai dengan kondisi yang mendukung.\n\n",
            "Lamaran {{saudara}} untuk *{{posisi}}* telah kami terima dengan baik.\n\nSebagai tahap berikutnya, kami mohon kesediaan {{saudara}} mengerjakan assessment pada:\n{{link}}\n\nMasa berlaku akses: *{{masa_berlaku}}*.\n\n",
            "Sehubungan dengan proses seleksi *{{posisi}}*, kami menyampaikan undangan *Career Assessment*.\n\n*Tautan resmi:*\n{{link}}\n\nBerlaku *{{masa_berlaku}}*. Kami menghargai kerja sama {{saudara}} untuk menyelesaikannya secara lengkap.\n\n",
            "Kami mengapresiasi partisipasi {{saudara}} pada rekrutmen *{{posisi}}*.\n\nMohon berkenan melanjutkan ke assessment melalui:\n{{link}}\n\nAkses dibuka selama *{{masa_berlaku}}*.\n\n",
            "Dengan hormat kami sampaikan bahwa tahap assessment untuk *{{posisi}}* telah tersedia.\n\nSilakan mengakses:\n{{link}}\n\nPeriode akses *{{masa_berlaku}}*. Mohon dikerjakan dengan seksama.\n\n",
            "Untuk mendukung kelancaran seleksi *{{posisi}}*, kami memohon bantuan {{saudara}} menyelesaikan Career Assessment:\n\n{{link}}\n\nBerlaku *{{masa_berlaku}}*. Terima kasih atas kesediaannya.\n\n",
            "Berikut kami informasikan undangan assessment posisi *{{posisi}}*.\n\n1. Buka tautan: {{link}}\n2. Kerjakan hingga selesai\n3. Pastikan koneksi internet memadai\n\nBatas akses: *{{masa_berlaku}}*.\n\n",
            "Kami berterima kasih atas lamaran *{{posisi}}* yang telah {{saudara}} kirimkan.\n\nTahap selanjutnya adalah *Career Assessment*:\n{{link}}\n\nMohon diselesaikan sebelum masa berlaku (*{{masa_berlaku}}*) berakhir.\n\n",
            "Izin menyampaikan undangan assessment untuk posisi *{{posisi}}*.\n\nTautan: {{link}}\nWaktu akses: *{{masa_berlaku}}*\n\nKami sangat menghargai waktu yang {{saudara}} sediakan.\n\n",
            "Proses rekrutmen *{{posisi}}* memasuki tahap assessment. Kami mohon kesediaan {{saudara}} untuk mengerjakannya pada tautan resmi berikut:\n\n{{link}}\n\nBerlaku *{{masa_berlaku}}*.\n\n",
            "Dengan segala hormat, kami mengundang {{saudara}} menyelesaikan penilaian karier (*Career Assessment*) terkait *{{posisi}}*.\n\n{{link}}\n\nAkses tersedia *{{masa_berlaku}}*.\n\n",
            "Mohon perkenan {{saudara}} untuk melanjutkan seleksi *{{posisi}}* melalui assessment online:\n{{link}}\n\nMasa berlaku *{{masa_berlaku}}*. Semoga pengerjaannya berjalan lancar.\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function interviewHrdInvite()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n"
                    . "Sehubungan dengan proses seleksi posisi *{{posisi}}* di PT Inti Surya Laboratorium, "
                    . "kami mengundang Anda untuk mengikuti tahapan *Interview HRD* pada:\n\n"
                    . "*Hari / Tanggal:* {{hari}}, {{tanggal}}\n"
                    . "*Waktu:* {{jam}} WIB\n"
                    . "*Metode:* {{metode}}\n"
                    . "{{detail_lokasi}}\n"
                    . "{{kode}}\n"
                    . "Mohon mengonfirmasi ketersediaan Anda dengan membalas pesan ini.\n\n"
                    . "Atas perhatian Anda, kami ucapkan terima kasih.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
            [
                'label' => 'ref_original_legacy',
                'body' => "{{sapaan}} {{nama}}\n\n"
                    . "Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi {{posisi}}\n"
                    . "*Bagian dilamar : {{bagian}}*\n"
                    . "{{kode}}\n"
                    . "Bersamaan dengan ini, kami informasikan bahwa Anda lolos ke tahap selanjutnya "
                    . "dan kami mengundang Anda untuk melaksanakan interview:\n\n"
                    . "*Hari : {{hari}} / {{tanggal}}*\n"
                    . "*Jam : {{jam}}*\n"
                    . "*Sistem Interview : {{metode}}*\n"
                    . "{{detail_lokasi}}\n\n"
                    . "Anda wajib konfirmasi kehadiran dengan membalas:\n"
                    . "*Bersedia / Tidak Bersedia_Nama Lengkap*\n\n"
                    . "Demikian pemberitahuan ini kami sampaikan, terima kasih.",
            ],
        ];

        $mids = [
            "Terima kasih atas partisipasi {{saudara}} pada seleksi *{{posisi}}* ({{bagian}}).\n\nDengan hormat, kami mengundang {{saudara}} untuk *Interview HRD* pada:\n\n*Hari / Tanggal:* {{hari}}, {{tanggal}}\n*Waktu:* {{jam}} WIB\n*Metode:* {{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon berkenan mengonfirmasi ketersediaan dengan membalas pesan ini (*Bersedia / Tidak Bersedia* disertai nama lengkap).\n\n",
            "Kami menghargai minat {{saudara}} pada posisi *{{posisi}}*.\n\nBerikut jadwal *Interview HRD* yang kami siapkan:\n- {{hari}}, {{tanggal}}\n- Pukul {{jam}} WIB\n- {{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon konfirmasi kehadiran melalui balasan pada chat ini.\n\n",
            "Sehubungan dengan proses rekrutmen *{{posisi}}*, kami memohon kesediaan {{saudara}} hadir pada *Interview HRD*.\n\nJadwal: {{hari}}, {{tanggal}} pukul {{jam}} WIB ({{metode}}).\n{{detail_lokasi}}\n{{kode}}\nKonfirmasi sangat membantu kelancaran penjadwalan kami.\n\n",
            "Dengan rendah hati kami sampaikan undangan Interview HRD untuk *{{posisi}}* / {{bagian}}.\n\n{{hari}}/{{tanggal}} | {{jam}} WIB | {{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon balas: *Bersedia* atau *Tidak Bersedia_Nama Lengkap*.\n\n",
            "Izin mengundang {{saudara}} ke tahap *Interview HRD* posisi *{{posisi}}*.\n\n*Hari:* {{hari}}, {{tanggal}}\n*Jam:* {{jam}} WIB\n*Sistem:* {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKami tunggu konfirmasi dengan penuh apresiasi.\n\n",
            "Berikut kami informasikan undangan wawancara HRD terkait *{{posisi}}*.\n\nTanggal {{hari}}, {{tanggal}}\nWaktu {{jam}} WIB\nMetode {{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon konfirmasi ketersediaan {{saudara}}.\n\n",
            "Kami mengapresiasi lamaran {{saudara}}. Tahap berikutnya adalah *Interview HRD* untuk *{{posisi}}*.\n\n{{hari}}, {{tanggal}} - {{jam}} WIB - {{metode}}\n{{detail_lokasi}}\n{{kode}}\nSilakan konfirmasi melalui balasan pesan ini.\n\n",
            "Dengan segala hormat, kami menjadwalkan *Interview HRD* posisi *{{posisi}}* pada {{hari}}, {{tanggal}} pukul {{jam}} WIB secara {{metode}}.\n{{detail_lokasi}}\n{{kode}}\nMohon kesediaan untuk mengonfirmasi kehadiran.\n\n",
            "Undangan Interview HRD - *{{posisi}}* ({{bagian}}).\n\nDetail jadwal:\n{{hari}}, {{tanggal}}\n{{jam}} WIB\n{{metode}}\n{{detail_lokasi}}\n{{kode}}\nFormat konfirmasi: Bersedia / Tidak Bersedia_Nama Lengkap.\n\n",
            "Kami berterima kasih atas waktu {{saudara}} sejauh ini. Berikut undangan *Interview HRD* untuk *{{posisi}}*:\n{{hari}}, {{tanggal}} pukul {{jam}} ({{metode}}).\n{{detail_lokasi}}\n{{kode}}\nMohon dibalas konfirmasinya.\n\n",
            "Mohon perkenan {{saudara}} untuk mengikuti Interview HRD posisi *{{posisi}}*.\n\nJadwal yang kami ajukan: {{hari}}, {{tanggal}} - {{jam}} WIB - {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKonfirmasi ke nomor ini sangat kami hargai.\n\n",
            "Informasi undangan wawancara:\nPosisi *{{posisi}}* | Bagian {{bagian}}\n{{hari}}, {{tanggal}} pukul {{jam}}\nMetode {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKami mohon konfirmasi dengan ramah dan tepat waktu.\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function interviewHrdReschedule()
    {
        $refs = [
            [
                'label' => 'ref_original_legacy',
                'body' => "{{sapaan}} {{nama}}\n\n"
                    . "Terima kasih atas lamaran kerja pada posisi {{posisi}}\n\n"
                    . "Bagian dilamar : {{bagian}}\n\n"
                    . "Bersamaan dengan ini, kami informasikan bahwa terdapat *perubahan jadwal interview HRD*, "
                    . "dengan rincian sebagai berikut:\n\n"
                    . "Hari : {{hari}} / {{tanggal}}\n"
                    . "Jam : {{jam}}\n"
                    . "Sistem Interview : {{metode}}\n"
                    . "{{detail_lokasi}}\n"
                    . "{{kode}}\n"
                    . "Mohon konfirmasi kehadiran dengan membalas:\n"
                    . "Bersedia / Tidak Bersedia_Nama Lengkap\n\n"
                    . "Demikian pemberitahuan ini kami sampaikan, terima kasih.",
            ],
        ];

        $mids = [
            "Mohon maaf sebelumnya. Terdapat *penyesuaian jadwal Interview HRD* untuk posisi *{{posisi}}*.\n\nJadwal terbaru:\n{{hari}}, {{tanggal}} | {{jam}} WIB | {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKami mohon kesediaan {{saudara}} untuk mengonfirmasi ulang.\n\n",
            "Dengan hormat kami informasikan perubahan jadwal Interview HRD ({{posisi}} / {{bagian}}).\n\n{{hari}}/{{tanggal}} pukul {{jam}} - {{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon balas *Bersedia / Tidak Bersedia_Nama Lengkap*.\n\n",
            "Izin menyampaikan permintaan maaf atas perubahan jadwal. Interview HRD *{{posisi}}* kami sesuaikan menjadi:\n{{hari}}, {{tanggal}} pukul {{jam}} WIB ({{metode}})\n{{detail_lokasi}}\n{{kode}}\nApakah jadwal ini masih memungkinkan bagi {{saudara}}?\n\n",
            "Sehubungan dengan penyesuaian internal, jadwal Interview HRD diganti ke:\n{{hari}}, {{tanggal}} - {{jam}} WIB - {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKami menghargai konfirmasi ulang dari {{saudara}}.\n\n",
            "Kami mohon pengertian atas perubahan jadwal *Interview HRD* posisi *{{posisi}}*.\nWaktu baru: {{hari}}, {{tanggal}} jam {{jam}} ({{metode}}).\n{{detail_lokasi}}\n{{kode}}\nKonfirmasi kehadiran tetap kami perlukan.\n\n",
            "Pemberitahuan dengan hormat: slot Interview HRD dipindahkan.\n{{posisi}} - {{bagian}}\n{{hari}}/{{tanggal}} {{jam}} WIB\n{{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon dibalas konfirmasinya.\n\n",
            "Terima kasih atas pengertian {{saudara}}. Berikut jadwal pengganti Interview HRD *{{posisi}}*:\n{{hari}}, {{tanggal}} pukul {{jam}} secara {{metode}}.\n{{detail_lokasi}}\n{{kode}}\n\n",
            "Terdapat penyesuaian jadwal wawancara. Detail terbaru:\nHari/tanggal: {{hari}}, {{tanggal}}\nJam: {{jam}}\nMetode: {{metode}}\n{{detail_lokasi}}\n{{kode}}\nMohon konfirmasi ulang dengan baik.\n\n",
            "Dengan rendah hati kami sampaikan bahwa jadwal Interview HRD *{{posisi}}* menjadi {{hari}}, {{tanggal}} pukul {{jam}} WIB ({{metode}}).\n{{detail_lokasi}}\n{{kode}}\nSilakan konfirmasi melalui pesan ini.\n\n",
            "Mohon dicatat jadwal pengganti Interview HRD:\n{{hari}}, {{tanggal}}\n{{jam}} WIB\n{{metode}}\n{{detail_lokasi}}\n{{kode}}\nPosisi: *{{posisi}}*. Kami mohon maaf atas perubahan ini.\n\n",
            "Kami memohon kesediaan {{saudara}} menyesuaikan ke jadwal baru Interview HRD *{{posisi}}*.\n{{hari}}/{{tanggal}} jam {{jam}} - {{metode}}\n{{detail_lokasi}}\n{{kode}}\n\n",
            "Reschedule Interview HRD - {{posisi}} ({{bagian}}).\n{{hari}}, {{tanggal}} | {{jam}} | {{metode}}\n{{detail_lokasi}}\n{{kode}}\nKonfirmasi: Bersedia / Tidak Bersedia_Nama Lengkap.\nMaaf atas ketidaknyamanannya.\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function interviewUserInvite()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n"
                    . "Berikut kami sampaikan informasi jadwal *User Interview* Anda untuk posisi *{{posisi}}* "
                    . "di *PT Inti Surya Laboratorium*:\n\n"
                    . "*Waktu Interview*: {{waktu}}\n"
                    . "*Tipe Interview*: {{tipe}}\n"
                    . "{{detail_lokasi}}\n\n"
                    . "{{instruksi}}\n\n"
                    . "Atas perhatian dan konfirmasi Anda, kami ucapkan terima kasih.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
        ];

        $mids = [
            "Terima kasih atas perjalanan seleksi {{saudara}} sejauh ini. Dengan hormat, kami mengundang *User Interview* untuk posisi *{{posisi}}*.\n\n*Waktu:* {{waktu}}\n*Tipe:* {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nMohon konfirmasi ketersediaan. Apabila berhalangan, kami mohon kabar secepatnya.\n\n",
            "Kami menghargai partisipasi {{saudara}}. Berikut jadwal *User Interview* posisi *{{posisi}}*:\n\n{{waktu}} | {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Sehubungan dengan tahap lanjutan *{{posisi}}*, kami memohon kesediaan {{saudara}} hadir pada User Interview.\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Dengan rendah hati kami sampaikan undangan User Interview (*{{posisi}}*).\n\nDetail:\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nKami tunggu konfirmasi {{saudara}}.\n\n",
            "Berikut informasi jadwal wawancara user untuk *{{posisi}}*:\n*Waktu Interview:* {{waktu}}\n*Tipe:* {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Izin mengundang {{saudara}} ke sesi User Interview terkait *{{posisi}}*.\nJadwal: {{waktu}}\nMode: {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Tahap berikutnya adalah User Interview *{{posisi}}*. Kami mohon kehadiran {{saudara}} sesuai detail berikut:\n{{waktu}} - {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Kami mengapresiasi kerja sama {{saudara}}. Undangan User Interview:\nPosisi {{posisi}}\nWaktu {{waktu}}\nTipe {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Dengan segala hormat, slot User Interview *{{posisi}}* telah kami siapkan.\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nKonfirmasi {{saudara}} sangat membantu.\n\n",
            "Mohon perkenan {{saudara}} untuk mengikuti User Interview posisi *{{posisi}}* pada {{waktu}} secara {{tipe}}.\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Persiapan User Interview *{{posisi}}*:\n1. Waktu: {{waktu}}\n2. Mode: {{tipe}}\n3. Lokasi/tautan:\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Menyampaikan undangan User Interview dengan hormat.\n*{{posisi}}* | {{waktu}} | {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function interviewUserReschedule()
    {
        $refs = [
            [
                'label' => 'ref_original_legacy',
                'body' => "{{sapaan}} {{nama}}\n\n"
                    . "Terima kasih atas lamaran kerja pada posisi {{posisi}}.\n\n"
                    . "Kami informasikan terdapat *perubahan jadwal User Interview*, dengan rincian:\n\n"
                    . "Waktu : {{waktu}}\n"
                    . "Sistem : {{tipe}}\n"
                    . "{{detail_lokasi}}\n\n"
                    . "{{instruksi}}\n\n"
                    . "Mohon konfirmasi kehadiran dengan membalas pesan ini.\n\n"
                    . "Demikian, terima kasih.",
            ],
        ];

        $mids = [
            "Mohon maaf, jadwal *User Interview* untuk *{{posisi}}* perlu kami sesuaikan.\n\nJadwal baru: {{waktu}}\nTipe: {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nKami mohon konfirmasi ulang dan menyampaikan terima kasih atas pengertiannya.\n\n",
            "Dengan hormat kami informasikan *reschedule* User Interview - {{posisi}}.\n{{waktu}} | {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nMohon balas pesan ini untuk konfirmasi.\n\n",
            "Izin menyampaikan perubahan jadwal User Interview.\n\nPosisi: *{{posisi}}*\nWaktu pengganti: {{waktu}} ({{tipe}})\n{{detail_lokasi}}\n\n{{instruksi}}\n\nApakah {{saudara}} masih berkenan hadir?\n\n",
            "Terdapat penyesuaian jadwal User Interview *{{posisi}}*.\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nKonfirmasi kehadiran sangat kami hargai.\n\n",
            "Kami mohon pengertian: sesi User Interview *{{posisi}}* dipindah ke {{waktu}} secara {{tipe}}.\n{{detail_lokasi}}\n\n{{instruksi}}\n\nMohon konfirmasi ulang.\n\n",
            "Pemberitahuan reschedule dengan segala hormat:\nPosisi {{posisi}}\nWaktu baru {{waktu}}\nMode {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Jadwal sebelumnya tidak berlaku. Berikut jadwal User Interview *{{posisi}}* yang baru:\n{{waktu}} - {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Kami memohon kesediaan {{saudara}} menyesuaikan ke jadwal User Interview berikut:\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Update jadwal User Interview *{{posisi}}*: {{waktu}} ({{tipe}}).\n{{detail_lokasi}}\n\n{{instruksi}}\n\nMaaf atas perubahannya.\n\n",
            "Perubahan jadwal telah ditetapkan. Detail User Interview:\n{{waktu}} | {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\nPosisi: *{{posisi}}*.\n\n",
            "Mohon dicatat ulang jadwal User Interview {{saudara}} (*{{posisi}}*).\n{{waktu}}\n{{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
            "Reschedule User Interview {{posisi}} - dengan permintaan maaf kami.\nWaktu: {{waktu}}\nTipe: {{tipe}}\n{{detail_lokasi}}\n\n{{instruksi}}\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function completeProfile()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n"
                    . "Sehubungan dengan proses rekrutmen posisi *{{posisi}}* di *PT Inti Surya Laboratorium*, "
                    . "mohon berkenan untuk *melengkapi Data Diri & Berkas Pendukung* Anda melalui tautan resmi berikut:\n\n"
                    . "*Tautan Pengisian Data Diri:*\n{{link}}\n\n"
                    . "Data yang perlu dilengkapi meliputi:\n"
                    . "1. *Data Diri:* NIK, KK, NPWP, BPJS, & Alamat Lengkap\n"
                    . "2. *Pendidikan:* Jenjang, Nama Sekolah/Universitas, Jurusan, & IPK\n"
                    . "3. *Pengalaman Kerja:* Perusahaan Terakhir, Posisi, Masa Kerja, & Kontak Referensi\n"
                    . "4. *Dokumen Lampiran:* Softcopy KTP, KK, NPWP, Ijazah, Transkrip, & Sertifikat\n\n"
                    . "Kelengkapan data ini diperlukan untuk pembaruan data rekrutmen dan mendukung kelancaran proses selanjutnya.\n"
                    . "*Harap diperhatikan bahwa apabila data diri belum dilengkapi, maka proses rekrutmen tidak dapat dilanjutkan ke tahap selanjutnya.*\n\n"
                    . "Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
        ];

        $mids = [
            "Sehubungan dengan rekrutmen *{{posisi}}*, kami mohon kesediaan {{saudara}} untuk *melengkapi Data Diri & Berkas* melalui:\n\n{{link}}\n\nTanpa kelengkapan data, proses belum dapat kami lanjutkan. Kami menghargai kerja sama {{saudara}}.\n\n",
            "Dengan hormat, kami memohon bantuan {{saudara}} melengkapi data rekrutmen *{{posisi}}* pada tautan berikut:\n{{link}}\n\nSiapkan antara lain: KTP, KK, NPWP, ijazah, transkrip, dan CV.\n\n",
            "Agar proses *{{posisi}}* berjalan lancar, kami mohon perkenan {{saudara}} mengisi formulir data diri:\n{{link}}\n\nPengisian tepat waktu sangat membantu kami.\n\n",
            "Kami membutuhkan kelengkapan berkas {{saudara}} untuk administrasi *{{posisi}}*.\nSilakan lengkapi melalui: {{link}}\n\nTerima kasih atas kesediaannya.\n\n",
            "Izin mengingatkan dengan ramah: data diri untuk *{{posisi}}* masih perlu dilengkapi.\nLanjutkan pengisian di {{link}}\n\n",
            "Mohon berkenan melengkapi profil kandidat *{{posisi}}* melalui tautan resmi:\n{{link}}\n\nData digunakan untuk proses rekrutmen yang tertib.\n\n",
            "Satu langkah administrasi sebelum tahap berikutnya (*{{posisi}}*):\nmohon lengkapi data di {{link}}\n\nKami sangat menghargai kerja sama {{saudara}}.\n\n",
            "Formulir kelengkapan data telah kami sediakan untuk *{{posisi}}*.\n{{link}}\n\nMohon dilengkapi apabila berkenan dalam waktu dekat.\n\n",
            "Untuk pembaruan database rekrutmen *{{posisi}}*, kami mohon {{saudara}} mengisi:\n{{link}}\n\nPastikan data sesuai dokumen resmi.\n\n",
            "Dengan rendah hati kami mengingatkan kelengkapan Data Diri & Berkas (*{{posisi}}*).\nLink: {{link}}\n\nProses berikutnya menunggu kelengkapan ini.\n\n",
            "Silakan mengakses tautan resmi pengisian data:\n{{link}}\n\nPosisi terkait: *{{posisi}}*. Atas perhatiannya, terima kasih.\n\n",
            "Kami menunggu kelengkapan data {{saudara}} dengan penuh apresiasi.\nPosisi: *{{posisi}}*\nTautan: {{link}}\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function candidateRejection()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "Yth. {{saudara}} *{{nama}}*,\n\n"
                    . "Terima kasih atas waktu dan partisipasi Anda dalam proses rekrutmen untuk posisi *{{posisi}}*.\n\n"
                    . "Setelah melalui proses evaluasi, kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya. "
                    . "Keputusan ini diambil berdasarkan pertimbangan kebutuhan posisi saat ini.\n\n"
                    . "Kami menghargai minat Anda untuk bergabung bersama PT Inti Surya Laboratorium "
                    . "dan mendoakan yang terbaik untuk perjalanan karier Anda.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
            [
                'label' => 'ref_original_legacy',
                'body' => "{{sapaan}} {{nama}}\n\n"
                    . "Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi {{posisi}}.\n\n"
                    . "Bersamaan dengan ini, kami informasikan bahwa berdasarkan pertimbangan dan penilaian pihak kami, "
                    . "Anda *belum dapat lolos* ke tahap selanjutnya pada proses rekrutmen perusahaan kami.\n\n"
                    . "Kami menghargai ketertarikan dan waktu Anda selama proses rekrutmen PT Inti Surya Laboratorium, "
                    . "dan kami berharap Anda sukses dalam karier Anda di kemudian hari.\n\n"
                    . "Demikian informasi ini disampaikan. Terima kasih.",
            ],
        ];

        $opens = [
            "Yth. {{saudara}} *{{nama}}*,\n\n",
            "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n",
            "Yth. {{saudara}} *{{nama}}*,\n\n",
            "{{saudara}} *{{nama}}*, {{sapaan}}.\n\n",
            "Dengan hormat,\nYth. {{saudara}} *{{nama}}*,\n\n",
            "{{sapaan}}. Kepada {{saudara}} *{{nama}}*,\n\n",
            "Yth. {{saudara}} *{{nama}}*.\n\n",
            "{{sapaan}}, {{saudara}} *{{nama}}*.\n\n",
            "Kepada Yth. {{saudara}} *{{nama}}*,\n\n",
            "{{sapaan}} {{saudara}} *{{nama}}*,\n\n",
        ];

        $mids = [
            "Terima kasih yang sebesar-besarnya atas partisipasi {{saudara}} dalam seleksi *{{posisi}}*.\n\nSetelah pertimbangan yang saksama, dengan rendah hati kami sampaikan bahwa proses belum dapat kami lanjutkan ke tahap berikutnya. Keputusan ini terkait kebutuhan posisi saat ini.\n\nKami tetap menghargai minat {{saudara}} dan mendoakan yang terbaik.\n\n",
            "Kami mengapresiasi waktu dan antusiasme {{saudara}} pada posisi *{{posisi}}*.\n\nMohon maaf, saat ini kami belum dapat membawa proses {{saudara}} ke tahap selanjutnya.\n\nSemoga kesempatan yang lebih sesuai segera hadir.\n\n",
            "Dengan segala hormat, kami informasikan hasil evaluasi untuk *{{posisi}}*: lamaran belum dapat dilanjutkan.\n\nKeputusan ini bukan mengurangi nilai {{saudara}}, melainkan penyesuaian terhadap kebutuhan organisasi saat ini.\n\n",
            "Terima kasih atas lamaran *{{posisi}}*. Dengan penuh penghargaan, kami sampaikan bahwa proses belum dapat kami lanjutkan.\n\nSukses senantiasa kami doakan.\n\n",
            "Kami menghargai minat {{saudara}} bergabung pada *{{posisi}}*. Setelah peninjauan internal, kami belum dapat menawarkan kelanjutan tahap seleksi saat ini.\n\nJangan berkecil hati - semoga jalan karier berikutnya memberkahi.\n\n",
            "Setelah evaluasi untuk *{{posisi}}*, kami belum dapat melanjutkan lamaran {{saudara}}.\nKeputusan berkaitan dengan kebutuhan posisi.\n\nKami sangat menghargai waktu yang telah diluangkan.\n\n",
            "Dengan berat hati namun penuh hormat, kami sampaikan bahwa proses rekrutmen *{{posisi}}* belum dapat dilanjutkan untuk {{saudara}}.\n\nTerima kasih atas partisipasinya.\n\n",
            "Hasil seleksi *{{posisi}}*: kami belum dapat membawa {{saudara}} ke tahap berikutnya.\n\nKami tetap berterima kasih dan mendoakan yang terbaik.\n\n",
            "Terima kasih telah melamar *{{posisi}}* di PT Inti Surya Laboratorium. Setelah peninjauan, kami belum menemukan kecocokan untuk tahap lanjut saat ini.\n\nDoa terbaik untuk perjalanan karier {{saudara}}.\n\n",
            "Informasi hasil seleksi *{{posisi}}*: proses belum dilanjutkan.\n\nKami tetap menghargai minat {{saudara}} untuk bergabung.\n\n",
            "Mohon maaf, lamaran *{{posisi}}* belum dapat kami proses ke tahap berikutnya.\n\nTetap semangat mencari peluang yang tepat. Kami menghargai usaha {{saudara}}.\n\n",
            "Kami telah menyelesaikan evaluasi kandidat *{{posisi}}*. Dengan rendah hati kami sampaikan {{saudara}} belum masuk daftar lanjut.\n\nTerima kasih atas kesungguhan dan waktu yang diberikan.\n\n",
        ];

        return self::fromParts($refs, $opens, $mids, self::closesCorporate());
    }

    private static function salaryOffering()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n"
                    . "Terima kasih atas partisipasi Anda dalam proses rekrutmen posisi *{{posisi}}* di *PT Inti Surya Laboratorium*.\n\n"
                    . "Kami informasikan bahwa *Surat Penawaran Gaji (Salary Offering Letter)* telah kami kirimkan ke email: *{{email}}*.\n\n"
                    . "*Ringkasan Penawaran:*\n"
                    . "- Posisi: {{posisi}}\n"
                    . "- Penawaran Gaji: {{gaji}}\n\n"
                    . "Mohon periksa email Anda (termasuk folder spam) dan pelajari dokumen PDF yang kami lampirkan.\n"
                    . "Catatan: surat ini *bukan keputusan penerimaan kerja resmi* - keputusan final setelah tahapan administrasi berikutnya.\n\n"
                    . "Apabila ada pertanyaan, silakan hubungi tim HRD kami.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
        ];

        $mids = [
            "Terima kasih atas partisipasi {{saudara}} pada rekrutmen *{{posisi}}*.\n\nDengan hormat kami informasikan bahwa *Surat Penawaran Gaji* telah dikirim ke *{{email}}*.\n\nRingkasan: penawaran {{gaji}}.\n\nMohon diperiksa (termasuk folder spam). Surat ini masih tahap penawaran, belum merupakan keputusan penerimaan resmi.\n\n",
            "Kami menghargai perjalanan seleksi {{saudara}}. Dokumen *Salary Offering* posisi *{{posisi}}* ({{gaji}}) telah dikirim ke {{email}}.\n\nMohon ditinjau dengan seksama. Tanggapan {{saudara}} akan kami hargai.\n\n",
            "Izin menyampaikan: offering *{{posisi}}* sebesar {{gaji}} telah kami emailkan ke {{email}}.\n\nIni bukan hiring letter resmi. Mohon PDF-nya dipelajari terlebih dahulu.\n\n",
            "Dengan rendah hati kami sampaikan penawaran gaji untuk *{{posisi}}* sebesar *{{gaji}}*, beserta dokumen PDF ke email {{saudara}}.\n\nAlamat: {{email}}\n\nSilakan dipelajari. Kami siap membantu bila ada yang perlu diklarifikasi.\n\n",
            "Surat penawaran gaji *{{posisi}}* telah kami kirim ke {{email}}.\nNilai penawaran: {{gaji}}.\n\nMohon dicek folder utama dan spam.\n\n",
            "Sehubungan dengan proses administrasi *{{posisi}}*, Salary Offering Letter telah dikirim.\nEmail: {{email}}\nPenawaran: {{gaji}}\n\nCatatan: belum keputusan penerimaan final.\n\n",
            "Kami informasikan offering letter terkait *{{posisi}}* telah terkirim.\n{{gaji}} - silakan cek email {{email}}.\n\nAtas perhatiannya, terima kasih.\n\n",
            "Mohon periksa email *{{email}}* untuk dokumen penawaran gaji posisi *{{posisi}}* ({{gaji}}).\n\nKami mohon agar isinya dipelajari sebelum memberikan tanggapan.\n\n",
            "Penawaran formal untuk *{{posisi}}* telah kami siapkan di inbox {{saudara}} ({{email}}).\nRingkasan: {{gaji}}.\n\n",
            "Dengan hormat: PDF Salary Offering *{{posisi}}* dikirim ke {{email}}.\nPenawaran gaji {{gaji}}.\n\nBukan surat hiring.\n\n",
            "Silakan meninjau offering letter yang kami kirim.\nPosisi {{posisi}} | {{gaji}} | email {{email}}\n\nKami menghargai waktu peninjauan {{saudara}}.\n\n",
            "HRD telah mengirimkan surat penawaran gaji.\n*{{posisi}}* - {{gaji}}\nTujuan: {{email}}\n\nMohon dikonfirmasi setelah dibaca, apabila berkenan.\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function hiringLetter()
    {
        $refs = [
            [
                'label' => 'ref_original_ats',
                'body' => "{{sapaan}}, Yth. {{saudara}} *{{nama}}*\n\n"
                    . "Selamat! Berdasarkan hasil seleksi rekrutmen, Anda *diterima* untuk bergabung di *PT Inti Surya Laboratorium* "
                    . "pada posisi *{{posisi}}*.\n\n"
                    . "*Ringkasan Keputusan:*\n"
                    . "- Posisi: {{posisi}}\n"
                    . "- Gaji Pokok: {{gaji}}\n"
                    . "- Tanggal Mulai Kerja: {{tanggal_mulai}}\n\n"
                    . "Surat Keputusan Penerimaan Kerja (Hiring Letter) PDF telah kami kirimkan ke email: *{{email}}*.\n\n"
                    . "Mohon periksa email Anda (termasuk folder spam), unduh lampiran PDF, "
                    . "dan berikan konfirmasi penerimaan paling lambat *1 x 24 jam* sejak surat diterima.\n\n"
                    . "Apabila ada pertanyaan, silakan hubungi tim HRD kami.\n\n"
                    . "Salam,\n*Tim Recruitment HRD*\n*PT Inti Surya Laboratorium*",
            ],
        ];

        $mids = [
            "Dengan segala hormat dan rasa syukur, kami sampaikan bahwa {{saudara}} *diterima* bergabung sebagai *{{posisi}}* di PT Inti Surya Laboratorium.\n\nMulai kerja: {{tanggal_mulai}}\nGaji pokok: {{gaji}}\n\nHiring Letter telah dikirim ke {{email}}. Mohon konfirmasi penerimaan paling lambat *1x24 jam* setelah email diterima.\n\n",
            "Kami dengan rendah hati menyampaikan keputusan: *DITERIMA* - {{posisi}}.\nMulai: {{tanggal_mulai}} | Gaji: {{gaji}}\nPDF di email: {{email}}\n\nKonfirmasi wajib dalam 1x24 jam. Terima kasih atas kepercayaan {{saudara}} mengikuti proses ini.\n\n",
            "Senang dapat menyampaikan bahwa {{saudara}} diterima pada posisi *{{posisi}}*.\n\nDetail ada di Hiring Letter yang dikirim ke {{email}} (gaji {{gaji}}, mulai {{tanggal_mulai}}).\n\nMohon balas konfirmasi setelah membaca suratnya.\n\n",
            "Hiring Letter untuk *{{posisi}}* telah kami kirim ke {{email}}.\n{{tanggal_mulai}} | {{gaji}}\n\nLangkah yang kami mohon: unduh PDF â†’ baca â†’ konfirmasi kepada kami (maks. 1x24 jam).\n\n",
            "Selamat bergabung. {{saudara}} diterima sebagai *{{posisi}}*.\nMulai {{tanggal_mulai}}, gaji pokok {{gaji}}.\nDokumen resmi ada di email {{email}}.\n\nKami menghargai konfirmasi tepat waktu.\n\n",
            "Hasil akhir seleksi *{{posisi}}*: diterima.\nTanggal mulai {{tanggal_mulai}}\nGaji {{gaji}}\nHiring Letter: email {{email}}\n\nMohon konfirmasi secepatnya, terima kasih.\n\n",
            "Dengan hormat kami mengundang {{saudara}} bergabung di *{{posisi}}*.\nDetail keputusan ada di PDF yang dikirim ke {{email}}.\nRingkas: mulai {{tanggal_mulai}}, gaji {{gaji}}.\n\n",
            "Pemberitahuan penerimaan kerja - *{{posisi}}*.\n{{tanggal_mulai}} | {{gaji}}\nDokumen di {{email}}\n\nKonfirmasi maks. 1x24 jam.\n\n",
            "Hiring Letter telah terkirim.\nPosisi *{{posisi}}*, mulai {{tanggal_mulai}}, gaji {{gaji}}.\nTujuan email: {{email}}\n\nKami tunggu konfirmasi {{saudara}} dengan penuh apresiasi.\n\n",
            "{{saudara}} telah menyelesaikan proses hingga keputusan akhir: *diterima* di *{{posisi}}*.\nSilakan cek {{email}}.\nMulai kerja {{tanggal_mulai}} | {{gaji}}.\n\n",
            "Dengan rasa hormat, keputusan hiring untuk *{{posisi}}* telah final.\nEmail: {{email}}\nMulai: {{tanggal_mulai}}\nGaji: {{gaji}}\n\nMohon konfirmasi setelah membaca surat.\n\n",
            "Dokumen penerimaan kerja telah kami kirimkan.\n*{{posisi}}* - {{tanggal_mulai}} - {{gaji}}\nEmail: {{email}}\n\nKami menunggu kabar konfirmasi {{saudara}}.\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }

    private static function onboarding()
    {
        $refs = [
            [
                'label' => 'ref_original_legacy',
                'body' => "{{sapaan}} {{nama}}\n\n"
                    . "Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi {{posisi}}.\n\n"
                    . "Berdasarkan pertimbangan dan penilaian pihak kami, serta sesuai kesepakatan yang telah Anda setujui "
                    . "pada tahapan akhir proses rekrutmen, maka dengan ini kami informasikan bahwa Anda diterima untuk bergabung "
                    . "dengan perusahaan, terhitung per tanggal {{tanggal}}.\n\n"
                    . "Sehubungan dengan hal tersebut, Anda wajib hadir di perusahaan pada:\n\n"
                    . "Hari : {{hari}} / {{tanggal}}\n"
                    . "Jam : {{jam}}\n"
                    . "Alamat Perusahaan : {{alamat}}\n\n"
                    . "Apabila terdapat pertanyaan, Anda dapat menghubungi HRD melalui WhatsApp: 0811-1254-0719.\n\n"
                    . "Demikian pemberitahuan ini kami sampaikan, agar dapat diketahui dan dilaksanakan dengan baik. Terima kasih.",
            ],
        ];

        $mids = [
            "Menjelang hari pertama {{saudara}} sebagai *{{posisi}}*, dengan hormat kami sampaikan detail kehadiran:\n\n{{hari}}, {{tanggal}}\n{{jam}}\n{{alamat}}\n\nMohon hadir tepat waktu. Apabila membutuhkan bantuan, silakan hubungi HRD di 0811-1254-0719.\n\n",
            "Kami mengucapkan selamat bergabung pada posisi *{{posisi}}*, terhitung {{tanggal}}.\n\nChecklist kehadiran:\n1. Hadir {{hari}}, {{tanggal}} pukul {{jam}}\n2. Lokasi: {{alamat}}\n3. Bawa dokumen yang diminta HRD (apabila ada)\n\nPertanyaan: 0811-1254-0719\n\n",
            "Mohon kesediaan {{saudara}} hadir untuk memulai kerja *{{posisi}}*:\n{{hari}}/{{tanggal}} pukul {{jam}}\nLokasi: {{alamat}}\n\nWA HRD: 0811-1254-0719\n\n",
            "Menindaklanjuti keputusan penerimaan pada posisi *{{posisi}}*, kami mohon {{saudara}} hadir pada {{hari}}, {{tanggal}} pukul {{jam}} di:\n\n{{alamat}}\n\nInformasi lebih lanjut: HRD 0811-1254-0719.\n\n",
            "Dengan rendah hati kami sampaikan jadwal mulai kerja *{{posisi}}*.\n\n{{hari}}, {{tanggal}}\n{{jam}}\n{{alamat}}\n\nKami tunggu kehadiran {{saudara}}.\n\n",
            "Pemberitahuan mulai kerja - *{{posisi}}*.\nHari/tanggal: {{hari}} / {{tanggal}}\nJam: {{jam}}\nAlamat: {{alamat}}\n\nHubungi HRD 0811-1254-0719 bila ada pertanyaan.\n\n",
            "Hari pertama di Intilab (*{{posisi}}*):\n{{hari}}, {{tanggal}} pukul {{jam}}\n{{alamat}}\n\nMohon hadir sesuai jadwal. Terima kasih atas kerja samanya.\n\n",
            "Kami memohon kehadiran {{saudara}} untuk onboarding *{{posisi}}* pada {{hari}}, {{tanggal}} jam {{jam}}.\nLokasi: {{alamat}}\n\n",
            "Detail kehadiran pertama dengan hormat kami sampaikan:\nPosisi {{posisi}}\n{{hari}}, {{tanggal}} - {{jam}}\n{{alamat}}\n\nWA HRD 0811-1254-0719.\n\n",
            "{{saudara}} diharapkan hadir di kantor untuk memulai kerja *{{posisi}}*.\nJadwal: {{hari}}/{{tanggal}} pukul {{jam}}\nAlamat: {{alamat}}\n\n",
            "Onboarding *{{posisi}}* dimulai {{tanggal}}.\nMohon datang {{hari}} pukul {{jam}} ke {{alamat}}.\n\nAtas perhatiannya, terima kasih.\n\n",
            "Informasi kehadiran karyawan baru (*{{posisi}}*):\n{{hari}}, {{tanggal}}\nJam {{jam}}\n{{alamat}}\n\nBila membutuhkan bantuan: 0811-1254-0719.\n\n",
        ];

        return self::fromParts($refs, self::opensCorporate(), $mids, self::closesCorporate());
    }
}
