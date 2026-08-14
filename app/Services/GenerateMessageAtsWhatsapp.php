<?php

namespace App\Services;

use Carbon\Carbon;

class GenerateMessageAtsWhatsapp
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    private function sapaan()
    {
        $jam = Carbon::now()->hour;
        if ($jam >= 3 && $jam <= 10) {
            return "Selamat Pagi";
        } else if ($jam >= 11 && $jam < 17) {
            return "Selamat Siang";
        } else {
            return "Selamat Malam";
        }
    }

    private function candidateSalutationLine($name)
    {
        $salutation = GenerateMessageAtsEmail::resolveSalutation($this->data);
        $namaLengkap = \ucwords($name);

        return "Yth. " . $salutation . " *" . $namaLengkap . "*";
    }

    private function internalSalutationLine($name)
    {
        $nama = \ucwords($name);

        return "Yth. Bapak/Ibu *" . $nama . "*";
    }

    /**
     * Concise, Neutral & Professional WhatsApp Message for Approved Candidate (HRD Interview)
     * 
     * @return string
     */
    public function PassedCandidateSelection()
    {
        $namaLengkap = \ucwords($this->data->nama_lengkap ?? 'Kandidat');
        $posisi = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';
        $hari = $this->data->hariIndonesia ?? '-';
        $tanggal = $this->data->tglInter ?? '-';
        $jam = $this->data->jam_interview ?? $this->data->jam_interview_hrd ?? '-';
        $jenisMode = $this->data->jenis_interview_hrd ?? 'Online';

        $msg = $this->sapaan() . ", " . $this->candidateSalutationLine($namaLengkap) . "\n\n";
        $msg .= "Sehubungan dengan proses seleksi posisi *" . $posisi . "* di PT Inti Surya Laboratorium, kami mengundang Anda untuk mengikuti tahapan *Interview HRD* pada:\n\n";
        $msg .= "*Hari / Tanggal:* " . $hari . ", " . $tanggal . "\n";
        $msg .= "*Waktu:* " . $jam . " WIB\n";
        $msg .= "*Metode:* " . $jenisMode . "\n";

        if ($jenisMode === 'Online') {
            $msg .= "*Link Meeting:* " . ($this->data->link_gmeet_hrd ?? '-') . "\n";
        } else {
            $alamat = trim($this->data->alamat_cabang ?? 'Ruang HRD PT Inti Surya Laboratorium');
            $msg .= "*Lokasi Ruangan:* " . $alamat . "\n";
            $msg .= "*Catatan:* Harap hadir 10 menit sebelum jadwal dan membawa berkas pendukung (CV Terbaru, FC KTP & KK).\n";
        }

        $msg .= "\nMohon mengonfirmasi ketersediaan Anda dengan membalas pesan ini.\n\n";
        $msg .= "Atas perhatian Anda, kami ucapkan terima kasih.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment HRD*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    /**
     * Exact User Refined Dignified Rejection WhatsApp Message
     * 
     * @return string
     */
    public function RejectedCandidateSelection()
    {
        $namaLengkap = \ucwords($this->data->nama_lengkap ?? 'Kandidat');
        $posisi      = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';

        $msg = $this->candidateSalutationLine($namaLengkap) . ",\n\n";
        $msg .= "Terima kasih atas waktu dan partisipasi Anda dalam proses rekrutmen untuk posisi *" . $posisi . "*.\n\n";
        $msg .= "Setelah melalui proses evaluasi, kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya. Keputusan ini diambil berdasarkan pertimbangan kebutuhan posisi saat ini.\n\n";
        $msg .= "Kami menghargai minat Anda untuk bergabung bersama PT Inti Surya Laboratorium dan mendoakan yang terbaik untuk perjalanan karier Anda.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment HRD*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    /**
     * WhatsApp message to candidate requesting profile & document completion
     * 
     * @return string
     */
    public function CompleteProfileCandidate()
    {
        $namaLengkap = \ucwords($this->data->nama_lengkap ?? 'Kandidat');
        $posisi      = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';
        $linkProfile = $this->data->link_complete_profile ?? ('https://portal.intilab.com/new-recruitment/complete-profile/' . rawurlencode($this->data->token ?? ''));

        $msg = $this->sapaan() . ", " . $this->candidateSalutationLine($namaLengkap) . "\n\n";
        $msg .= "Sehubungan dengan proses rekrutmen posisi *" . $posisi . "* di *PT Inti Surya Laboratorium*, mohon berkenan untuk *melengkapi Data Diri & Berkas Pendukung* Anda melalui tautan resmi berikut:\n\n";
        $msg .= "*Tautan Pengisian Data Diri:*\n" . $linkProfile . "\n\n";
        $msg .= "Data yang perlu dilengkapi meliputi:\n";
        $msg .= "1. *Data Diri:* NIK, KK, NPWP, BPJS, & Alamat Lengkap\n";
        $msg .= "2. *Pendidikan:* Jenjang, Nama Sekolah/Universitas, Jurusan, & IPK\n";
        $msg .= "3. *Pengalaman Kerja:* Perusahaan Terakhir, Posisi, Masa Kerja, & Kontak Referensi\n";
        $msg .= "4. *Dokumen Lampiran:* Softcopy KTP, KK, NPWP, Ijazah, Transkrip, & Sertifikat\n\n";
        $msg .= "Kelengkapan data ini diperlukan untuk pembaruan data rekrutmen dan mendukung kelancaran proses selanjutnya.\n";
        $msg .= "*Harap diperhatikan bahwa apabila data diri belum dilengkapi, maka proses rekrutmen tidak dapat dilanjutkan ke tahap selanjutnya.*\n\n";
        $msg .= "Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment HRD*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    public function Assessment()
    {
        $namaLengkap = \ucwords($this->data->nama_lengkap ?? 'Kandidat');
        $posisi      = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';
        $assessmentUrl = $this->data->assessment_url ?? ('https://portal.intilab.com/new-recruitment/assessment/' . rawurlencode($this->data->token ?? ''));

        $msg = $this->sapaan() . ", " . $this->candidateSalutationLine($namaLengkap) . "\n\n";
        $msg .= "Terima kasih telah mengirimkan lamaran untuk posisi *" . $posisi . "* di *PT Inti Surya Laboratorium*.\n\n";
        $msg .= "Lamaran Anda telah kami terima. Silakan lanjutkan ke tahap *Career Assessment* melalui tautan resmi berikut:\n\n";
        $msg .= "*Mulai Career Assessment:*\n" . $assessmentUrl . "\n\n";
        $msg .= "Link assessment berlaku selama *2 x 24 jam* sejak pendaftaran dibuat. Pastikan koneksi internet stabil dan kerjakan assessment hingga selesai.\n\n";
        $msg .= "Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment HRD*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    public function UserInterviewScheduleCandidate()
    {
        $namaLengkap = \ucwords($this->data->nama_kandidat ?? $this->data->nama_lengkap ?? 'Kandidat');
        $posisi      = $this->data->posisi ?? 'Posisi Dilamar';
        $tgl         = $this->data->tgl_interview ?? '-';
        $jenis       = strtolower(strip_tags($this->data->jenis_interview ?? 'online'));
        $linkGmeet   = $this->data->link_gmeet ?? '';
        $ruangan     = $this->data->ruangan_interview ?? 'Office Room';
        $catatan     = $this->data->catatan ?? '';

        $msg = $this->sapaan() . ", " . $this->candidateSalutationLine($namaLengkap) . "\n\n";
        $msg .= "Berikut kami sampaikan informasi jadwal *User Interview* Anda untuk posisi *" . $posisi . "* di *PT Inti Surya Laboratorium*:\n\n";
        $msg .= "*Waktu Interview*: " . $tgl . "\n";
        if ($jenis === 'online') {
            $msg .= "*Tipe Interview*: Online (Google Meet)\n";
            if (!empty($linkGmeet)) {
                $msg .= "*Link Google Meet*:\n" . $linkGmeet . "\n";
            }
        } else {
            $msg .= "*Tipe Interview*: Offline (Tatap Muka)\n";
            $msg .= "*Ruangan / Lokasi*: " . $ruangan . "\n";
        }
        $msg .= "\n";
        if ($jenis === 'online') {
            $msg .= "Mohon bergabung ke Google Meet 10 menit sebelum jadwal dimulai dan pastikan koneksi internet Anda stabil.\n\n";
        } else {
            $msg .= "Mohon dapat hadir 15 menit sebelum jadwal di lokasi yang telah ditentukan.\n\n";
        }
        $msg .= "Atas perhatian dan konfirmasi Anda, kami ucapkan terima kasih.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment HRD*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    public function UserInterviewScheduleUser()
    {
        $namaUser     = \ucwords($this->data->nama_user ?? 'Bapak/Ibu');
        $namaKandidat = \ucwords($this->data->nama_kandidat ?? 'Kandidat');
        $posisi       = $this->data->posisi ?? 'Posisi Requested';
        $noRequest    = $this->data->no_request ?? '-';
        $tgl          = $this->data->tgl_interview ?? '-';
        $jenis        = strtolower(strip_tags($this->data->jenis_interview ?? 'online'));
        $linkGmeet    = $this->data->link_gmeet ?? '';
        $ruangan      = $this->data->ruangan_interview ?? 'Office Room';
        $catatan      = $this->data->catatan ?? '';

        $msg = $this->sapaan() . ", " . $this->internalSalutationLine($namaUser) . "\n\n";
        $msg .= "Menginformasikan bahwa jadwal dan sarana sesi *User Interview* untuk kandidat pada Personnel Request Anda (No. Request: *" . $noRequest . "*) telah disiapkan:\n\n";
        $msg .= "👤 *Kandidat*: " . $namaKandidat . "\n";
        $msg .= "💼 *Posisi*: " . $posisi . "\n";
        $msg .= "📅 *Waktu Interview*: " . $tgl . "\n";
        if ($jenis === 'online') {
            $msg .= "💻 *Tipe Interview*: Online (Google Meet)\n";
            if (!empty($linkGmeet)) {
                $msg .= "🔗 *Link Google Meet*:\n" . $linkGmeet . "\n";
            }
        } else {
            $msg .= "🏢 *Tipe Interview*: Offline (Tatap Muka)\n";
            $msg .= "📍 *Ruangan Interview*: " . $ruangan . "\n";
        }
        if (!empty($catatan)) {
            $msg .= "📝 *Catatan*: " . $catatan . "\n";
        }
        $msg .= "\nTerima kasih atas perhatian dan kerjasamanya.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment HRD*\n";
        $msg .= "PT Inti Surya Laboratorium";

        return $msg;
    }
}
