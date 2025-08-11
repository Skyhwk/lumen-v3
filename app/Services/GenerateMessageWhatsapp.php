<?php

namespace App\Services;

use Carbon\Carbon;

class GenerateMessageWhatsapp
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    private function sapaan()
    {
        $nowJam = Carbon::now();
        $jam = $nowJam->hour;
        $sapaan = "";

        if ($jam >= 3 && $jam <= 10) {
            $sapaan = "Selamat Pagi";
        } else if ($jam >= 11 && $jam < 17) {
            $sapaan = "Selamat Siang";
        } else {
            $sapaan = "Selamat Malam";
        }

        return $sapaan;
    }

    /* 20250722 public function PassedCandidateSelection()
    {
        $message = $this->sapaan() . " " . \ucwords($this->data->nama_lengkap);
        $message .= "\n\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n*Bagian dilamar : " . $this->data->nama_jabatan . "*";
        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa Anda lolos ke tahap selanjutnya proses rekrutmen dan kami mengundang Anda untuk melaksanakan interview, dengan rincian informasi sebagai berikut : ";
        $message .= "\n\n*Hari : " . $this->data->hariIndonesia . " / " . $this->data->tglInter . "*";
        $message .= "\n*Jam : " . $this->data->jam_interview . "*";
        $message .= "\n*Sistem Interview : " . $this->data->jenis_interview_hrd . "*";

        if ($this->data->jenis_interview_hrd == 'Online') {
            $message .= "\n\n*Link Interview :* " . $this->data->link_gmeet_hrd;
        } else {
            $message .= "\n\n*Tempat :* *" . preg_replace('~[\r\n]+~', "*\n*", $this->data->alamat_cabang) . "*";
            $message .= "\nNote : Harap membawa berkas seperti *CV, FC KTP & KK, dan Sertifikat Vaksin*";
        }

        $message .= "\n\nAnda wajib melakukan konfirmasi kehadiran Anda dengan membalas pesan ini, dengan format sebagai berikut :";
        $message .= "\n\n*Bersedia / Tidak Bersedia _ Nama Lengkap*";
        $message .= "\n\nDemikian pemberitahuan ini kami sampaikan, terima kasih.";

        return $message;
    } */
   public function PassedCandidateSelection() 
    {
        $message = $this->sapaan() . " " . \ucwords($this->data->nama_lengkap);
        $message .= "\n\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n*Bagian dilamar : " . $this->data->nama_jabatan . "*";
        if($this->data->jenis_interview_hrd == 'Offline'){
            $message .= "\n*Kode : " . $this->data->kode_uniq . "*";
            // Tambahan kalimat penting di sini
            $message .= "\n\n❗Kode ini bersifat *sekali pakai* dan *tidak boleh* dibagikan kepada siapapun.";
            $message .= "\nPastikan perangkat yang Anda gunakan dalam kondisi baik saat mengikuti tahapan selanjutnya.";
            $message .= "\nGunakan kode ini saat Anda hadir ke lokasi interview untuk proses verifikasi.";
        }else{
            $message .= "\nPastikan perangkat yang Anda gunakan dalam kondisi baik saat mengikuti tahapan selanjutnya.";
        }

        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa Anda lolos ke tahap selanjutnya proses rekrutmen dan kami mengundang Anda untuk melaksanakan interview, dengan rincian informasi sebagai berikut : ";

        $message .= "\n\n*Hari : " . $this->data->hariIndonesia . " / " . $this->data->tglInter . "*";
        $message .= "\n*Jam : " . $this->data->jam_interview . "*";
        $message .= "\n*Sistem Interview : " . $this->data->jenis_interview_hrd . "*";

        if ($this->data->jenis_interview_hrd == 'Online') {
            $message .= "\n\n*Link Interview :* " . $this->data->link_gmeet_hrd;
        } else {
            $alamat = trim($this->data->alamat_cabang); // hilangkan spasi di awal/akhir
            $alamat = preg_replace('~[\r\n]+~', "\n", $alamat); // normalisasi baris baru
            $message .= "\n\n*Tempat :*\n*" . $alamat . "*";
            $message .= "\nNote : Harap membawa berkas seperti *CV, FC KTP & KK, dan Sertifikat Vaksin*";
        }

        $message .= "\n\nAnda wajib melakukan konfirmasi kehadiran Anda dengan membalas pesan ini, dengan format sebagai berikut :";
        $message .= "\n\n*Bersedia / Tidak Bersedia_Nama Lengkap*";
        $message .= "\n\nDemikian pemberitahuan ini kami sampaikan, terima kasih.";

        return $message;
    }

    public function RejectedCandidateSelection()
    {
        $message = $this->sapaan() . " " . \ucwords($this->data->nama_lengkap);
        $message .= "\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n\n*Bagian dilamar :* *" . $this->data->jabatan->nama_jabatan . "*";
        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa berdasarkan pertimbangan dan penilaian pihak kami, Anda *belum dapat lolos* ke tahap selanjutnya pada proses rekrutmen perusahaan kami. ";
        $message .= "\n\nKami menghargai ketertarikan dan waktu Anda selama proses rekrutmen PT Inti Surya Laboratorium, dan kami berharap Anda sukses dalam karir Anda di kemudian hari. ";
        $message .= "\n\nDemikian informasi ini disampaikan, Terimakasih.";
        return $message;
    }

    public function PassedHRD()
    {
        $message = $this->sapaan() . " " . $this->data->nama_lengkap;
        $message .= "\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n\n*Bagian dilamar : " . $this->data->nama_jabatan . "*";
        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa Anda *lolos ke tahap selanjutnya* proses rekrutmen dan kami mengundang Anda untuk melaksanakan *interview lanjutan*, dengan rincian informasi sebagai berikut : ";
        $message .= "\n\n*Hari : " . $this->data->hariIndonesia . " / " . $this->data->tglInter . "*";
        $message .= "\n*Jam : " . $this->data->jam_interview_user . "*";
        $message .= "\n*Sistem Interview : " . $this->data->jenis_interview_user . "*";
        if ($this->data->jenis_interview_user == 'Online') {
            $message .= "\n*Link Interview :* " . $this->data->link_gmeet_user;

        } else {
            $message .= "\n\n*Tempat :* *" . preg_replace('~[\r\n]+~', "*\n*", $this->data->alamat) . "*";
            $message .= "\nNote : Harap membawa berkas seperti *CV, FC KTP & KK, dan Sertifikat Vaksin*";
        }
        $message .= "\n\nAnda wajib melakukan konfirmasi kehadiran Anda dengan membalas pesan ini, dengan format sebagai berikut :";
        $message .= "\n\n*Bersedia / Tidak Bersedia _ Nama Lengkap*";
        $message .= "\n\nDemikian pemberitahuan ini kami sampaikan, terima kasih.";

        return $message;
    }

    public function RescheduleHRD()
    {
        $message = $this->sapaan() . " " . $this->data->nama_lengkap;
        $message .= "\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n\nBagian dilamar : " . $this->data->nama_jabatan;
        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa perubahan jadwal interview hrd dan kami mengundang Anda untuk melaksanakan interview lanjutan, dengan rincian informasi sebagai berikut : ";
        $message .= "\n\nHari : " . $this->data->hariIndonesia . " / " . $this->data->tglInter;
        $message .= "\nJam : " . $this->data->jam_interview;
        $message .= "\nSistem Interview : " . $this->data->jenis_interview_hrd;
        if ($this->data->jenis_interview_hrd == 'Online') {
            $message .= "\nLink Interview : " . $this->data->link_gmeet_hrd;

        } else {
            $message .= "\nTempat : " . $this->data->alamat;
        }
        $message .= "\n\nAnda wajib melakukan konfirmasi kehadiran Anda dengan membalas pesan ini, dengan format sebagai berikut :";
        $message .= "\n\nBersedia / Tidak Bersedia_Nama Lengkap";
        $message .= "\n\nDemikian pemberitahuan ini kami sampaikan, terima kasih.";

        return $message;
    }

    public function RejectedHRD()
    {
        $message = $this->sapaan() . " " . $this->data->nama_lengkap;
        $message .= "\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n\n*Bagian dilamar : " . $this->data->nama_jabatan . "*";
        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa berdasarkan pertimbangan dan penilaian pihak kami, Anda *belum dapat lolos* ke tahap selanjutnya pada proses rekrutmen perusahaan kami. ";
        $message .= "\n\nKami menghargai ketertarikan dan waktu Anda selama proses rekrutmen PT Inti Surya Laboratorium, dan kami berharap Anda sukses dalam karir Anda di kemudian hari. ";
        $message .= "\n\nDemikian informasi ini disampaikan, Terimakasih.";

        return $message;
    }

    public function RescheduleUser()
    {
        $message = $this->sapaan() . " " . $this->data->nama_lengkap;
        $message .= "\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n\nBagian dilamar : " . $this->data->nama_jabatan;
        $message .= "\n\nBersamaan dengan ini, kami informasikan bahwa perubahan jadwal interview User dan kami mengundang Anda untuk melaksanakan interview lanjutan, dengan rincian informasi sebagai berikut : ";
        $message .= "\n\nHari : " . $this->data->hariIndonesia . " / " . $this->data->tglInter;
        $message .= "\nJam : " . $this->data->jam_interview_user;
        $message .= "\nSistem Interview : " . $this->data->jenis_interview_user;
        if ($this->data->jenis_interview_user == 'Online') {
            $message .= "\n\nLink Interview : " . $this->data->link_gmeet_user;
        } else {
            $message .= "\n\nTempat : " . $this->data->alamat;
        }
        $message .= "\n\nAnda wajib melakukan konfirmasi kehadiran Anda dengan membalas pesan ini, dengan format sebagai berikut : ";
        $message .= "\n\nBersedia / Tidak Bersedia _ Nama Lengkap";
        $message .= "\n\nDemikian pemberitahuan ini kami sampaikan, terima kasih.";

        return $message;
    }

    public function PassedOS()
    {
        $message = $this->sapaan() . " " . $this->data->nama_lengkap;
        $message .= "\nTerima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi " . $this->data->posisi_di_lamar;
        $message .= "\n\nBerdasarkan pertimbangan dan penilaian pihak kami, serta sesuai dengan kesepakatan yang telah Anda setujui pada tahapan akhir proses rekrutmen perusahaan kami, maka dengan ini kami informasikan keputusan pihak perusahaan bahwa Anda diterima untuk bergabung dengan perusahaan, terhitung per tanggal " . $this->data->tglInter;
        $message .= "\n\nSehubungan dengan hal tersebut, Anda wajib hadir di perusahaan pada : ";
        $message .= "\n\nHari : " . $this->data->hariIndonesia . " / " . $this->data->tglInter;
        $message .= "\nJam : 08:00";
        $message .= "\nAlamat Perusahaan : " . $this->data->alamat;
        $message .= "\n\nApabila terdapat pertanyaan, Anda dapat langsung menghubungi pihak HRD melalui Whatsapp pada nomor : 0811-1254-0719.";
        $message .= "\n\nDemikian pemberitahuan ini kami sampaikan, agar dapat diketahui, dipahami, dan dilaksanakan dengan sebaik-baiknya, terima kasih.";

        return $message;
    }
}