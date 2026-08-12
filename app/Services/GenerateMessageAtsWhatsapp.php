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

        $msg = $this->sapaan() . ", Yth. Bapak/Ibu *" . $namaLengkap . "*\n\n";
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
        $msg .= "*Tim Recruitment & Talent Acquisition*\n";
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
        $posisi = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';

        $gender = strtolower((string) ($this->data->jenis_kelamin ?? $this->data->gender ?? ''));
        $salutation = $gender === 'female' ? 'Saudari' : 'Saudara';
        $msg = "Yth. " . $salutation . " *" . $namaLengkap . "*,\n\n";
        $msg .= "Terima kasih atas waktu dan partisipasi Anda dalam proses rekrutmen untuk posisi *" . $posisi . "*.\n\n";
        $msg .= "Setelah melalui proses evaluasi, kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya. Keputusan ini diambil berdasarkan pertimbangan kebutuhan posisi saat ini.\n\n";
        $msg .= "Kami menghargai minat Anda untuk bergabung bersama PT Inti Surya Laboratorium dan mendoakan yang terbaik untuk perjalanan karier Anda.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment*\n";
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
        $linkProfile = $this->data->link_complete_profile ?? ('https://portal.intilab.com/new-recruitment/complete-profile/' . ($this->data->token ?? ''));

        $msg = $this->sapaan() . ", Yth. Bapak/Ibu *" . $namaLengkap . "*\n\n";
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
        $msg .= "*Tim Recruitment & Talent Acquisition*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    public function Assessment()
    {
        $namaLengkap = \ucwords($this->data->nama_lengkap ?? 'Kandidat');
        $posisi      = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';
        $assessmentUrl = $this->data->assessment_url ?? ('https://portal.intilab.com/new-recruitment/assessment/' . rawurlencode($this->data->token ?? ''));

        $msg = $this->sapaan() . ", Yth. Bapak/Ibu *" . $namaLengkap . "*\n\n";
        $msg .= "Terima kasih telah mengirimkan lamaran untuk posisi *" . $posisi . "* di *PT Inti Surya Laboratorium*.\n\n";
        $msg .= "Lamaran Anda telah kami terima. Silakan lanjutkan ke tahap *Career Assessment* melalui tautan resmi berikut:\n\n";
        $msg .= "*Mulai Career Assessment:*\n" . $assessmentUrl . "\n\n";
        $msg .= "Link assessment berlaku selama *2 x 24 jam* sejak pendaftaran dibuat. Pastikan koneksi internet stabil dan kerjakan assessment hingga selesai.\n\n";
        $msg .= "Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment & Talent Acquisition*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }
}
