<?php

namespace App\Services;

class GenerateMessageHRD
{
    /**
     * Summary of bodyEmailRejectKandidat
     * @param object $data
     * @param string $nama_lengkap
     * @param string $nama_jabatan
     * @param string $hariIndonesia
     * @param string $tglInter
     * @param string $jam_interview
     * @param string $jenis_interview_hrd
     * @param string $link_gmeet_hrd
     * @param string $alamat_cabang
     * @return string
     */
    static function bodyEmailApproveKandidat($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                            <tr>
                                <td>
                                    <table align="center" cellpadding="0" cellspacing="0" width="600" style="color: black;">
                                        <tr>
                                            <td bgcolor="#ffffff" style="padding: 40px 30px;">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            Dear &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                                            <span style="font-weight: 700; color: black;">&nbsp;' . $data->nama_lengkap . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0; text-align: justify;">
                                                            Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi 
                                                            <span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0; text-align: justify;">
                                                            Bersamaan dengan ini, kami informasikan bahwa 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">Anda lolos tahap awal proses rekrutmen</span> dan kami mengundang Anda untuk melaksanakan 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">interview</span> dengan rincian informasi sebagai berikut:
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 130px;">
                                                            <table cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Hari / Tanggal</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->hariIndonesia . ' / ' . $data->tglInter . '</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Waktu</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jam_interview . '</span> WIB
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Sistem Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jenis_interview_hrd . '</span>
                                                                    </td>
                                                                </tr>';
                                                    if ($data->jenis_interview_hrd == 'Offline') {
                                                        $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Kode OTP</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->kode_uniq . '</span>
                                                                    </td>
                                                                </tr>';
                                                    } 
            if ($data->jenis_interview_hrd == 'Online') {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Link Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->link_gmeet_hrd . '
                                                                    </td>
                                                                </tr>';
            } else {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px;">Tempat</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->alamat_cabang . '
                                                                    </td>
                                                                </tr>';
            }
            $output .= '
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <table style="border: 3px solid;" cellpadding="0" cellspacing="0" width="600">
                                                                <tr align="center">
                                                                    <td style="font-weight: 600; text-decoration: underline; color: red;">Perhatian!</td>
                                                                </tr>
                                                                <tr align="center">';
                                                                    if ($data->jenis_interview_hrd == 'Offline') {
                                                                        $output .= '
                                                                            <td style="color: red;">
                                                                                Pastikan perangkat Anda dalam kondisi baik selama mengikuti tes atau proses lanjutan.<br><br>
                                                                                Anda juga <b>wajib melakukan konfirmasi kehadiran</b> dengan cara <span style="font-weight: 600; text-decoration: underline;">membalas pesan WhatsApp</span> ke nomor resmi perusahaan <span style="font-weight: 600; text-decoration: underline;">0811-8185-731</span> dengan format sebagai berikut:
                                                                            </td>';
                                                                    }else{
                                                                        $output .= '
                                                                        <td style="color: red;">
                                                                            Kode ini bersifat <b>sekali pakai</b> dan <b>tidak boleh dibagikan kepada siapapun</b>. Silakan gunakan kode ini hanya saat Anda hadir ke lokasi interview sebagai bukti verifikasi kehadiran.<br><br>
                                                                            Pastikan perangkat Anda dalam kondisi baik selama mengikuti tes atau proses lanjutan.<br><br>
                                                                            Anda juga <b>wajib melakukan konfirmasi kehadiran</b> dengan cara <span style="font-weight: 600; text-decoration: underline;">membalas pesan WhatsApp</span> ke nomor resmi perusahaan <span style="font-weight: 600; text-decoration: underline;">0811-8185-731</span> dengan format sebagai berikut:
                                                                        </td>';

                                                                    }
                                                             $output .= '</tr>
                                                                <tr align="center">
                                                                    <td style="color: red; font-weight: 600">Bersedia / Tidak Bersedia_Nama Lengkap</td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 5px 0 20px 0; text-align: justify;">
                                                            Demikian pemberitahuan ini kami sampaikan, terima kasih.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 450px;">Regards,</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 40px 0 40px 300px;"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 375px; font-weight: 600; color: black; white-space: nowrap;">
                                                            ( HRD PT. Inti Surya Laboratorium )
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            return $output;
        } else {
            return "";
        }
    }

    /**
     * Summary of bodyEmailRejectKandidat
     * @param mixed $data
     * @param string $nama_lengkap
     * @param string $nama_jabatan
     * @return string
     */
    static function bodyEmailRejectKandidat($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="650" style="border-collapse: collapse;">
                            <tr>
                                <td bgcolor="#ffffff" style="padding: 40px 30px;">
                                    <table style="color: black;">
                                        <tr>
                                            <td>
                                                Dear &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                                <span style="font-weight: 700; color: black;">&nbsp;' . $data->nama_lengkap . '</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px 0; text-align: justify;">
                                                Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi 
                                                <span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px 0; text-align: justify;">
                                                Bersamaan dengan ini, kami informasikan bahwa berdasarkan pertimbangan dan penilaian pihak kami, 
                                                <span style="font-weight: 600; color: black; text-decoration: underline;">
                                                    Anda belum dapat lolos ke tahap selanjutnya pada proses rekrutmen perusahaan kami.
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px 0; text-align: justify;">
                                                Kami menghargai ketertarikan dan waktu Anda selama proses rekrutmen PT Inti Surya Laboratorium, 
                                                dan kami berharap Anda sukses dalam karir Anda di kemudian hari.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; text-align: justify;">
                                                Demikian pemberitahuan ini kami sampaikan, terima kasih.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0 0 0 400px;">Regards,</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 40px 0 40px 300px;"></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0 0 0 320px; font-weight: 600; color: black;">
                                                ( HRD PT. Inti Surya Laboratorium )
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';

            return $output;
        } else {
            return "";
        }
    }

    protected function bodyEmailCV($data)
    {
        $image_path = public_path('recruitment/foto/' . $data->foto_selfie);
        if (file_exists($image_path)) {
            list($width, $height, $type, $attr) = getimagesize($image_path);

            switch ($type) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($image_path);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($image_path);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($image_path);
                    break;
                default:
                    die('Unsupported image type.');
            }

            $resized_image = imagescale($image, 120, 120);

            ob_start();
            imagejpeg($resized_image);
            $image_data = ob_get_contents();
            ob_end_clean();

            $base64_image = base64_encode($image_data);

            imagedestroy($image);
            imagedestroy($resized_image);
        }
        $output = '
        <h2>Detail Kandidat</h2>

        <!-- Header dengan Foto -->
        <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 20px;">
            <!-- Left Column: Photo -->
            <div style="flex: 0 0 120px; text-align: center; margin-right: 20px;">
                <img src="https://apps.intilab.com/v3/public/recruitment/foto/' . $data->foto_selfie . '" alt="Foto Kandidat" style="width: 120px; height: 120px; border-radius: 50%; margin-bottom: 15px;">
            </div>
            <!-- Right Column: Name and Contact -->
            <div style="flex: 1; text-align: left;">
                <ul style="list-style-type: none; padding: 0;">
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Nama</span>
                        <span>: ' . ($data->nama_lengkap ?: '-') . '</span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Lokasi Penempatan</span>
                        <span>: ' . ($data->nama_cabang ?: '-') . '</span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Posisi Dilamar</span>
                        <span>: ' . ($data->posisi_di_lamar ?: '-') . '</span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Bagian Dilamar</span>
                        <span>: ' . ($data->nama_jabatan ?: '-') . '</span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Shio</span>
                        <span>: ' . ($data->shio ?: '-') . '</span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Elemen</span>
                        <span>: ' . ($data->elemen ?: '-') . '</span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Salary User</span>
                        <span>: ' . ("Rp " . number_format($data->salary_user, 0, ',', '.')) . '</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Review HRD -->
        <h2>Review HRD</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Review HRD By</span>
                    <span>: ' . ($data->nama_hrd ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Kepercayaan Diri</span>
                    <span>: ' . ($data->kepercayaan_diri ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Kemampuan Komunikasi</span>
                    <span>: ' . ($data->kemampuan_komunikasi ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Antusias Perusahaan</span>
                    <span>: ' . ($data->antusias_perusahaan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Pengetahuan Perusahaan</span>
                    <span>: ' . ($data->pengetahuan_perusahaan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Pengetahuan Jobs</span>
                    <span>: ' . ($data->pengetahuan_jobs ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Motivasi Kerja</span>
                    <span>: ' . ($data->motivasi_kerja ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Kesimpulan</span>
                    <span>: ' . ($data->kesimpulan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Catatan</span>
                    <span>: ' . ($data->catatan ?: '-') . '</span>
                </li>
            </ul>
        </div>

        <!-- Personal Information -->
        <h2>Personal Information</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Nationality</span>
                    <span>: ' . ($data->kebangsaan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Birth Place</span>
                    <span>: ' . ($data->tempat_lahir ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Gender</span>
                    <span>: ' . ($data->gender ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Marital Status</span>
                    <span>: ' . ($data->status_nikah ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Marital Date</span>
                    <span>: ' . ($data->tgl_nikah ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Marital Place</span>
                    <span>: ' . ($data->tempat_nikah ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">BPJS Kesehatan</span>
                    <span>: ' . ($data->bpjs_kesehatan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Kenalan Di Perusahaan</span>
                    <span>: ' . ($data->orang_dalam ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">ID Number</span>
                    <span>: ' . ($data->nik_ktp ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Date Of Birth</span>
                    <span>: ' . ($data->tanggal_lahir ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Religion</span>
                    <span>: ' . ($data->agama ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Salutation</span>
                    <span>: ' . ($data->nama_panggilan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Email</span>
                    <span>: ' . ($data->email ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">ID Expired Date</span>
                    <span>: ' . ($data->tgl_exp_identitas ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">BPJS Ketenagakerjaan</span>
                    <span>: ' . ($data->bpjs_ketenagakerjaan ?: '-') . '</span>
                </li>
            </ul>
        </div>

        <!-- Medical Information -->
        <h2>Medical Information</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Tinggi Badan</span>
                    <span>: ' . ($data->tinggi_badan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Berat Badan</span>
                    <span>: ' . ($data->berat_badan ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Mata</span>
                    <span>: ' . ($data->mata ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Golongan Darah</span>
                    <span>: ' . ($data->golongan_darah ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Penyakit Bawaan Lahir</span>
                    <span>: ' . ($data->penyakit_bawaan_lahir ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Penyakit Kronis</span>
                    <span>: ' . ($data->penyakit_kronis ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Riwayat Kecelakaan</span>
                    <span>: ' . ($data->riwayat_kecelakaan ?: '-') . '</span>
                </li>
            </ul>
        </div>

        <!-- Address & Phone Information -->
        <h2> Address & Phone Information </h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">
                <!-- Address Information -->
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Phone</span>
                    <span>: ' . ($data->no_hp ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Current Address</span>
                    <span>: ' . ($data->alamat_ktp ?: '-') . '</span>
                </li>
                <li>
                    <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">KTP Address</span>
                    <span>: ' . ($data->alamat_domisili ?: '-') . '</span>
                </li>
            </ul>
        </div>';

        if (!empty($data->pendidikan) && !empty(array_filter(json_decode($data->pendidikan, true)))) {
            $output .= '
        <!-- Education -->
        <h2>Education</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->pendidikan, true) as $pendidikan) {
                $output .= '<li>
                                <span style="display: inline-block; width: 180px; text-align: left;"><strong>' . ($pendidikan['jenjang'] ?: '-') . '-' . ($pendidikan['jurusan'] ?: '-') . '</strong>, ' . ($pendidikan['institusi'] ?: '-') . ', ' . ($pendidikan['tahun_masuk'] ?: '-') . '-' . ($pendidikan['tahun_lulus'] ?: '-') . '</span>
                            </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->pengalaman_kerja) && !empty(array_filter(json_decode($data->pengalaman_kerja, true)))) {
            $output .= '
        <!-- Job Experience -->
        <h2>Job Experience</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->pengalaman_kerja, true) as $pengalaman_kerja) {
                $output .= '<li>
                        <span style="display: inline-block; width: 180px; text-align: left;"><strong>' . ($pengalaman_kerja['posisi_kerja'] ?: '-') . '</strong> di <strong>' . ($pengalaman_kerja['nama_perusahaan'] ?: '-') . '</strong> dari <strong>' . ($pengalaman_kerja['mulai_kerja'] ?: '-') . '</strong> s/d <strong>' . ($pengalaman_kerja['akhir_kerja'] ?: '-') . '</strong></span>
                    </li>
                    <li>
                        <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Alasan Keluar</span>
                        <span>: ' . ($pengalaman_kerja['alasan_keluar'] ?: '-') . '</span>
                    </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->skill) && !empty(array_filter(json_decode($data->skill, true)))) {
            $output .= '
        <!-- Skill -->
        <h2>Skill</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->skill, true) as $skill) {
                $output .= '<li>
                        <span style="display: inline-block; width: 180px; text-align: left;"><strong>Keahlian</strong> : ' . ($skill['keahlian'] ?: '-') . '</span>
                        <span><strong>Rate</strong> : ' . ($skill['rate'] ?: '-') . '</span>
                    </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->skill_bahasa) && !empty(array_filter(json_decode($data->skill_bahasa, true)))) {
            $output .= '
        <!-- Language Skill -->
        <h2>Language Skill</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->skill_bahasa, true) as $language) {
                $output .= '<li>
                            <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Language</span>
                            <span>: ' . ($language['bahasa'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Reading</span>
                            <span>: ' . ($language['baca'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Writing</span>
                            <span>: ' . ($language['tulis'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Listening</span>
                            <span>: ' . ($language['dengar'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Speaking</span>
                            <span>: ' . ($language['bicara'] ?: '-') . '</span>
                        </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->minat) && !empty(array_filter(json_decode($data->minat, true)))) {
            $output .= '
        <!-- Interest -->
        <h2>Interest</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->minat, true) as $minat) {
                $output .= '<li>
                        <span style="display: inline-block; width: 180px; text-align: left;"><strong>Minat</strong> : ' . ($minat['minat'] ?: '-') . '</span>
                        <span><strong>Rate</strong> : ' . ($minat['rate'] ?: '-') . '</span>
                    </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->organisasi) && !empty(array_filter(json_decode($data->organisasi, true)))) {
            $output .= '
        <!-- Organization Activities -->
        <h2>Organization Activities</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->organisasi, true) as $organisasi) {
                $output .= '<li>
                        <span style="display: inline-block; width: 180px; text-align: left;"><strong>' . ($organisasi['posisi'] ?: '-') . '</strong> di <strong>' . ($organisasi['nama'] ?: '-') . '</strong> dari <strong>' . ($organisasi['mulai_org'] ?: '-') . '</strong> s/d <strong>' . ($organisasi['akhir_org'] ?: '-') . '</strong></span>
                    </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->referensi) && !empty(array_filter(json_decode($data->referensi, true)))) {
            $output .= '
        <!-- Reference List -->
        <h2>Reference List</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            $referensi = json_decode($data->referensi, true)[0];
            $output .= '<li>
                        <span style="display: inline-block; width: 180px; text-align: left;"><strong>' . ($referensi['nama'] ?: '-') . '</strong> dari <strong>' . ($referensi['instansi'] ?: '-') . '</strong> : ' . (!$referensi['telepon'] ? $referensi['email'] : (!$referensi['email'] ? $referensi['telepon'] : $referensi['telepon'] . ' / ' . $referensi['email'])) . '</span>
                    </li>';
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->sertifikat) && !empty(array_filter(json_decode($data->sertifikat, true)))) {
            $output .= '
        <!-- Certification -->
        <h2>Certification</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->sertifikat, true) as $sertifikat) {
                $output .= '<li>
                            <span style="display: inline-block; width: 180px; text-align: left; font-weight: bold;">Nama Sertifikat</span>
                            <span>: ' . ($sertifikat['nama'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Nomor Sertifikat</span>
                            <span>: ' . ($sertifikat['nomor'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Jenis Sertifikat</span>
                            <span>: ' . ($sertifikat['tipe'] ?: '-') . '</span>
                        </li>
                        <li>
                            <span style="display: inline-block; width: 180px; text-align: right; font-weight: bold;">Tanggal Berlaku</span>
                            <span>: ' . ($sertifikat['tanggal_sertifikasi'] ?: '-') . ' s/d ' . ($sertifikat['tanggal_expired'] ?: '-') . '</span>
                        </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        if (!empty($data->kursus) && !empty(array_filter(json_decode($data->kursus, true)))) {
            $output .= '
        <!-- Course Information -->
        <h2>Course Information</h2>
        <div style="text-align: left; margin-bottom: 20px;">
            <ul style="list-style-type: none; padding: 0;">';
            foreach (json_decode($data->kursus, true) as $kursus) {
                $output .= '<li>
                                <span style="display: inline-block; width: 180px; text-align: left;"><strong>' . ($kursus['nama'] ?: '-') . '</strong> di <strong>' . ($kursus['institusi'] ?: '-') . '</strong> dari <strong>' . ($kursus['mulai_kursus'] ?: '-') . '</strong> s/d <strong>' . ($kursus['akhir_kursus'] ?: '-') . '</strong></span>
                            </li>';
            }
            $output .= '
                </ul>
            </div>';
        }

        return $output;
    }

    protected function bodyEmailButtonIbu($btn)
    {
        $output = '
                    <div style="text-align: left; margin-bottom: 20px;">
                        <a href="' . $btn->approve . '" style="background-color: #007bff; color: #fff; padding: 10px 10px; font-size: 10px; text-decoration: none; border-radius: 5px;">Approve Kandidat</a>
                        <a href="' . $btn->reject . '" style="background-color: #ff0000; color: #fff; padding: 10px 10px; font-size: 10px; margin-left: 15px; text-decoration: none; border-radius: 5px;">Reject Kandidat</a>
                        <a href="' . $btn->keep . '" style="background-color: #fe5d05; color: #fff; padding: 10px 10px; font-size: 10px; margin-left: 15px; text-decoration: none; border-radius: 5px;">Hold +7 Hari</a>
                    </div>';

        return $output;
    }
    protected function bodyEmailButtonBapak($btn)
    {
        $output = '
                    <div style="text-align: left; margin-bottom: 20px;">
                        <a href="' . $btn->approve . '" style="background-color: #007bff; color: #fff; padding: 10px 10px; font-size: 10px; text-decoration: none; border-radius: 5px;">Approve Kandidat</a>
                        <a href="' . $btn->reject . '" style="background-color: #ff0000; color: #fff; padding: 10px 10px; font-size: 10px; margin-left: 15px; text-decoration: none; border-radius: 5px;">Reject Kandidat</a>
                    </div>';

        return $output;
    }

    /**
     * Summary of bodyEmailKeepApproveKandidat
     * @param mixed $data
     * @return string
     */
    static function bodyEmailKeepApproveKandidat($data, $btn = null, $mark)
    {
        if ($data != null) {
            $output = '
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Email Pemberitahuan</title>
            </head>
            <body style="background-color: #f4f4f4; margin: 0; padding: 0;">

            <div style="width: 100%; max-width: 700px; margin: 0 auto; background-color: white; padding: 20px; border: 1px solid #ddd; box-sizing: border-box;">

                <!-- Surat Pemberitahuan -->
                <div style="margin-bottom: 30px;">
                    <h2>Permohonan Persetujuan Kandidat</h2>

                    <hr>

                    <p>Yth. Bapak/Ibu Direktur,</p>
                    <p>Dengan hormat, kami informasikan bahwa saat ini terdapat kandidat potensial yang telah melalui tahap seleksi awal dan dinyatakan memenuhi kriteria untuk dipertimbangkan dalam proses selanjutnya. Kami mohon persetujuan Bapak/Ibu Direktur atas kandidat berikut:</p>

                    <ul style="padding-left: 20px;">
                        <li><strong>Nama Kandidat:</strong> ' . ($data->nama_lengkap ?: '-') . '</li>
                        <li><strong>Posisi yang Dilamar:</strong> ' . ($data->nama_jabatan ?: '-') . '</li>
                        <li><strong>Usia:</strong> ' . ($data->umur ?: '-') . '</li>
                        <li><strong>Alamat:</strong> ' . ($data->alamat_domisili ?: '-') . '</li>
                        <li><strong>Kontak:</strong> ' . (!$data->no_hp ? $data->email : (!$data->email ? $data->no_hp : $data->no_hp . ' / ' . $data->email)) . '</li>
                    </ul>
                    <p>Kandidat ini telah memenuhi sejumlah persyaratan awal dan memiliki potensi sesuai dengan kebutuhan perusahaan. Persetujuan Bapak/Ibu Direktur akan sangat membantu dalam menentukan langkah selanjutnya.</p>

                    <p>Terima kasih atas perhatian dan kerja samanya.</p>

                    <p>Hormat kami,<br>
                    <br>
                    <br>
                    <br>
                    HRD Recruitment Team</p>
                </div>

                <hr>';

            $output .= (new GenerateMessageHRD)->bodyEmailCV($data);

            if ($mark == 'Ibu Boss') {
                $output .= (new GenerateMessageHRD)->bodyEmailButtonIbu($btn);
            } else if ($mark == 'Bapak Boss') {
                $output .= (new GenerateMessageHRD)->bodyEmailButtonBapak($btn);
            }

            $output .= '
            </div>

            </body>
            </html>
            ';
            return $output;
        } else {
            return "";
        }
    }

    static function bodyEmailApproveIbuBoss($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                            <tr>
                                <td>
                                    <table align="center" cellpadding="0" cellspacing="0" width="600" bgcolor="#ffffff" style="padding: 40px 30px; color: black;">
                                        <tr>
                                            <td style="padding: 20px 0; text-align: justify;">
                                                Dengan hormat, 
                                                diinformasikan bahwa kandidat telah berhasil disetujui untuk posisi 
                                                <span style="font-weight: 600; color: #007bff;">' . $data->posisi_di_lamar . '</span> 
                                                setelah melalui proses interview dengan HRD. 
                                                Kandidat tersebut kini dijadwalkan untuk melaksanakan interview dengan User.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0 0 0 130px;">
                                                <table>
                                                    <tr>
                                                        <td><strong>Kandidat</strong></td>
                                                        <td> : </td>
                                                        <td><span style="font-weight: 600; color: black;">' . $data->nama_lengkap . '</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Posisi</strong></td>
                                                        <td> : </td>
                                                        <td><span style="font-weight: 600; color: black;">' . $data->posisi_di_lamar . '</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Bagian</strong></td>
                                                        <td> : </td>
                                                        <td><span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 20px 0; text-align: justify;">
                                                Terima kasih atas perhatian yang diberikan. Apabila ada pertanyaan atau informasi lebih lanjut yang dibutuhkan, 
                                                silakan menghubungi HR Manager.
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            return $output;
        } else {
            return "";
        }
    }

    static function bodyEmailInterviewCalon($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                            <tr>
                                <td>
                                    <table align="center" cellpadding="0" cellspacing="0" width="600" style="color: black;">
                                        <tr>
                                            <td bgcolor="#ffffff" style="padding: 40px 30px;">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            Dear &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                                            <span style="font-weight: 700; color: black;">&nbsp;' . $data->nama_lengkap . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0; text-align: justify;">
                                                            Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi 
                                                            <span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0; text-align: justify;">
                                                            Bersamaan dengan ini, kami informasikan bahwa 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">Anda lolos ke tahap selanjutnya proses rekrutmen</span> dan kami mengundang Anda untuk melaksanakan 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">interview lanjutan</span> dengan rincian informasi sebagai berikut:
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 130px;">
                                                            <table cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Hari / Tanggal</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->hariIndonesia . ' / ' . $data->tglInter . '</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Waktu</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jam_interview_user . '</span> WIB
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Sistem Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jenis_interview_user . '</span>
                                                                    </td>
                                                                </tr>';
            if ($data->jenis_interview_user == 'Online') {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Link Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->link_gmeet_user . '
                                                                    </td>
                                                                </tr>';
            } else {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px;">Tempat</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->alamat . '
                                                                    </td>
                                                                </tr>';
            }
            $output .= '
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <table style="border: 3px solid;" cellpadding="0" cellspacing="0" width="600">
                                                                <tr align="center">
                                                                    <td style="font-weight: 600; text-decoration: underline; color: red;">Perhatian!</td>
                                                                </tr>
                                                                <tr align="center">
                                                                    <td style="color: red;">
                                                                        Anda wajib melakukan 
                                                                        <span style="font-weight: 600; text-decoration: underline;">konfirmasi kehadiran interview</span> Anda dengan 
                                                                        <span style="font-weight: 600; text-decoration: underline;">membalas pesan Whatsapp pada nomor resmi perusahaan (0811-1254-0719),</span>
                                                                        dengan format sebagai berikut:
                                                                    </td>
                                                                </tr>
                                                                <tr align="center">
                                                                    <td style="color: red; font-weight: 600">Bersedia / Tidak Bersedia_Nama Lengkap</td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 5px 0 20px 0; text-align: justify;">
                                                            Demikian pemberitahuan ini kami sampaikan, terima kasih.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 450px;">Regards,</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 40px 0 40px 300px;"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 375px; font-weight: 600; color: black; white-space: nowrap;">
                                                            ( HRD PT. Inti Surya Laboratorium )
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            return $output;
        } else {
            return "";
        }
    }

    static function bodyEmailInterviewUser($data)
    {
        if ($data != null) {
            $output = $output = '
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Penilaian review HRD</title>
            </head>
            <body style="background-color: #f4f4f4; margin: 0; padding: 0;">

            <div style="width: 100%; max-width: 700px; margin: 0 auto; background-color: white; padding: 20px; border: 1px solid #ddd; box-sizing: border-box;">';

            $output .= (new GenerateMessageHRD)->bodyEmailCV($data);

            $output .= '
            </div>

            </body>
            </html>
            ';
            return $output;
        } else {
            return "";
        }

    }
    static function bodyEmailBypassOffering($data, $btn, $mark, $msg)
    {
        if ($data != null) {
            $output = '
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Email Pemberitahuan</title>
            </head>
            <body style="background-color: #f4f4f4; margin: 0; padding: 0;">

            <div style="width: 100%; max-width: 595px; margin: 0 auto; background-color: white; padding: 20px; border: 1px solid #ddd; box-sizing: border-box;">

                <!-- Surat Pemberitahuan -->
                <div style="margin-bottom: 30px;">
                    <h2>Pemberitahuan Bypass Offering Kandidat</h2>

                    <hr>

                    <p>Yth. Bapak/Ibu Direktur,</p>
                    <p>Dengan hormat, kami informasikan bahwa saat ini sudah dilakukan <strong>BYPASS</strong> ' . $msg . '. Sistem telah melakukan bypass to offering untuk kandidat dengan rincian sebagai berikut:</p>

                    <ul style="padding-left: 20px;">
                        <li><strong>Nama Kandidat:</strong> ' . ($data->nama_lengkap ?: '-') . '</li>
                        <li><strong>Posisi yang Dilamar:</strong> ' . ($data->nama_jabatan ?: '-') . '</li>
                        <li><strong>Usia:</strong> ' . ($data->umur ?: '-') . '</li>
                        <li><strong>Alamat:</strong> ' . ($data->alamat_domisili ?: '-') . '</li>
                        <li><strong>Kontak:</strong> ' . (!$data->no_hp ? $data->email : (!$data->email ? $data->no_hp : $data->no_hp . ' / ' . $data->email)) . '</li>
                    </ul>
                    <p>Proses selanjutnya akan dilaksanakan sesuai dengan prosedur yang berlaku. Apabila diperlukan informasi lebih lanjut atau klarifikasi, mohon untuk menghubungi kami.</p>

                    <p>Terima kasih atas perhatian dan kerja samanya.</p>

                    <p>Hormat kami,<br>
                    <br>
                    <br>
                    <br>
                    HRD Recruitment Team</p>
                </div>

                <hr>';

            $output .= (new GenerateMessageHRD)->bodyEmailCV($data);

            if ($mark) {
                $output .= '
                    <div style="text-align: left; margin-bottom: 20px;">
                        <a href="' . $btn->approve . '" style="background-color: #007bff; color: #fff; padding: 10px 10px; font-size: 10px; text-decoration: none; border-radius: 5px;">Approve Kandidat</a>
                        <a href="' . $btn->reject . '" style="background-color: #ff0000; color: #fff; padding: 10px 10px; font-size: 10px; margin-left: 15px; text-decoration: none; border-radius: 5px;">Reject Kandidat</a>
                        <a href="' . $btn->keep . '" style="background-color: #fe5d05; color: #fff; padding: 10px 10px; font-size: 10px; margin-left: 15px; text-decoration: none; border-radius: 5px;">Hold +7 Hari</a>
                    </div>';
            }

            $output .= '
            </div>

            </body>
            </html>
            ';
            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailReschedule($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                            <tr>
                                <td>
                                    <table align="center" cellpadding="0" style="color: black;" cellspacing="0" width="600">
                                        <tr>
                                            <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            Dear
                                                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                                            <span style="font-weight: 700; color: black;">&nbsp;' . $data->nama_lengkap . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                            Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi 
                                                            <span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                            Bersamaan dengan ini, kami informasikan bahwa 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">
                                                                Perubahan jadwal interview User
                                                            </span> 
                                                            dan kami mengundang Anda untuk melaksanakan 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">interview lanjutan</span>
                                                            dengan rincian informasi sebagai berikut :
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 130px;">
                                                            <table cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Hari / Tanggal</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->hariIndonesia . ' / ' . $data->tglInter . '</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Waktu</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jam_interview . '</span> WIB
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Sistem Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jenis_interview_hrd . '</span>
                                                                    </td>
                                                                </tr>';
            if ($data->jenis_interview_hrd == 'Online') {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Link Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->link_gmeet_hrd . '
                                                                    </td>
                                                                </tr>';
            } else {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px;">Tempat</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->alamat . '
                                                                    </td>
                                                                </tr>';
            }
            $output .= '
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <table style="border: 3px solid;" cellpadding="0" cellspacing="0" width="600">
                                                            <tr align="center">
                                                                <td style="font-weight: 600; text-decoration: underline; color: red;">Perhatian!</td>
                                                            </tr>
                                                            <tr align="center">
                                                                <td style="color: red;">
                                                                    Anda wajib melakukan 
                                                                    <span style="font-weight: 600; text-decoration: underline;">konfirmasi kehadiran interview</span> 
                                                                    Anda dengan 
                                                                    <span style="font-weight: 600; text-decoration: underline;">
                                                                        membalas pesan WhatsApp pada nomor resmi perusahaan 
                                                                        (0811-1254-0719),
                                                                    </span> 
                                                                    dengan format sebagai berikut :
                                                                </td>
                                                            </tr>
                                                            <tr align="center">
                                                                <td style="color: red; font-weight: 600">
                                                                    Bersedia / Tidak Bersedia_Nama Lengkap
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 5px 0 20px 0; text-align: justify;">
                                                            Demikian pemberitahuan ini kami sampaikan, terima kasih.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 450px;">Regards,</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 40px 0 40px 300px;"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 375px; font-weight: 600; color: black; white-space: nowrap;">
                                                            ( HRD PT. Inti Surya Laboratorium )
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';

            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailRescheduleUser($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                            <tr>
                                <td>
                                    <table align="center" cellpadding="0" style="color: black;" cellspacing="0" width="600">
                                        <tr>
                                            <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            Dear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                                            <span style="font-weight: 700; color: black;">&nbsp;' . $data->nama_lengkap . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                            Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi 
                                                            <span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                            Bersamaan dengan ini, kami informasikan bahwa 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">
                                                                Terdapat perubahan jadwal interview User
                                                            </span> dan kami mengundang Anda untuk melaksanakan 
                                                            <span style="font-weight: 600; color: black; text-decoration: underline;">interview</span>
                                                            dengan rincian informasi sebagai berikut :
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0 0 0 130px;">
                                                            <table cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse;">
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Hari / Tanggal</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->hariIndonesia . ' / ' . $data->tglInter . '</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Waktu</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jam_interview_user . '</span> WIB
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Sistem Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        <span style="font-weight: 600; color: black;">' . $data->jenis_interview_user . '</span>
                                                                    </td>
                                                                </tr>';
            if ($data->jenis_interview_user == 'Online') {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px; white-space: nowrap;">Link Interview</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->link_gmeet_user . '
                                                                    </td>
                                                                </tr>';
            } else {
                $output .= '
                                                                <tr>
                                                                    <td style="text-align: right; padding: 5px;">Tempat</td>
                                                                    <td style="text-align: center; padding: 5px;">: </td>
                                                                    <td style="text-align: left; padding: 5px;">
                                                                        ' . $data->alamat . '
                                                                    </td>
                                                                </tr>';
            }
            $output .= '
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <table style="border: 3px solid;" cellpadding="0" cellspacing="0" width="600">
                                                            <tr align="center">
                                                                <td style="font-weight: 600; text-decoration: underline; color: red;">Perhatian!</td>
                                                            </tr>
                                                            <tr align="center">
                                                                <td style="color: red;">
                                                                    Anda wajib melakukan <span style="font-weight: 600; text-decoration: underline;">konfirmasi kehadiran interview</span> 
                                                                    Anda dengan <span style="font-weight: 600; text-decoration: underline;">membalas pesan Whatsapp</span> pada nomor resmi perusahaan 
                                                                    (0811-1254-0719), dengan format sebagai berikut :
                                                                </td>
                                                            </tr>
                                                            <tr align="center">
                                                                <td style="color: red; font-weight: 600">Bersedia / Tidak Bersedia_Nama Lengkap</td>
                                                            </tr>
                                                        </table>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 5px 0px 20px 0px; text-align: justify;">
                                                            Demikian pemberitahuan ini kami sampaikan, terima kasih.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0px 0px 0px 400px; text-align: center;">Regards,</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 40px 0px 40px 300px;"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0px 0px 0px 400px; font-weight: 600; color: black; text-align: center; white-space: nowrap;">
                                                            ( HRD PT. Inti Surya Laboratorium )
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailRejectHRD($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="650" style="border-collapse: collapse;">
                            <tr>
                                <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                    <table style="color: black;">
                                        <tr>
                                            <td>
                                                Dear&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                                <span style="font-weight: 700; color: black;">&nbsp;' . $data->nama_lengkap . '</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi
                                                <span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                Bersamaan dengan ini, kami informasikan bahwa berdasarkan pertimbangan dan penilaian pihak kami,
                                                <span style="font-weight: 600; color: black; text-decoration: underline;">
                                                    Anda belum dapat lolos ke tahap selanjutnya pada proses rekrutmen perusahaan kami.
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px 0px 10px 0px; text-align: justify;">
                                                Kami menghargai ketertarikan dan waktu Anda selama proses rekrutmen PT Inti Surya Laboratorium,
                                                dan kami berharap Anda sukses dalam karir Anda di kemudian hari.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0px 10px 0px; text-align: justify;">
                                                Demikian pemberitahuan ini kami sampaikan, terima kasih.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0px 0px 0px 400px;">Regards,</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 40px 0px 40px 300px;"></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0px 0px 0px 320px; font-weight: 600; color: black;">
                                                ( HRD PT. Inti Surya Laboratorium )
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailRejectIbuBoss($data)
    {
        if ($data != null) {
            $output = '
            <table>
                <table cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td>
                            <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                                <tr>
                                    <td>
                                        <table align="center" cellpadding="0" cellspacing="0" width="600">
                                            <tr>
                                                <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                                    <table style="color: black;">
                                                        <tr>
                                                            <td style="padding: 20px 0px 20px 0px; text-align: justify;">
                                                                Berhasil melakukan <span style="font-weight: 600; color: #ff0000;"> REJECT </span>
                                                                kandidat pada interview USER
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 0px 0px 0px 130px;">
                                                                <table>
                                                                    <tr>
                                                                        <td>Kandidat</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->nama_lengkap . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Posisi</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->posisi_di_lamar . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Bagian</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Hari</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->hariIndonesia . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Tanggal</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->tglInter . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Waktu </td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->jam_interview_user . '</span> WIB</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Tempat</td><td> : </td><td>' . $data->alamat . '</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </table>';

            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailKeepIbuBoss($data)
    {
        if ($data != null) {
            $output = '
            <table>
                <table cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td>
                            <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                                <tr>
                                    <td>
                                        <table align="center" cellpadding="0" cellspacing="0" width="600">
                                            <tr>
                                                <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                                    <table style="color: black;">
                                                        <tr>
                                                            <td style="padding: 20px 0px 20px 0px; text-align: justify;">
                                                                Berhasil melakukan <span style="font-weight: 600; color: #ff0000;">HOLD +7 Hari</span> kandidat pada interview USER
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 0px 0px 0px 130px;">
                                                                <table>
                                                                    <tr>
                                                                        <td>Kandidat</td><td>: </td><td><span style="font-weight: 600; color: black;">' . $data->nama_lengkap . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Posisi</td><td>: </td><td><span style="font-weight: 600; color: black;">' . $data->posisi_di_lamar . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Bagian</td><td>: </td><td><span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Hari</td><td>: </td><td><span style="font-weight: 600; color: black;">' . $data->hariIndonesia . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Tanggal</td><td>: </td><td><span style="font-weight: 600; color: black;">' . $data->tglInter . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Waktu</td><td>: </td><td><span style="font-weight: 600; color: black;">' . $data->jam_interview_user . '</span> WIB</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Tempat</td><td>: </td><td>' . $data->alamat . '</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </table>';
            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailApproveBapakBoss($data)
    {
        if ($data != null) {
            $output = '
            <table>
                <table cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td>
                            <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                                <tr>
                                    <td>
                                        <table align="center" cellpadding="0" cellspacing="0" width="600">
                                            <tr>
                                                <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                                    <table style="color: black;">
                                                        <tr>
                                                            <td style="padding: 20px 0px 20px 0px; text-align: justify;">
                                                                Berhasil melakukan 
                                                                <span style="font-weight: 600; color: #007bff;"> APPROVE </span> 
                                                                kandidat pada Offering Salary, dan kandidat akan terjadwalkan masuk kerja pada
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 0px 0px 0px 130px;">
                                                                <table>
                                                                    <tr>
                                                                        <td>Kandidat</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->nama_lengkap . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Posisi</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->posisi_di_lamar . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Bagian</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Tanggal Masuk</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->tglInter . '</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>Tempat</td><td> : </td><td>' . $data->alamat . '</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </table>';
            return $output;
        } else {
            return "";
        }
    }
    static function bodyEmailApproveOSCalon($data)
    {
        if ($data != null) {
            $output = '<table cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td>
                    <table align="center" cellpadding="0" cellspacing="0" width="650" style="border-collapse:collapse">
                        <tr>
                            <td bgcolor="#ffffff" style="padding:40px 30px 40px 30px">
                                <table style="color:#000">
                                    <tr>
                                        <td>Dear &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:
                                            <span style="font-weight:700;color:#000">&nbsp; ' . $data->nama_lengkap . '</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0 10px 0;text-align:justify">
                                            Terima kasih atas lamaran kerja yang telah Anda berikan kepada kami pada posisi 
                                            <span style="font-weight:600;color:#000">' . $data->posisi_di_lamar . '</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0 10px 0;text-align:justify">
                                            Berdasarkan pertimbangan dan penilaian pihak kami, serta sesuai dengan kesepakatan 
                                            yang telah Anda setujui pada tahapan akhir proses rekrutmen perusahaan kami, maka 
                                            dengan ini kami informasikan keputusan pihak perusahaan bahwa 
                                            <span style="font-weight:600;color:#000;text-decoration:underline">
                                                Anda diterima untuk bergabung dengan perusahaan, terhitung per tanggal (tanggal bergabung)
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0 10px 0;text-align:justify">
                                            Sehubungan dengan hal tersebut,
                                            <span style="font-weight:600;color:#000">Anda wajib hadir di perusahaan pada :</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 0 80px">
                                            <table>
                                                <tr>
                                                    <td>Hari / Tanggal</td>
                                                    <td>:</td>
                                                    <td><span style="font-weight:600;color:#000">' . $data->hariIndonesia . ' / ' . $data->tglInter . '</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Waktu</td>
                                                    <td>:</td>
                                                    <td><span style="font-weight:600;color:#000">08:00 </span>WIB</td>
                                                </tr>
                                                <tr>
                                                    <td>Alamat Perusahaan</td>
                                                    <td>:</td>
                                                    <td>' . $data->alamat . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:5px 0 10px 0;text-align:justify">
                                            Apabila terdapat pertanyaan, Anda dapat langsung menghubungi pihak HRD melalui Whatsapp 
                                            pada nomor : 0811-1254-0719.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:5px 0 10px 0;text-align:justify">
                                            Demikian pemberitahuan ini kami sampaikan, agar dapat diketahui, dipahami, dan dilaksanakan 
                                            dengan sebaik-baiknya, terima kasih.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 0 400px">Regards,</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:40px 0 40px 300px"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 0 320px;font-weight:600;color:#000">
                                            ( HRD PT. Inti Surya Laboratorium )
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

            return $output;
        } else {
            return "";
        }
    }

    static function bodyEmailRejectBapakBoss($data)
    {
        if ($data != null) {
            $output = '
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                            <tr>
                                <td>
                                    <table align="center" cellpadding="0" cellspacing="0" width="600">
                                        <tr>
                                            <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                                <table style="color: black;">
                                                    <tr>
                                                        <td style="padding: 20px 0px 20px 0px; text-align: justify;">
                                                            Berhasil melakukan <span style="font-weight: 600; color: #ff0000;"> REJECT </span> kandidat pada Offering Salary
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0px 0px 0px 130px;">
                                                            <table>
                                                                <tr>
                                                                    <td>Kandidat</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->nama_lengkap . '</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Posisi</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->posisi_di_lamar . '</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Bagian</td><td> : </td><td><span style="font-weight: 600; color: black;">' . $data->nama_jabatan . '</span></td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
            return $output;
        } else {
            return "";
        }
    }
}