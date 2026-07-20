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
        return view('TemplateEmail.hrd.partials.cv-detail', HrdEmailViewData::prepareCvData($data))->render();
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
        if ($data == null) {
            return "";
        }

        return view('TemplateEmail.hrd.permohonan-persetujuan-kandidat', [
            'data' => $data,
            'btn' => $btn,
            'mark' => $mark,
            'contact' => HrdEmailViewData::contactLine($data),
            'cv' => HrdEmailViewData::prepareCvData($data),
        ])->render();
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
        if ($data == null) {
            return "";
        }

        return view('TemplateEmail.hrd.approve-offering-salary-director', [
            'data' => $data,
        ])->render();
    }
    static function bodyEmailApproveOSCalon($data)
    {
        if ($data == null) {
            return "";
        }

        return view('TemplateEmail.hrd.approve-offering-salary-candidate', [
            'data' => $data,
        ])->render();
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