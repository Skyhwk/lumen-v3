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

    /**
     * Professional email notification to Personnel Request creator when HRD approves a candidate
     *
     * @param object $data  { nama_user, nama_kandidat, posisi, no_request, approved_by, approved_at }
     * @return string
     */
    public static function bodyEmailHrdApprovalNotifUser($data)
    {
        $namaUser      = htmlspecialchars($data->nama_user      ?? 'User');
        $namaKandidat  = htmlspecialchars($data->nama_kandidat  ?? 'Candidate');
        $posisi        = htmlspecialchars($data->posisi         ?? '-');
        $noRequest     = htmlspecialchars($data->no_request     ?? '-');
        $approvedBy    = htmlspecialchars($data->approved_by    ?? 'HRD');
        $approvedAt    = htmlspecialchars($data->approved_at    ?? '-');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Pemberitahuan Hasil Interview HRD - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                <tr>
                    <td bgcolor='#1e293b' style='padding: 24px 32px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 2px;'>Divisi HRD &amp; Talent Acquisition</div>
                    </td>
                </tr>

                <!-- Status Banner -->
                <tr>
                    <td style='padding: 0;'>
                        <div style='background-color: #dcfce7; border-left: 4px solid #16a34a; padding: 14px 32px;'>
                            <span style='font-size: 13px; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px;'>&#10003; Interview HRD Disetujui</span>
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        <p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$namaUser}</strong>,</p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Dengan hormat, kami informasikan bahwa kandidat berikut telah <strong>dinyatakan lulus tahap Interview HRD</strong> untuk permintaan personel yang Bapak/Ibu ajukan. Kandidat tersebut kini siap untuk dijadwalkan pada tahap <strong>Interview User</strong>.
                        </p>

                        <!-- Candidate Info Box -->
                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Informasi Kandidat
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600; width: 160px;'>Nama Kandidat</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 700;'>{$namaKandidat}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Posisi Dilamar</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>No. Permintaan</td>
                                    <td style='padding: 5px 0; color: #2563eb; font-weight: 700;'>{$noRequest}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Disetujui Oleh</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$approvedBy}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tanggal Persetujuan</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$approvedAt}</td>
                                </tr>
                            </table>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Mohon segera berkoordinasi dengan tim HRD untuk menjadwalkan <strong>Interview User</strong> sesuai waktu yang tersedia.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Demikian pemberitahuan ini kami sampaikan. Atas perhatian Bapak/Ibu, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor='#f8fafc' style='padding: 18px 32px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <strong style='color: #334155;'>Salam,</strong><br>
                        <span style='font-weight: 600; color: #0f172a;'>Tim HRD &amp; Talent Acquisition</span><br>
                        PT Inti Surya Laboratorium
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }


    public static function bodyEmailHrdSchaduled($data)
    {
        $namaUser       = htmlspecialchars($data->nama_user ?? 'User');
        $namaKandidat   = htmlspecialchars($data->nama_kandidat ?? 'Candidate');
        $divisi         = htmlspecialchars($data->divisi ?? '-');
        $posisi         = htmlspecialchars($data->posisi ?? '-');
        $cabang         = htmlspecialchars($data->cabang ?? '-');
        $jenisInterview = htmlspecialchars($data->jenis_interview ?? '-');
        $catatan        = $data->catatan_interview ?? '-'; // TinyMCE content (HTML)
        $tglInterview   = isset($data->tgl_interview) ? \Carbon\Carbon::parse($data->tgl_interview)->locale('id')->translatedFormat('l, d F Y H:i') : '-';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Jadwal Interview Kandidat - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                <tr>
                    <td bgcolor='#1e293b' style='padding: 24px 32px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 2px;'>Divisi HRD &amp; Talent Acquisition</div>
                    </td>
                </tr>

                <!-- Status Banner -->
                <tr>
                    <td style='padding: 0;'>
                        <div style='background-color: #e0f2fe; border-left: 4px solid #0284c7; padding: 14px 32px;'>
                            <span style='font-size: 13px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;'>&#9432; Jadwal Interview Telah Dibuat</span>
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        <p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. <strong>Tim HRD</strong>,</p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Melalui email ini kami informasikan bahwa User (<strong>Bapak/Ibu {$namaUser}</strong>) telah selesai menjadwalkan <strong>Interview User</strong> untuk kandidat di bawah ini.
                        </p>

                        <!-- Candidate Info Box -->
                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Informasi Kandidat
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600; width: 140px;'>Nama Kandidat</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 700;'>{$namaKandidat}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Divisi</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$divisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Posisi</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Cabang</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$cabang}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tanggal Interview</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$tglInterview} WIB</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tipe Interview</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600; text-transform: capitalize;'>{$jenisInterview}</td>
                                </tr>
                            </table>
                        </div>

                        <div style='margin-top: 20px;'>
                            <strong style='font-size: 14px; color: #475569;'>Catatan / Pesan Tambahan:</strong>
                            <div style='font-size: 14px; line-height: 1.6; color: #334155; background-color: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 4px; margin-top: 6px;'>
                                {$catatan}
                            </div>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0; margin-top: 24px;'>
                            Mohon bantuan Tim HRD untuk menindaklanjuti penyiapan proses selanjutnya, baik berupa pembuatan tautan (link) Google Meet maupun penyiapan ruangan yang akan digunakan untuk proses Interview User. Atas perhatian dan kerja sama yang baik, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Email notifikasi hasil interview user ke HRD
     */
    static function bodyEmailHasilInterviewUser($recruitment, $pr, $interview, $decision)
    {
        $profile = \Illuminate\Support\Facades\DB::table('candidate_profiles')
            ->where('new_recruitment_id', $recruitment->id)->first();
            
        $educations = \Illuminate\Support\Facades\DB::table('candidate_educations')
            ->where('new_recruitment_id', $recruitment->id)
            ->where('is_active', 1)->get();

        $photoDoc = \Illuminate\Support\Facades\DB::table('candidate_documents')
            ->where('new_recruitment_id', $recruitment->id)
            ->where('is_active', 1)
            ->where(function($q) {
                $q->where('jenis_dokumen', 'like', '%Foto%')
                  ->orWhere('jenis_dokumen', 'like', '%Photo%');
            })->first();

        $photoUrl = $photoDoc ? 'https://apps.intilab.com/v3/public/' . ltrim($photoDoc->path_file, '/') : '';

        $formatDateId = function($dateStr) {
            if (empty($dateStr) || $dateStr === '-' || $dateStr === '0000-00-00') return '-';
            try {
                return \Carbon\Carbon::parse($dateStr)->locale('id')->isoFormat('D MMMM YYYY');
            } catch (\Exception $e) {
                return $dateStr;
            }
        };

        $dataCv = (object) array_merge(
            $recruitment ? $recruitment->toArray() : [],
            $profile ? (array)$profile : [],
            [
                'nama_cabang' => $pr->detailCabang->nama_cabang ?? $pr->lokasi_penempatan_cabang ?? '-',
                'posisi_di_lamar' => $pr->posisi ?? '-',
                'nama_jabatan' => $pr->detailPosisi->nama_jabatan ?? '-',
                'status_nikah' => $profile->status_pernikahan ?? null,
                'bpjs_kesehatan' => $profile->no_bpjs_ks ?? null,
                'bpjs_ketenagakerjaan' => $profile->no_bpjs_tk ?? null,
                'alamat_domisili' => $profile->alamat_domisili ?? null,
                'alamat_ktp' => $profile->alamat_ktp ?? null,
                'no_hp' => $recruitment->no_telepon ?? null,
                'gender' => $recruitment->jenis_kelamin ?? null,
                'tgl_nikah' => $formatDateId($recruitment->tgl_nikah ?? null),
                'tanggal_lahir' => $formatDateId($recruitment->tanggal_lahir ?? null),
                'tgl_exp_identitas' => $formatDateId($recruitment->tgl_exp_identitas ?? null),
            ]
        );

        $pendidikan = $educations->map(function($ed) {
            return [
                'jenjang' => $ed->jenjang_pendidikan,
                'institusi' => $ed->nama_institusi,
                'jurusan' => $ed->jurusan,
                'tahun_masuk' => $ed->tahun_masuk,
                'tahun_lulus' => $ed->tahun_lulus,
            ];
        })->toArray();

        $pengalamanKerja = is_string($recruitment->pengalaman_kerja) ? json_decode($recruitment->pengalaman_kerja, true) : ($recruitment->pengalaman_kerja ?? []);
        if (is_array($pengalamanKerja)) {
            foreach ($pengalamanKerja as &$pk) {
                if (isset($pk['mulai_kerja'])) $pk['mulai_kerja'] = $formatDateId($pk['mulai_kerja']);
                if (isset($pk['akhir_kerja'])) $pk['akhir_kerja'] = $formatDateId($pk['akhir_kerja']);
            }
        }
        
        $skill = is_string($recruitment->skill) ? json_decode($recruitment->skill, true) : ($recruitment->skill ?? []);

        $cv = [
            'data' => $dataCv,
            'photoUrl' => $photoUrl,
            'pendidikan' => $pendidikan,
            'pengalamanKerja' => $pengalamanKerja,
            'skills' => $skill,
            'skillBahasa' => [],
            'minat' => [],
            'organisasi' => [],
            'referensi' => [],
            'sertifikat' => [],
            'kursus' => [],
            'salaryFormatted' => \App\Services\HrdEmailViewData::formatRupiah($recruitment->salary_user ?? null),
        ];
       
        $hrdInterview = \Illuminate\Support\Facades\DB::table('recruitment_interviews')
            ->where('new_recruitment_id', $recruitment->id)
            ->where('stage', 'hrd')
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->first();

        return view('TemplateEmail.ats.hasil-interview-user', [
            'recruitment' => $recruitment,
            'pr' => $pr,
            'interview' => $interview,
            'hrdInterview' => $hrdInterview,
            'decision' => $decision,
            'cv' => $cv
        ])->render();
    }

    /**
     * Professional Email Template to Candidate for Completing Personal Profile after passing HRD Interview
     * 
     * @param object $data
     * @return string
     */
    public static function bodyEmailCompleteProfileCandidate($data)
    {
        $namaLengkap = htmlspecialchars($data->nama_lengkap ?? 'Kandidat');
        $posisi      = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? 'Posisi Dilamar');
        $linkProfile = htmlspecialchars($data->link_complete_profile ?? ('https://apps.intilab.com/candidate-profile?id=' . ($data->id ?? '')));

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Permintaan Kelengkapan Data Diri - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                <tr>
                    <td bgcolor='#1e293b' style='padding: 24px 32px; text-align: left;'>
                        <div style='color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>PT INTI SURYA LABORATORIUM</div>
                        <div style='color: #94a3b8; font-size: 13px; margin-top: 2px;'>Divisi HRD &amp; Talent Acquisition</div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        <p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$namaLengkap}</strong>,</p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Sehubungan dengan proses rekrutmen posisi <strong>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>, kami memohon kesediaan Bapak/Ibu untuk <strong>melengkapi data diri</strong> serta mengunggah berkas pendukung yang dibutuhkan melalui tautan di bawah ini:
                        </p>

                        <!-- Instruction Checklist Box -->
                        <div style='background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Data Yang Perlu Dilengkapi:
                            </div>
                            <ul style='margin: 0; padding-left: 20px; font-size: 14px; color: #334155; line-height: 1.8;'>
                                <li><strong>Data Diri Lengkap:</strong> NIK, No. KK, NPWP, BPJS, Alamat KTP &amp; Domisili.</li>
                                <li><strong>Riwayat Pendidikan:</strong> Jenjang, Nama Sekolah/Universitas, Jurusan, &amp; IPK.</li>
                                <li><strong>Pengalaman Kerja:</strong> Perusahaan Terakhir, Posisi, Masa Kerja, &amp; Kontak Referensi.</li>
                                <li><strong>Dokumen Lampiran:</strong> Softcopy KTP, KK, NPWP, Ijazah, Transkrip, &amp; Sertifikat.</li>
                            </ul>
                        </div>

                        <!-- CTA Button -->
                        <div style='text-align: center; margin: 28px 0;'>
                            <a href='{$linkProfile}' target='_blank' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; font-size: 14px; font-weight: 700; border-radius: 6px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);'>
                                Lengkapi Data Diri
                            </a>
                        </div>
                        <p style='font-size: 12px; color: #64748b; text-align: center; margin-bottom: 24px;'>
                            Atau akses tautan berikut melalui peramban (browser) Anda:<br>
                            <a href='{$linkProfile}' target='_blank' style='color: #2563eb; word-break: break-all;'>{$linkProfile}</a>
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Kelengkapan data ini diperlukan untuk pembaruan data rekrutmen dan mendukung kelancaran proses selanjutnya. <strong style='color: #dc2626;'>Harap diperhatikan bahwa apabila data diri belum dilengkapi, maka proses rekrutmen tidak dapat dilanjutkan ke tahap selanjutnya.</strong>
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Demikian informasi ini kami sampaikan. Apabila ada pertanyaan, silakan menghubungi tim HRD kami. Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor='#f8fafc' style='padding: 18px 32px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <strong style='color: #334155;'>Salam,</strong><br>
                        <span style='font-weight: 600; color: #0f172a;'>Tim Recruitment &amp; Talent Acquisition</span><br>
                        PT Inti Surya Laboratorium
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
