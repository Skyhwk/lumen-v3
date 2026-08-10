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
     * Corporate & Dignified WhatsApp Message for Approved Candidate (HRD Interview Schedule)
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

        $msg = $this->sapaan() . ", Yth. Sdr/Sdri. *" . $namaLengkap . "*\n\n";
        $msg .= "Terima kasih atas ketertarikan dan permohonan lamaran kerja yang Anda ajukan untuk posisi *" . $posisi . "* di PT Inti Surya Laboratorium.\n\n";
        $msg .= "Berdasarkan hasil seleksi dan verifikasi berkas awal, kami mengapresiasi kualifikasi Anda dan dengan senang hati mengundang Anda untuk mengikuti tahapan *Interview HRD*, yang akan dilaksanakan pada:\n\n";
        $msg .= "📅 *Hari / Tanggal:* " . $hari . ", " . $tanggal . "\n";
        $msg .= "⏰ *Waktu:* " . $jam . " WIB\n";
        $msg .= "💻 *Metode Interview:* " . $jenisMode . "\n";

        if ($jenisMode === 'Online') {
            $msg .= "🔗 *Link Meeting:* " . ($this->data->link_gmeet_hrd ?? '-') . "\n";
        } else {
            $alamat = trim($this->data->alamat_cabang ?? 'Ruang HRD Kantor Pusat PT Inti Surya Laboratorium');
            $msg .= "📍 *Lokasi Ruangan:* " . $alamat . "\n";
            $msg .= "📌 *Note:* Harap hadir 10 menit lebih awal dengan membawa dokumen pendukung (CV Terbaru, FC KTP & KK).\n";
        }

        $msg .= "\nMohon konfirmasi ketersediaan kehadiran Anda dengan membalas pesan ini dengan format:\n";
        $msg .= "*Hadir_" . $namaLengkap . "* atau *Reschedule_" . $namaLengkap . "*\n\n";
        $msg .= "Demikian informasi ini kami sampaikan. Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.\n\n";
        $msg .= "Salam hangat,\n";
        $msg .= "*Recruitment & Talent Acquisition Team*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }

    /**
     * Corporate & Dignified WhatsApp Message for Rejected Candidate
     * 
     * @return string
     */
    public function RejectedCandidateSelection()
    {
        $namaLengkap = \ucwords($this->data->nama_lengkap ?? 'Kandidat');
        $posisi = $this->data->posisi_di_lamar ?? $this->data->nama_jabatan ?? 'Posisi Dilamar';

        $msg = $this->sapaan() . ", Yth. Sdr/Sdri. *" . $namaLengkap . "*\n\n";
        $msg .= "Terima kasih atas waktu, perhatian, dan antusiasme Anda dalam melamar posisi *" . $posisi . "* di PT Inti Surya Laboratorium.\n\n";
        $msg .= "Melalui pesan ini, kami ingin menginformasikan bahwa setelah melalui proses evaluasi kualifikasi secara mendalam oleh tim seleksi, kami telah memilih kandidat lain yang profilnya saat ini lebih sesuai dengan kebutuhan spesifik operasional posisi ini.\n\n";
        $msg .= "Kami sangat menghargai waktu dan usaha yang telah Anda berikan. Data profil Anda akan tetap tersimpan secara aman dalam bank data talent kami untuk peluang karir mendatang yang lebih sesuai.\n\n";
        $msg .= "Kami mendoakan yang terbaik untuk kesuksesan karir dan pencapaian profesional Anda di masa depan.\n\n";
        $msg .= "Salam hormat,\n";
        $msg .= "*Recruitment & Talent Acquisition Team*\n";
        $msg .= "*PT Inti Surya Laboratorium*";

        return $msg;
    }
}
