<?php

namespace App\Services;

class GenerateMessageAtsEmail
{
    /**
     * Corporate & Dignified Email Template for Approved Candidate (HRD Interview Schedule)
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
                    <td style='padding: 8px 0; color: #475569; font-weight: 600; width: 140px;'>Meeting Link</td>
                    <td style='padding: 8px 0; color: #1e293b;'><a href='{$linkGmeet}' target='_blank' style='color: #2563eb; text-decoration: underline; font-weight: 600;'>{$linkGmeet}</a></td>
                </tr>";
        } else {
            $alamat = nl2br(htmlspecialchars($data->alamat_cabang ?? 'Ruang HRD PT Inti Surya Laboratorium'));
            $locationDetail = "
                <tr>
                    <td style='padding: 8px 0; color: #475569; font-weight: 600; width: 140px; vertical-align: top;'>Lokasi Ruangan</td>
                    <td style='padding: 8px 0; color: #1e293b; font-weight: 500;'>{$alamat}</td>
                </tr>
                <tr>
                    <td colspan='2' style='padding: 6px 0; color: #64748b; font-size: 13px; font-style: italic;'>
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
                    <td bgcolor='#1e293b' style='padding: 28px 36px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 4px;'>Talent Acquisition & Human Resource Development</div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 36px;'>
                        <p style='font-size: 15px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$namaLengkap}</strong>,</p>
                        
                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Terima kasih atas ketertarikan dan waktu yang Anda luangkan dalam mengajukan permohonan lamaran kerja untuk posisi 
                            <strong style='color: #1e293b;'>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Berdasarkan hasil penelaahan dan evaluasi awal terhadap profil serta kualifikasi yang Anda sampaikan, kami mengapresiasi rekam jejak profesional Anda. Dengan senang hati kami mengundang Anda untuk mengikuti tahapan <strong>Interview HRD</strong>.
                        </p>

                        <!-- Schedule Box -->
                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 20px; margin: 24px 0;'>
                            <div style='font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Rincian Jadwal Wawancara
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 6px 0; color: #475569; font-weight: 600; width: 140px;'>Posisi Dilamar</td>
                                    <td style='padding: 6px 0; color: #0f172a; font-weight: 700;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #475569; font-weight: 600;'>Hari & Tanggal</td>
                                    <td style='padding: 6px 0; color: #0f172a; font-weight: 600;'>{$hari}, {$tanggal}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #475569; font-weight: 600;'>Waktu</td>
                                    <td style='padding: 6px 0; color: #0f172a; font-weight: 600;'>{$jam} WIB</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #475569; font-weight: 600;'>Metode Interview</td>
                                    <td style='padding: 6px 0; color: #2563eb; font-weight: 700;'>{$jenisMode}</td>
                                </tr>
                                {$locationDetail}
                            </table>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Mohon dapat memberikan konfirmasi balasan ketersediaan kehadiran Anda paling lambat <strong>24 jam</strong> setelah pesan ini diterima.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Demikian undangan ini kami sampaikan. Kami berharap dapat berdiskusi lebih lanjut mengenai potensi kontribusi Anda bersama PT Inti Surya Laboratorium.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor='#f8fafc' style='padding: 20px 36px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <strong style='color: #334155;'>Hormat Kami,</strong><br>
                        <span style='font-weight: 600; color: #0f172a;'>Tim Recruitment & Talent Acquisition</span><br>
                        PT Inti Surya Laboratorium<br>
                        <span style='color: #94a3b8; font-size: 11px;'>Email ini dikirimkan secara otomatis oleh Applicant Tracking System (ATS).</span>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Corporate & Dignified Email Template for Rejected Candidate
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
            <title>Pemberitahuan Hasil Seleksi - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                <tr>
                    <td bgcolor='#1e293b' style='padding: 28px 36px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 4px;'>Talent Acquisition & Human Resource Development</div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 36px;'>
                        <p style='font-size: 15px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$namaLengkap}</strong>,</p>
                        
                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Terima kasih atas ketertarikan dan waktu yang telah Anda investasikan untuk melamar posisi 
                            <strong style='color: #1e293b;'>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Kami sangat mengapresiasi kualifikasi, pengalaman, serta antusiasme yang Anda tunjukkan selama tahap seleksi. Setiap aplikasi yang masuk kami tinjau secara seksama dan profesional oleh tim panitia seleksi rekrutmen kami.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Berdasarkan hasil evaluasi yang komprehensif, kami ingin menginformasikan bahwa untuk posisi ini kami telah memilih kandidat lain yang profil dan kompetensinya saat ini paling mendekati kebutuhan spesifik operasional kami. Oleh karena itu, dengan berat hati kami belum dapat melanjutkan lamaran Anda ke tahapan berikutnya.
                        </p>

                        <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;'>
                            <p style='font-size: 13px; line-height: 1.5; color: #475569; margin: 0; text-align: justify;'>
                                Profile & CV Anda akan tetap tersimpan secara aman dalam <em>Talent Database</em> PT Inti Surya Laboratorium. Apabila di kemudian hari terdapat peluang karir lain yang sesuai dengan latar belakang dan keahlian Anda, tim kami akan menghubungi Anda secara prioritas.
                            </p>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; text-align: justify;'>
                            Kami mengucapkan terima kasih yang sebesar-besarnya atas kepercayaan Anda kepada perusahaan kami. Semoga Anda senantiasa meraih kesuksesan dalam perjalanan karir dan pencapaian profesional Anda di masa mendatang.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor='#f8fafc' style='padding: 20px 36px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <strong style='color: #334155;'>Hormat Kami,</strong><br>
                        <span style='font-weight: 600; color: #0f172a;'>Tim Recruitment & Talent Acquisition</span><br>
                        PT Inti Surya Laboratorium<br>
                        <span style='color: #94a3b8; font-size: 11px;'>Email ini dikirimkan secara otomatis oleh Applicant Tracking System (ATS).</span>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
