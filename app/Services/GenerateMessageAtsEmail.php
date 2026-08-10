<?php

namespace App\Services;

class GenerateMessageAtsEmail
{
    /**
     * Concise, Neutral & Professional Email Template for Approved Candidate (HRD Interview)
     * 
     * @param object $data
     * @return string
     */
    public static function bodyEmailApproveKandidat($data)
    {
        $namaLengkap = htmlspecialchars($data->nama_lengkap ?? 'Kandidat');
        $posisi = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? 'Posisi Dilamar');
        $hari = htmlspecialchars($data->hariIndonesia ?? '');
        $tanggal = htmlspecialchars($data->tglInter ?? '');
        $jam = htmlspecialchars($data->jam_interview ?? $data->jam_interview_hrd ?? '');
        $jenisMode = htmlspecialchars($data->jenis_interview_hrd ?? 'Online');
        
        $locationDetail = '';
        if ($jenisMode === 'Online') {
            $linkGmeet = htmlspecialchars($data->link_gmeet_hrd ?? '-');
            $locationDetail = "
                <tr>
                    <td style='padding: 6px 0; color: #475569; font-weight: 600; width: 140px;'>Meeting Link</td>
                    <td style='padding: 6px 0; color: #1e293b;'><a href='{$linkGmeet}' target='_blank' style='color: #2563eb; text-decoration: underline; font-weight: 600;'>{$linkGmeet}</a></td>
                </tr>";
        } else {
            $alamat = nl2br(htmlspecialchars($data->alamat_cabang ?? 'Ruang HRD PT Inti Surya Laboratorium'));
            $locationDetail = "
                <tr>
                    <td style='padding: 6px 0; color: #475569; font-weight: 600; width: 140px; vertical-align: top;'>Lokasi Ruangan</td>
                    <td style='padding: 6px 0; color: #1e293b; font-weight: 500;'>{$alamat}</td>
                </tr>
                <tr>
                    <td colspan='2' style='padding: 6px 0; color: #64748b; font-size: 13px;'>
                        * Harap hadir 10 menit sebelum jadwal dan membawa berkas pendukung (CV Terbaru, FC KTP & KK).
                    </td>
                </tr>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Undangan Interview HRD - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                <tr>
                    <td bgcolor='#1e293b' style='padding: 24px 32px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 2px;'>HRD & Talent Acquisition Division</div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        <p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$namaLengkap}</strong>,</p>
                        
                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Sehubungan dengan proses seleksi posisi <strong>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>, kami mengundang Anda untuk mengikuti tahapan <strong>Interview HRD</strong> dengan rincian sebagai berikut:
                        </p>

                        <!-- Schedule Box -->
                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Jadwal Wawancara
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600; width: 140px;'>Posisi</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 700;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>Hari & Tanggal</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 600;'>{$hari}, {$tanggal}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>Waktu</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 600;'>{$jam} WIB</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>Metode</td>
                                    <td style='padding: 4px 0; color: #2563eb; font-weight: 700;'>{$jenisMode}</td>
                                </tr>
                                {$locationDetail}
                            </table>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Mohon konfirmasi balasan ketersediaan Anda paling lambat <strong>24 jam</strong> setelah pesan ini diterima.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Demikian pemberitahuan ini kami sampaikan. Atas perhatian Anda, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor='#f8fafc' style='padding: 18px 32px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <strong style='color: #334155;'>Salam,</strong><br>
                        <span style='font-weight: 600; color: #0f172a;'>Tim Recruitment & Talent Acquisition</span><br>
                        PT Inti Surya Laboratorium
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Exact User Refined Dignified Rejection Email Template
     * 
     * @param object $data
     * @return string
     */
    public static function bodyEmailRejectKandidat($data)
    {
        $namaLengkap = htmlspecialchars($data->nama_lengkap ?? 'Kandidat');
        $posisi = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? 'Posisi Dilamar');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Informasi Hasil Seleksi - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                <tr>
                    <td bgcolor='#1e293b' style='padding: 24px 32px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 2px;'>HRD & Talent Acquisition Division</div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        <p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$namaLengkap}</strong>,</p>
                        
                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Terima kasih atas partisipasi Bapak/Ibu dalam proses seleksi untuk posisi <strong>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Setelah melalui proses evaluasi, kami memutuskan untuk melanjutkan proses dengan kandidat lain yang lebih sesuai dengan kebutuhan posisi tersebut. Dengan demikian, proses lamaran Bapak/Ibu belum dapat kami lanjutkan ke tahap berikutnya.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Data lamaran Bapak/Ibu akan tetap tersimpan dalam sistem kami dan dapat dipertimbangkan untuk kesempatan yang sesuai di kemudian hari.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Demikian informasi ini kami sampaikan. Terima kasih atas waktu dan partisipasi Bapak/Ibu selama proses seleksi.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor='#f8fafc' style='padding: 18px 32px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <strong style='color: #334155;'>Salam,</strong><br>
                        <span style='font-weight: 600; color: #0f172a;'>Tim Recruitment & Talent Acquisition</span><br>
                        PT Inti Surya Laboratorium
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
