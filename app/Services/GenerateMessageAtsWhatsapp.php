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
        $msg .= "📅 *Hari / Tanggal:* " . $hari . ", " . $tanggal . "\n";
        $msg .= "⏰ *Waktu:* " . $jam . " WIB\n";
        $msg .= "💻 *Metode:* " . $jenisMode . "\n";

        if ($jenisMode === 'Online') {
            $msg .= "🔗 *Link Meeting:* " . ($this->data->link_gmeet_hrd ?? '-') . "\n";
        } else {
            $alamat = trim($this->data->alamat_cabang ?? 'Ruang HRD PT Inti Surya Laboratorium');
            $msg .= "📍 *Lokasi Ruangan:* " . $alamat . "\n";
            $msg .= "📌 *Note:* Harap hadir 10 menit sebelum jadwal dan membawa dokumen pendukung (CV Terbaru, FC KTP & KK).\n";
        }

        $msg .= "\nMohon mengonfirmasi kehadiran Anda dengan membalas pesan ini dengan format:\n";
        $msg .= "*Hadir_" . $namaLengkap . "* atau *Reschedule_" . $namaLengkap . "*\n\n";
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

        $msg = "Yth. Bapak/Ibu *" . $namaLengkap . "*,\n\n";
        $msg .= "Terima kasih atas partisipasi Bapak/Ibu dalam proses seleksi untuk posisi *" . $posisi . "* di *PT Inti Surya Laboratorium*.\n\n";
        $msg .= "Setelah melalui proses evaluasi, kami memutuskan untuk melanjutkan proses dengan kandidat lain yang lebih sesuai dengan kebutuhan posisi tersebut. Dengan demikian, proses lamaran Bapak/Ibu belum dapat kami lanjutkan ke tahap berikutnya.\n\n";
        $msg .= "Data lamaran Bapak/Ibu akan tetap tersimpan dalam sistem kami dan dapat dipertimbangkan untuk kesempatan yang sesuai di kemudian hari.\n\n";
        $msg .= "Demikian informasi ini kami sampaikan. Terima kasih atas waktu dan partisipasi Bapak/Ibu selama proses seleksi.\n\n";
        $msg .= "Salam,\n";
        $msg .= "*Tim Recruitment & Talent Acquisition*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }
}
