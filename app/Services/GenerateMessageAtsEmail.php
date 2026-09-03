<?php

namespace App\Services;

use App\Services\OfferingSalaryEmail;
use App\Services\RecruitmentPictureService;

class GenerateMessageAtsEmail
{
    private static function portalBaseUrl(): string
    {
        return rtrim((string) env('PORTALV4', ''), '/');
    }

    private static function directorDecisionButtons($recruitment): object
    {
        $portalUrl = self::portalBaseUrl();
        $encodedToken = $recruitment->token_approval;

        return (object) [
            'approve' => $portalUrl ? "{$portalUrl}/public/recruitment/decision/{$encodedToken}?decision=approve" : '',
            'reject'  => $portalUrl ? "{$portalUrl}/public/recruitment/decision/{$encodedToken}?decision=reject" : '',
            'keep'    => $portalUrl ? "{$portalUrl}/public/recruitment/decision/{$encodedToken}?decision=keep" : '',
        ];
    }

    public static function buildCandidateOfferingButtons($recruitment, $token = null): object
    {
        $portalUrl = self::portalBaseUrl();
        $encodedToken = $token ?? $recruitment->token;

        return (object) [
            'approve' => $portalUrl ? "{$portalUrl}/public/recruitment/candidate-offering/{$encodedToken}?decision=approve" : '',
            'reject'  => $portalUrl ? "{$portalUrl}/public/recruitment/candidate-offering/{$encodedToken}?decision=reject" : '',
        ];
    }

    private static function candidateOfferingActionButtonsHtml($btn): string
    {
        if (!$btn || (empty($btn->approve) && empty($btn->reject))) {
            return '';
        }

        return view('TemplateEmail.hrd.partials.action-buttons-salary-offer', [
            'btn' => $btn,
            'mark' => 'Candidate Offering',
        ])->render();
    }

    public static function bodyEmailCandidateOfferingSalary($data, $btn = null, object $letterData = null): string
    {
        if ($data == null) {
            return '';
        }

        if ($btn === null && !empty($data->token)) {
            $btn = self::buildCandidateOfferingButtons($data);
        }

        $letter = $letterData ?? (object) [];
        $actionButtonsHtml = self::candidateOfferingActionButtonsHtml($btn);

        $posisi = htmlspecialchars(HrdEmailViewData::getNamaJabatan($data));
        $offerAmount = $letter->gaji_pokok
            ?? optional($data->sallaryOffer)->sallary_offer_hrd
            ?? $data->sallary_offer_hrd
            ?? $data->ekspetasi_gaji
            ?? null;
        $offerFormatted = htmlspecialchars(HrdEmailViewData::formatRupiah($offerAmount));
        $deductionRowsHtml = $letterData ? self::buildSalaryDeductionEmailRows($letterData) : '';
        $pencadanganNoteHtml = $letterData ? self::buildPencadanganNoteEmail($letterData) : '';
        $greeting = self::candidateGreetingHtml($data, $data->nama_lengkap ?? 'Kandidat');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Penawaran Gaji - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 36px 0; color: #1e293b; -webkit-font-smoothing: antialiased;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='620' style='max-width: 620px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.06); margin: 0 auto;'>
                " . self::emailHeaderHtml() . "

                <tr>
                    <td style='padding: 36px 36px 28px 36px;'>
                        <!-- Header Badge -->
                        <div style='display: inline-block; background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; padding: 5px 14px; border-radius: 20px; margin-bottom: 20px;'>
                            Job Offer &amp; Compensation Details
                        </div>

                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.65; color: #334155; margin-top: 16px; margin-bottom: 14px;'>
                            Terima kasih atas partisipasi dan antusiasme Anda selama mengikuti tahapan seleksi rekrutmen untuk posisi <strong>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>.
                        </p>

                        <p style='font-size: 14px; line-height: 1.65; color: #334155; margin-bottom: 20px;'>
                            Berdasarkan hasil evaluasi kualifikasi dan wawancara, kami dengan senang hati menyampaikan rincian penawaran gaji sebagai berikut. Mohon dicatat bahwa <strong>penawaran ini merupakan klarifikasi awal hak gaji</strong> sebelum penerbitan Surat Keputusan Penerimaan Resmi (Hiring Letter).
                        </p>

                        <!-- Compensation Card -->
                        <div style='background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; margin: 24px 0 18px 0;'>
                            <div style='background-color: #0f2942; padding: 12px 18px; color: #ffffff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;'>
                                Rincian Penawaran Gaji
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 12px 18px; color: #64748b; font-weight: 600; width: 180px;'>Posisi / Jabatan</td>
                                    <td style='padding: 12px 18px; color: #0f172a; font-weight: 700;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 18px; color: #64748b; font-weight: 600; border-top: 1px solid #e2e8f0;'>Gaji Pokok (Gross)</td>
                                    <td style='padding: 12px 18px; color: #0284c7; font-weight: 800; font-size: 16px; border-top: 1px solid #e2e8f0;'>
                                        {$offerFormatted} <span style='font-size: 12px; font-weight: 500; color: #64748b;'>/ bulan</span>
                                    </td>
                                </tr>
                                {$deductionRowsHtml}
                            </table>
                        </div>

                        {$pencadanganNoteHtml}

                        <!-- PDF Attachment Box -->
                        <div style='background-color: #f0f9ff; border: 1px dashed #0284c7; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #0369a1; line-height: 1.6;'>
                            <strong>Dokumen Lampiran:</strong> Rincian resmi Surat Penawaran Gaji telah dilampirkan dalam format <strong>PDF (Surat Penawaran Gaji.pdf)</strong> pada email ini.
                        </div>

                        <p style='font-size: 14px; line-height: 1.65; color: #334155; margin-top: 20px; margin-bottom: 8px;'>
                            Silakan memberikan konfirmasi keputusan Anda mengenai penawaran gaji ini melalui tombol di bawah:
                        </p>

                        {$actionButtonsHtml}

                        <p style='font-size: 13px; line-height: 1.6; color: #64748b; margin-top: 24px; margin-bottom: 0;'>
                            Apabila Anda memerlukan penjelasan lebih lanjut mengenai rincian gaji ini, Anda dapat menghubungi Tim HRD kami melalui balasan email ini.
                        </p>
                    </td>
                </tr>

                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Pemberitahuan ke Finance saat kandidat menyetujui penawaran gaji HRD.
     *
     * @param object $data { nama_kandidat, posisi, no_request, penawaran_gaji, approved_at }
     */
    public static function bodyEmailFinanceCandidateSalaryApproved($data): string
    {
        $namaKandidat = htmlspecialchars($data->nama_kandidat ?? 'Kandidat');
        $posisi = htmlspecialchars($data->posisi ?? '-');
        $noRequest = htmlspecialchars($data->no_request ?? '-');
        $penawaranGaji = htmlspecialchars($data->penawaran_gaji ?? '-');
        $approvedAt = htmlspecialchars($data->approved_at ?? '-');
        $greeting = self::internalGreetingHtml('Tim Finance');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Pemberitahuan Persetujuan Penawaran Gaji Kandidat - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                " . self::emailHeaderHtml() . "

                <tr>
                    <td style='padding: 0;'>
                        <div style='background-color: #dcfce7; border-left: 4px solid #16a34a; padding: 14px 32px;'>
                            <span style='font-size: 13px; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px;'>&#10003; Kandidat Menyetujui Penawaran Gaji</span>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style='padding: 32px;'>
                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Melalui email ini kami informasikan bahwa kandidat berikut telah <strong>menyetujui penawaran gaji</strong> yang sebelumnya ditawarkan oleh HRD melalui surat penawaran gaji.
                        </p>

                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Ringkasan Kandidat
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600; width: 150px;'>Nama Kandidat</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 700;'>{$namaKandidat}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>Posisi</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 700;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>No. Request</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 600;'>{$noRequest}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>Penawaran Gaji HRD</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 700;'>{$penawaranGaji}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 4px 0; color: #475569; font-weight: 600;'>Waktu Persetujuan</td>
                                    <td style='padding: 4px 0; color: #0f172a; font-weight: 600;'>{$approvedAt}</td>
                                </tr>
                            </table>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Email ini bersifat pemberitahuan agar Divisi Finance dapat mengetahui bahwa kandidat telah menerima penawaran gaji yang diajukan HRD. Mohon ditindaklanjuti sesuai prosedur rekrutmen yang berlaku.
                        </p>
                    </td>
                </tr>

                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Email notifikasi ke kandidat saat approve — lampiran PDF Hiring Letter.
     *
     * @param object $data Kandidat (NewRecruitment atau object serupa)
     * @param object|null $letterData Payload surat (gaji, tanggal mulai, dll.)
     */
    public static function bodyEmailCandidateHiringLetter($data, object $letterData = null): string
    {
        if ($data == null) {
            return '';
        }

        $letter = $letterData ?? $data;
        $posisi = htmlspecialchars(HrdEmailViewData::getNamaJabatan($data));
        $gajiFormatted = htmlspecialchars(HrdEmailViewData::formatRupiah($letter->gaji_pokok ?? 0));
        $tglMulaiKerja = htmlspecialchars($letter->tanggal_mulai_kerja ?? '-');
        $deductionRowsHtml = $letterData ? self::buildSalaryDeductionEmailRows($letterData) : '';
        $pencadanganNoteHtml = $letterData ? self::buildPencadanganNoteEmail($letterData) : '';
        $greeting = self::candidateGreetingHtml($data, $data->nama_lengkap ?? 'Kandidat');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Surat Keputusan Penerimaan - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 36px 0; color: #1e293b; -webkit-font-smoothing: antialiased;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='620' style='max-width: 620px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.06); margin: 0 auto;'>
                " . self::emailHeaderHtml() . "

                <tr>
                    <td style='padding: 36px 36px 28px 36px;'>
                        <!-- Header Badge -->
                        <div style='display: inline-block; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; padding: 5px 14px; border-radius: 20px; margin-bottom: 20px;'>
                            &#10003; Official Hiring Decision
                        </div>

                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.65; color: #334155; margin-top: 16px; margin-bottom: 14px;'>
                            Selamat! Berdasarkan seluruh tahapan evaluasi rekrutmen, kami dengan bangga menyampaikan bahwa Anda <strong>diterima bergabung</strong> di <strong>PT Inti Surya Laboratorium</strong> untuk posisi <strong>{$posisi}</strong>.
                        </p>

                        <!-- Decision Summary Card -->
                        <div style='background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; margin: 24px 0 18px 0;'>
                            <div style='background-color: #166534; padding: 12px 18px; color: #ffffff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;'>
                                Ringkasan Keputusan Penerimaan
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 12px 18px; color: #64748b; font-weight: 600; width: 180px;'>Posisi / Jabatan</td>
                                    <td style='padding: 12px 18px; color: #0f172a; font-weight: 700;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 18px; color: #64748b; font-weight: 600; border-top: 1px solid #e2e8f0;'>Gaji Pokok (Gross)</td>
                                    <td style='padding: 12px 18px; color: #15803d; font-weight: 800; font-size: 16px; border-top: 1px solid #e2e8f0;'>
                                        {$gajiFormatted} <span style='font-size: 12px; font-weight: 500; color: #64748b;'>/ bulan</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 18px; color: #64748b; font-weight: 600; border-top: 1px solid #e2e8f0;'>Tanggal Mulai Bekerja</td>
                                    <td style='padding: 12px 18px; color: #0284c7; font-weight: 700; border-top: 1px solid #e2e8f0;'>{$tglMulaiKerja}</td>
                                </tr>
                                {$deductionRowsHtml}
                            </table>
                        </div>

                        {$pencadanganNoteHtml}

                        <!-- PDF Attachment Box -->
                        <div style='background-color: #f0fdf4; border: 1px dashed #16a34a; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 13px; color: #166534; line-height: 1.6;'>
                            <strong>Dokumen Resmi:</strong> Surat Keputusan Penerimaan Kerja (Hiring Letter) resmi telah dilampirkan dalam format <strong>PDF (Hiring_Letter.pdf)</strong> pada email ini.
                        </div>

                        <p style='font-size: 14px; line-height: 1.65; color: #334155; margin-top: 20px; margin-bottom: 0;'>
                            Mohon unduh, pelajari dokumen lampiran tersebut, dan berikan konfirmasi penerimaan Anda paling lambat <strong>1 x 24 jam</strong> sejak email ini diterima.
                        </p>
                    </td>
                </tr>

                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Kirim email Hiring Letter ke kandidat (body ATS + lampiran PDF).
     *
     * @param \App\Models\NewRecruitment|object $applicant
     */
    public static function sendCandidateHiringLetterEmail($applicant, object $dataObj, string $sender = 'HRD'): bool
    {
        if (empty($applicant->email)) {
            self::sendCandidateHiringLetterWhatsapp($applicant, $dataObj);
            return false;
        }

        $subject = 'Surat Keputusan Penerimaan - PT Inti Surya Laboratorium';
        $bodyEmail = self::bodyEmailCandidateHiringLetter($applicant, $dataObj);
        $pdfPath = self::generateHiringLetterPdfPath($dataObj);

        try {
            $emailQuery = SendEmail::where('to', $applicant->email)
                ->where('subject', $subject)
                ->where('body', $bodyEmail)
                ->where('karyawan', $sender);

            if (!empty($pdfPath) && file_exists($pdfPath)) {
                $emailQuery->where('attachment', [$pdfPath]);
            }

            $emailQuery->noReply('PT Inti Surya Laboratorium')->replyToAtsHrd()->send();

            self::sendCandidateHiringLetterWhatsapp($applicant, $dataObj);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('Candidate hiring letter email failed', [
                'recruitment_id' => $applicant->id ?? null,
                'message'        => $e->getMessage(),
            ]);

            self::sendCandidateHiringLetterWhatsapp($applicant, $dataObj);

            return false;
        } finally {
            if (!empty($pdfPath) && file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    /**
     * Kirim email Salary Offering Letter ke kandidat (body ATS + lampiran PDF + tombol keputusan).
     *
     * @param \App\Models\NewRecruitment|object $applicant
     */
    public static function sendCandidateOfferingSalaryEmail($applicant, object $dataObj, string $sender = 'HRD', $btn = null): bool
    {
        if (empty($applicant->email)) {
            self::sendCandidateOfferingSalaryWhatsapp($applicant, $dataObj);
            return false;
        }

        $subject = 'Penawaran Gaji - PT Inti Surya Laboratorium';
        $btn = $btn ?? self::buildCandidateOfferingButtons($applicant);
        $bodyEmail = self::bodyEmailCandidateOfferingSalary($applicant, $btn, $dataObj);
        $pdfPath = self::generateSalaryOfferingLetterPdfPath($dataObj);

        try {
            $emailQuery = SendEmail::where('to', $applicant->email)
                ->where('subject', $subject)
                ->where('body', $bodyEmail)
                ->where('karyawan', $sender);

            if (!empty($pdfPath) && file_exists($pdfPath)) {
                $emailQuery->where('attachment', [$pdfPath]);
            }

            $emailQuery->noReply('PT Inti Surya Laboratorium')->replyToAtsHrd()->send();

            self::sendCandidateOfferingSalaryWhatsapp($applicant, $dataObj);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('Candidate salary offering email failed', [
                'recruitment_id' => $applicant->id ?? null,
                'message'        => $e->getMessage(),
            ]);

            self::sendCandidateOfferingSalaryWhatsapp($applicant, $dataObj);

            throw $e;
        } finally {
            if (!empty($pdfPath) && file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    public static function sendCandidateOfferingSalaryWhatsapp($applicant, object $dataObj): bool
    {
        $whatsappData = (object) array_merge((array) $dataObj, [
            'nama_lengkap'    => $applicant->nama_lengkap ?? ($dataObj->nama_lengkap ?? 'Kandidat'),
            'email'           => $applicant->email ?? null,
            'jenis_kelamin'   => $applicant->jenis_kelamin ?? ($dataObj->jenis_kelamin ?? null),
            'posisi_di_lamar' => $dataObj->posisi_di_lamar ?? HrdEmailViewData::getNamaJabatan($applicant),
            'nama_jabatan'    => $dataObj->nama_jabatan ?? HrdEmailViewData::getNamaJabatan($applicant),
        ]);

        $message = (new GenerateMessageAtsWhatsapp($whatsappData))->SalaryOfferingLetter();

        return self::sendCandidateWhatsappMessage($applicant, $message, 'salary offering letter');
    }

    public static function sendCandidateHiringLetterWhatsapp($applicant, object $dataObj): bool
    {
        $whatsappData = (object) array_merge((array) $dataObj, [
            'nama_lengkap'    => $applicant->nama_lengkap ?? ($dataObj->nama_lengkap ?? 'Kandidat'),
            'email'           => $applicant->email ?? null,
            'jenis_kelamin'   => $applicant->jenis_kelamin ?? ($dataObj->jenis_kelamin ?? null),
            'posisi_di_lamar' => $dataObj->posisi_di_lamar ?? HrdEmailViewData::getNamaJabatan($applicant),
            'nama_jabatan'    => $dataObj->nama_jabatan ?? HrdEmailViewData::getNamaJabatan($applicant),
        ]);

        $message = (new GenerateMessageAtsWhatsapp($whatsappData))->HiringLetter();

        return self::sendCandidateWhatsappMessage($applicant, $message, 'hiring letter');
    }

    private static function resolveCandidatePhone($applicant): ?string
    {
        if (is_object($applicant) && method_exists($applicant, 'loadMissing')) {
            $applicant->loadMissing('candidateProfile');
        }

        $profile = is_object($applicant) ? ($applicant->candidateProfile ?? null) : null;
        $candidates = [
            is_object($applicant) ? ($applicant->no_telepon ?? null) : null,
            is_object($applicant) ? ($applicant->no_hp ?? null) : null,
            is_object($applicant) ? ($applicant->no_whatsapp ?? null) : null,
            is_object($profile) ? ($profile->no_whatsapp ?? null) : null,
            is_object($profile) ? ($profile->no_telepon ?? null) : null,
        ];

        foreach ($candidates as $phone) {
            $phone = trim((string) $phone);
            if ($phone !== '') {
                return $phone;
            }
        }

        return null;
    }

    private static function sendCandidateWhatsappMessage($applicant, string $message, string $context): bool
    {
        $phone = self::resolveCandidatePhone($applicant);
        if (!$phone) {
            return false;
        }

        try {
            (new SendWhatsapp(trim($phone), $message))->send();
            return true;
        } catch (\Throwable $e) {
            \Log::warning('Candidate WhatsApp failed (' . $context . ')', [
                'recruitment_id' => is_object($applicant) ? ($applicant->id ?? null) : null,
                'phone'          => $phone,
                'message'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param \App\Models\NewRecruitment|object $applicant
     */
    public static function buildOfferingLetterPayload($applicant, array $overrides = []): object
    {
        if (method_exists($applicant, 'loadMissing')) {
            $applicant->loadMissing([
                'sallaryOffer',
                'candidateDataOffer',
                'personalRequest.masterJabatan',
                'personnelRequest.masterJabatan',
            ]);
        }

        $offer = $applicant->sallaryOffer ?? null;
        $cOffer = $applicant->candidateDataOffer ?? null;
        $posisiName = HrdEmailViewData::getNamaJabatan($applicant);

        $resolveAmount = function (string $key, array $fallbacks) use ($overrides) {
            if (array_key_exists($key, $overrides)) {
                return $overrides[$key];
            }

            foreach ($fallbacks as $value) {
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }

            return 0;
        };

        if (array_key_exists('tanggal_mulai_kerja', $overrides)) {
            $tanggalMulaiKerja = $overrides['tanggal_mulai_kerja'];
        } elseif (!empty($cOffer->tanggal_mulai_kerja)) {
            try {
                $tanggalMulaiKerja = \Carbon\Carbon::parse($cOffer->tanggal_mulai_kerja)
                    ->locale('id')
                    ->translatedFormat('d F Y');
            } catch (\Throwable $e) {
                $tanggalMulaiKerja = '-';
            }
        } else {
            $tanggalMulaiKerja = '-';
        }

        return (object) [
            'nama_lengkap'        => $applicant->nama_lengkap,
            'alamat'              => $applicant->alamat_domisili ?: ($applicant->alamat_ktp ?: '-'),
            'no_telepon'          => $applicant->no_telepon ?: ($applicant->no_hp ?: '-'),
            'nama_jabatan'        => $posisiName,
            'posisi_di_lamar'     => $posisiName,
            'gaji_pokok'          => $resolveAmount('gaji_pokok', [
                optional($cOffer)->gaji_pokok,
                optional($offer)->final_sallary,
                optional($offer)->sallary_offer_hrd,
                $applicant->ekspetasi_gaji ?? null,
            ]),
            'potongan_bpjs_kes'   => $resolveAmount('potongan_bpjs_kes', [optional($cOffer)->potongan_bpjs_kes]),
            'potongan_bpjs_tk'    => $resolveAmount('potongan_bpjs_tk', [optional($cOffer)->potongan_bpjs_tk]),
            'pot_pph21'           => $resolveAmount('pot_pph21', [optional($cOffer)->pot_pph21]),
            'pencadangan_upah'    => $resolveAmount('pencadangan_upah', [optional($cOffer)->pencadangan_upah]),
            'tanggal_mulai_kerja' => $tanggalMulaiKerja,
            'hari_kerja'          => $overrides['hari_kerja'] ?? 'Senin s.d Jumat',
            'jenis_kelamin'       => $applicant->jenis_kelamin ?? null,
        ];
    }

    public static function generateSalaryOfferingLetterPdfPath(object $dataObj): ?string
    {
        return self::generateLetterPdfPath($dataObj, 'salary_offer');
    }

    public static function generateHiringLetterPdfPath(object $dataObj): ?string
    {
        return self::generateLetterPdfPath($dataObj, 'hiring');
    }

    /** @deprecated Use generateSalaryOfferingLetterPdfPath or generateHiringLetterPdfPath */
    public static function generateOfferingLetterPdfPath(object $dataObj): ?string
    {
        return self::generateHiringLetterPdfPath($dataObj);
    }

    public static function generateLetterPdfPath(object $dataObj, string $type = 'hiring'): ?string
    {
        $bodyEmail = $type === 'salary_offer'
            ? self::bodyEmailSalaryOfferingLetter($dataObj)
            : self::bodyEmailHiringLetter($dataObj);

        $prefix = $type === 'salary_offer' ? 'Surat_Penawaran_Gaji_' : 'Hiring_Letter_';
        $safeName = preg_replace('/[^A-Za-z0-9_]/', '_', $dataObj->nama_lengkap ?? 'Kandidat');
        $pdfPath = sys_get_temp_dir() . '/' . $prefix . $safeName . '_' . time() . '.pdf';

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);
            $mpdf->WriteHTML($bodyEmail);
            $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

            return file_exists($pdfPath) ? $pdfPath : null;
        } catch (\Throwable $e) {
            \Log::warning('Letter PDF generation failed', [
                'type'      => $type,
                'candidate' => $dataObj->nama_lengkap ?? null,
                'message'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function buildSalaryDecisionButtons($recruitment, $token = null): object
    {
        $portalUrl = self::portalBaseUrl();
        $encodedToken = $token ?? $recruitment->token_approval ?? '';

        return (object) [
            'approve'   => $portalUrl ? "{$portalUrl}/public/recruitment/salary-decision/{$encodedToken}?decision=approve" : '',
            'reject'    => $portalUrl ? "{$portalUrl}/public/recruitment/salary-decision/{$encodedToken}?decision=reject" : '',
            'negotiate' => $portalUrl ? "{$portalUrl}/public/recruitment/salary-decision/{$encodedToken}?decision=negotiate" : '',
        ];
    }

    private static function recruitmentPhotoForEmail($recruitment): string
    {
        $photoUrl = app(RecruitmentPictureService::class)->toPathUrl($recruitment->picture ?? null);

        return $photoUrl ?: '';
    }

    private static function resolveAiMatchingReason($recruitment): ?string
    {
        if (!empty($recruitment->ai_matching_reason)) {
            return trim((string) $recruitment->ai_matching_reason);
        }

        if (!empty($recruitment->ai_matching_response)) {
            $parsed = json_decode($recruitment->ai_matching_response, true);
            if (is_array($parsed) && !empty($parsed['reason'])) {
                return trim((string) $parsed['reason']);
            }
        }

        return null;
    }

    private static function letterIssueLocation(): string
    {
        return 'Tangerang Selatan';
    }

    private static function positiveAmount($value): float
    {
        return max(0, (float) ($value ?? 0));
    }

    private static function formatLetterRupiah($value): string
    {
        return number_format(self::positiveAmount($value), 0, ',', '.');
    }

    private static function salaryDeductionItems(object $data): array
    {
        $items = [];
        $map = [
            'Potongan BPJS Kesehatan' => 'potongan_bpjs_kes',
            'Potongan BPJS Ketenagakerjaan' => 'potongan_bpjs_tk',
            'Potongan PPh 21' => 'pot_pph21',
            'Pencadangan Upah' => 'pencadangan_upah',
        ];

        foreach ($map as $label => $key) {
            $amount = self::positiveAmount($data->{$key} ?? 0);
            if ($amount > 0) {
                $items[] = ['label' => $label, 'amount' => $amount];
            }
        }

        return $items;
    }

    private static function buildSalaryDeductionPdfRows(object $data): string
    {
        $html = '';
        foreach (self::salaryDeductionItems($data) as $item) {
            $label = htmlspecialchars($item['label']);
            $formatted = self::formatLetterRupiah($item['amount']);
            $html .= "
                    <tr>
                        <td class='label-col'>{$label}</td>
                        <td class='value-col'>Rp {$formatted}</td>
                    </tr>";
        }

        return $html;
    }

    private static function buildSalaryDeductionEmailRows(object $data): string
    {
        $html = '';
        foreach (self::salaryDeductionItems($data) as $item) {
            $label = htmlspecialchars($item['label']);
            $formatted = htmlspecialchars(HrdEmailViewData::formatRupiah($item['amount']));
            $html .= "
                                <tr>
                                    <td style='padding: 8px 12px; color: #64748b; font-weight: 600; font-size: 13px; border-top: 1px solid #f1f5f9; width: 200px;'>{$label}</td>
                                    <td style='padding: 8px 12px; color: #dc2626; font-weight: 700; font-size: 13px; border-top: 1px solid #f1f5f9;'>- {$formatted}</td>
                                </tr>";
        }

        return $html;
    }

    private static function buildPencadanganNotePdf(object $data): string
    {
        if (self::positiveAmount($data->pencadangan_upah ?? 0) <= 0) {
            return '';
        }

        return "
                <div class='note-box'>
                    <strong>Catatan Pencadangan Upah:</strong> Pencadangan upah akan dikembalikan secara bertahap setelah masa Training selesai.
                </div>";
    }

    private static function buildPencadanganNoteEmail(object $data): string
    {
        if (self::positiveAmount($data->pencadangan_upah ?? 0) <= 0) {
            return '';
        }

        return "
                        <div style='background-color: #fffbebf5; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 14px 18px; margin: 18px 0; font-size: 13px; color: #92400e; line-height: 1.6;'>
                            <strong style='color: #b45309;'>Catatan Pencadangan Upah:</strong> Pencadangan upah akan dikembalikan secara bertahap setelah masa Training selesai.
                        </div>";
    }

    public static function resolveSalutation($data)
    {
        $gender = strtolower(trim((string) ($data->jenis_kelamin ?? $data->gender ?? '')));

        if (in_array($gender, ['female', 'perempuan', 'f', 'wanita'], true)) {
            return 'Saudari';
        }

        if (in_array($gender, ['male', 'laki-laki', 'laki laki', 'm', 'pria'], true)) {
            return 'Saudara';
        }

        return 'Saudara/i';
    }

    private static function candidateGreetingHtml($data, $name)
    {
        $salutation = self::resolveSalutation($data);
        $namaLengkap = htmlspecialchars($name);

        return "<p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. {$salutation} <strong>{$namaLengkap}</strong>,</p>";
    }

    private static function internalGreetingHtml($name)
    {
        $nama = htmlspecialchars($name);

        return "<p style='font-size: 14px; margin-top: 0; color: #0f172a;'>Yth. Bapak/Ibu <strong>{$nama}</strong>,</p>";
    }

    private static function emailHeaderHtml()
    {
        return "
                <tr>
                    <td style='background: linear-gradient(135deg, #0f2942 0%, #1e3a5f 100%); padding: 28px 36px; border-bottom: 3px solid #2563eb; text-align: left;'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                            <tr>
                                <td>
                                    <div style='color: #ffffff; font-size: 19px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>PT INTI SURYA LABORATORIUM</div>
                                    <div style='color: #94a3b8; font-size: 12px; font-weight: 600; margin-top: 4px; letter-spacing: 0.5px; text-transform: uppercase;'>Human Resources Division</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>";
    }

    private static function emailFooterHtml()
    {
        return "
                <tr>
                    <td style='background-color: #f8fafc; padding: 24px 36px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: left;'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                            <tr>
                                <td>
                                    <strong style='color: #1e293b; font-size: 13px;'>Hormat kami,</strong><br>
                                    <span style='font-weight: 700; color: #0f2942; font-size: 13px;'>Tim Human Resources Division</span><br>
                                    <span style='color: #475569;'>PT Inti Surya Laboratorium</span>
                                    <div style='margin-top: 14px; font-size: 11px; color: #94a3b8; line-height: 1.5; border-top: 1px solid #e2e8f0; padding-top: 12px;'>
                                        Ruko Icon Business Park Blok O No. 5-6, BSD City, Kec. Cisauk, Tangerang Selatan, Banten 15345<br>
                                        Pesan ini dikirimkan secara otomatis oleh Sistem ATS PT Inti Surya Laboratorium. Mohon menjaga kerahasiaan isi dokumen penawaran ini.
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>";
    }

    /**
     * Concise, Neutral & Professional Email Template for Approved Candidate (HRD Interview)
     * 
     * @param object $data
     * @return string
     */
    public static function bodyEmailApproveKandidat($data)
    {
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

        $greeting = self::candidateGreetingHtml($data, $data->nama_lengkap ?? 'Kandidat');

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
                " . self::emailHeaderHtml() . "

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        {$greeting}

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
                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Surat Penawaran Gaji (Finance) — bukan surat keputusan penerimaan kerja.
     *
     * @param object $data
     * @return string
     */
    public static function bodyEmailSalaryOfferingLetter($data)
    {
        $namaLengkap = htmlspecialchars($data->nama_lengkap ?? 'Kandidat');
        $salutation = htmlspecialchars(self::resolveSalutation($data));
        $posisi = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? 'Posisi Dilamar');
        $alamat = nl2br(htmlspecialchars($data->alamat ?? '-'));
        $noTelepon = htmlspecialchars($data->no_telepon ?? '-');
        $gajiPokok = number_format((float) ($data->gaji_pokok ?? 0), 0, ',', '.');
        $tanggalSurat = htmlspecialchars(
            $data->tanggal_surat ?? \Carbon\Carbon::now()->locale('id')->translatedFormat('d F Y')
        );
        $lokasiSurat = htmlspecialchars(self::letterIssueLocation());
        $deductionRowsHtml = self::buildSalaryDeductionPdfRows($data);
        $pencadanganNoteHtml = self::buildPencadanganNotePdf($data);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Surat Penawaran Gaji - PT Inti Surya Laboratorium</title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    color: #1e293b;
                    margin: 0;
                    padding: 0;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .container {
                    width: 100%;
                    max-width: 680px;
                    margin: 0 auto;
                    padding: 30px 40px;
                    background-color: #ffffff;
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    border-bottom: 2px solid #0f2942;
                    padding-bottom: 12px;
                    margin-bottom: 20px;
                }
                .company-name {
                    font-size: 20px;
                    font-weight: 800;
                    color: #0f2942;
                    letter-spacing: 0.8px;
                    text-transform: uppercase;
                }
                .company-sub {
                    font-size: 11px;
                    color: #64748b;
                    margin-top: 3px;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .document-title {
                    text-align: center;
                    margin: 25px 0 20px 0;
                }
                .title-main {
                    font-size: 16px;
                    font-weight: 700;
                    color: #0f2942;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .title-sub {
                    font-size: 11px;
                    color: #64748b;
                    font-style: italic;
                    margin-top: 2px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    font-size: 13px;
                }
                .info-table td {
                    vertical-align: top;
                }
                .details-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 18px 0;
                    background-color: #f8fafc;
                    border: 1px solid #cbd5e1;
                    border-radius: 6px;
                    overflow: hidden;
                }
                .details-table td {
                    padding: 11px 16px;
                    font-size: 13px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .details-table tr:last-child td {
                    border-bottom: none;
                }
                .label-col {
                    width: 40%;
                    color: #475569;
                    font-weight: 600;
                }
                .value-col {
                    color: #0f172a;
                    font-weight: 700;
                }
                .note-box {
                    background-color: #fef3c7;
                    border-left: 4px solid #d97706;
                    border-radius: 4px;
                    padding: 12px 16px;
                    margin: 20px 0;
                    font-size: 12px;
                    color: #92400e;
                    line-height: 1.6;
                }
                .signature-section {
                    margin-top: 35px;
                    width: 100%;
                    border-collapse: collapse;
                }
                .signature-cell {
                    vertical-align: top;
                    font-size: 13px;
                    color: #1e293b;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <!-- Header Kop Surat -->
                <table class='header-table'>
                    <tr>
                        <td style='vertical-align: middle;'>
                            <div class='company-name'>PT INTI SURYA LABORATORIUM</div>
                            <div class='company-sub'>HUMAN RESOURCES DEPARTMENT</div>
                        </td>
                    </tr>
                </table>

                <!-- Title -->
                <div class='document-title'>
                    <div class='title-main'>SURAT PENAWARAN GAJI</div>
                    <div class='title-sub'>SALARY OFFERING LETTER</div>
                </div>

                <!-- Info Meta Table -->
                <table class='info-table'>
                    <tr>
                        <td width='58%'>
                            <span style='color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700;'>Kepada Yth.</span><br>
                            <strong style='font-size: 14px; color: #0f2942;'>{$salutation} {$namaLengkap}</strong><br>
                            <span style='color: #334155;'>{$alamat}</span><br>
                            <span style='color: #475569;'>No. Telp / HP: {$noTelepon}</span>
                        </td>
                        <td width='42%' style='text-align: right;'>
                            <span style='color: #64748b; font-size: 12px;'>{$lokasiSurat}, {$tanggalSurat}</span><br>
                            <span style='color: #64748b; font-size: 11px; display: inline-block; margin-top: 4px; padding: 2px 8px; background-color: #f1f5f9; border-radius: 4px; font-weight: 600;'>Sifat: Rahasia (Confidential)</span>
                        </td>
                    </tr>
                </table>

                <div style='margin-bottom: 16px; font-weight: 600; color: #0f2942;'>
                    Perihal: Penawaran Gaji &mdash; Posisi {$posisi}
                </div>

                <p style='margin-top: 0;'>Dengan hormat,</p>

                <p style='text-align: justify;'>
                    Sehubungan dengan hasil evaluasi kualifikasi dan tahapan wawancara yang telah Anda jalani di <strong>PT Inti Surya Laboratorium</strong>, Perusahaan bermaksud menyampaikan penawaran gaji awal untuk posisi <strong>{$posisi}</strong> dengan rincian sebagai berikut:
                </p>

                <!-- Rincian Tabel -->
                <table class='details-table'>
                    <tr>
                        <td class='label-col'>Posisi / Jabatan yang Ditawarkan</td>
                        <td class='value-col'>{$posisi}</td>
                    </tr>
                    <tr>
                        <td class='label-col'>Penawaran Gaji Pokok</td>
                        <td class='value-col' style='color: #0f2942; font-size: 14px;'>Rp {$gajiPokok} <span style='font-weight: 400; font-size: 12px; color: #64748b;'>/ bulan (gross)</span></td>
                    </tr>
                    {$deductionRowsHtml}
                </table>

                {$pencadanganNoteHtml}

                <!-- Note Disclaimer Box -->
                <div class='note-box'>
                    <strong>Catatan Penting:</strong> Dokumen ini merupakan <strong>Surat Penawaran Gaji (Salary Offering Letter)</strong> dalam rangka klarifikasi dan persetujuan awal hak gaji. Surat ini <strong>bukan merupakan Perjanjian Kerja maupun Surat Keputusan Penerimaan Kerja (Hiring Letter) resmi</strong>. Keputusan penerimaan kerja resmi akan diterbitkan setelah seluruh tahapan administrasi dan persetujuan Manajemen Direksi diselesaikan.
                </div>

                <p style='text-align: justify;'>
                    Apabila Sdr/Sdri menyetujui struktur penawaran gaji tersebut di atas, mohon untuk memberikan konfirmasi balasan. Apabila terdapat hal yang memerlukan penjelasan lebih lanjut, Anda dapat menghubungi Tim HRD Perusahaan.
                </p>

                <p style='text-align: justify;'>
                    Demikian penawaran ini kami sampaikan. Atas perhatian dan kerja sama Sdr/Sdri, kami ucapkan terima kasih.
                </p>

                <!-- Tanda Tangan Footer -->
                <table class='signature-section'>
                    <tr>
                        <td class='signature-cell' width='60%'>
                            <span style='color: #64748b; font-size: 12px;'>Hormat kami,</span><br>
                            <strong style='color: #0f2942;'>PT INTI SURYA LABORATORIUM</strong><br><br><br><br>
                            <strong style='text-decoration: underline; color: #0f2942;'>Tim HRD</strong><br>
                            <span style='color: #64748b; font-size: 11px;'>Human Resources Department</span>
                        </td>
                    </tr>
                </table>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Surat Keputusan Penerimaan / Hiring Letter (setelah Direktur approve).
     *
     * @param object $data
     * @return string
     */
    public static function bodyEmailHiringLetter($data)
    {
        $namaLengkap = htmlspecialchars($data->nama_lengkap ?? 'Kandidat');
        $salutation = htmlspecialchars(self::resolveSalutation($data));
        $posisi = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? 'Posisi Dilamar');
        $alamat = nl2br(htmlspecialchars($data->alamat ?? 'Jakarta'));
        $noTelepon = htmlspecialchars($data->no_telepon ?? '-');

        $gajiPokok = number_format((float)($data->gaji_pokok ?? 0), 0, ',', '.');

        $tglMulaiKerja = htmlspecialchars($data->tanggal_mulai_kerja ?? '-');
        $hariKerja = htmlspecialchars($data->hari_kerja ?? 'Senin s.d Jumat');
        $tanggalSurat = htmlspecialchars(
            $data->tanggal_surat ?? \Carbon\Carbon::now()->locale('id')->translatedFormat('d F Y')
        );
        $lokasiSurat = htmlspecialchars(self::letterIssueLocation());
        $deductionRowsHtml = self::buildSalaryDeductionPdfRows($data);
        $pencadanganNoteHtml = self::buildPencadanganNotePdf($data);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Surat Keputusan Penerimaan - PT Inti Surya Laboratorium</title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    color: #1e293b;
                    margin: 0;
                    padding: 0;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .container {
                    width: 100%;
                    max-width: 680px;
                    margin: 0 auto;
                    padding: 30px 40px;
                    background-color: #ffffff;
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    border-bottom: 2px solid #0f2942;
                    padding-bottom: 12px;
                    margin-bottom: 20px;
                }
                .company-name {
                    font-size: 20px;
                    font-weight: 800;
                    color: #0f2942;
                    letter-spacing: 0.8px;
                    text-transform: uppercase;
                }
                .company-sub {
                    font-size: 11px;
                    color: #64748b;
                    margin-top: 3px;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .document-title {
                    text-align: center;
                    margin: 25px 0 20px 0;
                }
                .title-main {
                    font-size: 16px;
                    font-weight: 700;
                    color: #0f2942;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .title-sub {
                    font-size: 11px;
                    color: #64748b;
                    font-style: italic;
                    margin-top: 2px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    font-size: 13px;
                }
                .info-table td {
                    vertical-align: top;
                }
                .details-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 18px 0;
                    background-color: #f8fafc;
                    border: 1px solid #cbd5e1;
                    border-radius: 6px;
                    overflow: hidden;
                }
                .details-table td {
                    padding: 11px 16px;
                    font-size: 13px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .details-table tr:last-child td {
                    border-bottom: none;
                }
                .label-col {
                    width: 40%;
                    color: #475569;
                    font-weight: 600;
                }
                .value-col {
                    color: #0f172a;
                    font-weight: 700;
                }
                .confirm-box {
                    background-color: #f0f9ff;
                    border-left: 4px solid #0284c7;
                    border-radius: 4px;
                    padding: 12px 16px;
                    margin: 20px 0;
                    font-size: 12px;
                    color: #0369a1;
                    line-height: 1.6;
                }
                .signature-section {
                    margin-top: 35px;
                    width: 100%;
                    border-collapse: collapse;
                }
                .signature-cell {
                    vertical-align: top;
                    font-size: 13px;
                    color: #1e293b;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <!-- Header Kop Surat -->
                <table class='header-table'>
                    <tr>
                        <td style='vertical-align: middle;'>
                            <div class='company-name'>PT INTI SURYA LABORATORIUM</div>
                            <div class='company-sub'>HUMAN RESOURCES DEPARTMENT</div>
                        </td>
                    </tr>
                </table>

                <!-- Title -->
                <div class='document-title'>
                    <div class='title-main'>SURAT KEPUTUSAN PENERIMAAN KERJA</div>
                    <div class='title-sub'>OFFICIAL HIRING LETTER</div>
                </div>

                <!-- Info Meta Table -->
                <table class='info-table'>
                    <tr>
                        <td width='58%'>
                            <span style='color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700;'>Kepada Yth.</span><br>
                            <strong style='font-size: 14px; color: #0f2942;'>{$salutation} {$namaLengkap}</strong><br>
                            <span style='color: #334155;'>{$alamat}</span><br>
                            <span style='color: #475569;'>No. Telp / HP: {$noTelepon}</span>
                        </td>
                        <td width='42%' style='text-align: right;'>
                            <span style='color: #64748b; font-size: 12px;'>{$lokasiSurat}, {$tanggalSurat}</span><br>
                            <span style='color: #64748b; font-size: 11px; display: inline-block; margin-top: 4px; padding: 2px 8px; background-color: #f1f5f9; border-radius: 4px; font-weight: 600;'>Sifat: Penting &amp; Rahasia</span>
                        </td>
                    </tr>
                </table>

                <div style='margin-bottom: 16px; font-weight: 600; color: #0f2942;'>
                    Perihal: Surat Keputusan Penerimaan Kerja &mdash; Posisi {$posisi}
                </div>

                <p style='margin-top: 0;'>Dengan hormat,</p>

                <p style='text-align: justify;'>
                    Berdasarkan hasil seleksi rekrutmen, <strong>PT Inti Surya Laboratorium</strong> menyatakan bahwa Sdr/Sdri <strong>diterima</strong> untuk bergabung pada posisi <strong>{$posisi}</strong>.
                </p>

                <!-- Rincian Tabel -->
                <table class='details-table'>
                    <tr>
                        <td class='label-col'>Posisi / Jabatan</td>
                        <td class='value-col'>{$posisi}</td>
                    </tr>
                    <tr>
                        <td class='label-col'>Status Kepegawaian</td>
                        <td class='value-col'>Masa Pelatihan (Training)</td>
                    </tr>
                    <tr>
                        <td class='label-col'>Gaji Pokok</td>
                        <td class='value-col' style='color: #0f2942; font-size: 14px;'>Rp {$gajiPokok} <span style='font-weight: 400; font-size: 12px; color: #64748b;'>/ bulan (gross)</span></td>
                    </tr>
                    {$deductionRowsHtml}
                    <tr>
                        <td class='label-col'>Tanggal Mulai Bekerja</td>
                        <td class='value-col' style='color: #0284c7;'>{$tglMulaiKerja}</td>
                    </tr>
                    <tr>
                        <td class='label-col'>Hari &amp; Jam Kerja</td>
                        <td class='value-col'>{$hariKerja}, 08.00 &ndash; 17.00 WIB</td>
                    </tr>
                </table>

                {$pencadanganNoteHtml}

                <!-- Confirm Box -->
                <div class='confirm-box'>
                    <strong>Ketentuan Tambahan &amp; Konfirmasi:</strong> Sdr/Sdri diharapkan memberikan konfirmasi penerimaan Surat Keputusan ini paling lambat <strong>1 x 24 jam</strong> sejak surat ini diterima. Mohon untuk membawa dokumen fisik pendukung (KTP, KK, NPWP, Ijazah &amp; Transkrip Nilai Asli) pada hari pertama kerja.
                </div>

                <p style='text-align: justify;'>
                    Selamat bergabung di <strong>PT Inti Surya Laboratorium</strong>. Kami berharap Sdr/Sdri dapat berkontribusi dan berkembang bersama Perusahaan.
                </p>

                <p style='text-align: justify;'>
                    Demikian Surat Keputusan ini kami sampaikan. Atas perhatian dan kesediaan Sdr/Sdri, kami ucapkan terima kasih.
                </p>

                <!-- Tanda Tangan Footer -->
                <table class='signature-section'>
                    <tr>
                        <td class='signature-cell' width='60%'>
                            <span style='color: #64748b; font-size: 12px;'>Hormat kami,</span><br>
                            <strong style='color: #0f2942;'>PT INTI SURYA LABORATORIUM</strong><br><br><br><br>
                            <strong style='text-decoration: underline; color: #0f2942;'>Tim HRD</strong><br>
                            <span style='color: #64748b; font-size: 11px;'>Human Resources Department</span>
                        </td>
                    </tr>
                </table>
            </div>
        </body>
        </html>
        ";
    }

    /** @deprecated Use bodyEmailHiringLetter */
    public static function bodyEmailOfferingLetter($data)
    {
        return self::bodyEmailHiringLetter($data);
    }

    /**
     * Exact User Refined Dignified Rejection Email Template
     * 
     * @param object $data
     * @return string
     */
    public static function bodyEmailRejectKandidat($data)
    {
        $posisi   = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? $data->posisi ?? 'Posisi Dilamar');
        $greeting = self::candidateGreetingHtml($data, $data->nama_lengkap ?? $data->nama_kandidat ?? 'Kandidat');

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
                " . self::emailHeaderHtml() . "

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Terima kasih atas waktu dan partisipasi Anda dalam proses rekrutmen untuk posisi <strong>{$posisi}</strong>.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Setelah melalui proses evaluasi, kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya. Keputusan ini diambil berdasarkan pertimbangan kebutuhan posisi saat ini.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Kami menghargai minat Anda untuk bergabung bersama PT Inti Surya Laboratorium dan mendoakan yang terbaik untuk perjalanan karier Anda.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                " . self::emailFooterHtml() . "
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
        $greeting      = self::internalGreetingHtml($data->nama_user ?? 'User');

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
                " . self::emailHeaderHtml() . "

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
                        {$greeting}

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
                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Professional Email Template to Candidate for Completing Personal Profile after passing HRD Interview
     * 
     * @param object $data
     * @return string
     */
    public static function bodyEmailCompleteProfileCandidate($data)
    {
        $posisi      = htmlspecialchars($data->posisi_di_lamar ?? $data->nama_jabatan ?? 'Posisi Dilamar');
        $linkProfile = htmlspecialchars($data->link_complete_profile ?? ('https://apps.intilab.com/candidate-profile?id=' . ($data->id ?? '')));
        $greeting    = self::candidateGreetingHtml($data, $data->nama_lengkap ?? 'Kandidat');

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
                " . self::emailHeaderHtml() . "

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Sehubungan dengan proses rekrutmen posisi <strong>{$posisi}</strong> di <strong>PT Inti Surya Laboratorium</strong>, kami memohon kesediaan Anda untuk <strong>melengkapi data diri</strong> serta mengunggah berkas pendukung yang dibutuhkan melalui tautan di bawah ini:
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
                            Kelengkapan data ini diperlukan untuk pembaruan data rekrutmen dan mendukung kelancaran proses selanjutnya.
                        </p>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0;'>
                            Demikian informasi ini kami sampaikan. Apabila ada pertanyaan, silakan menghubungi tim HRD kami. Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                " . self::emailFooterHtml() . "
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
                " . self::emailHeaderHtml() . "

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

                <!-- Footer -->
                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Email notifikasi hasil interview user ke HRD
     */
    static function bodyEmailHasilInterviewUser(
        $recruitment,
        $pr,
        $interview,
        $decision,
        array $assessmentAttachments = [],
        array $candidateDocumentAttachments = []
    ) {
        try {
            //code...
           
            $profile = \Illuminate\Support\Facades\DB::table('candidate_profiles')
                ->where('new_recruitment_id', $recruitment->id)->first();
                
            $educations = \Illuminate\Support\Facades\DB::table('candidate_educations')
                ->where('new_recruitment_id', $recruitment->id)
                ->where('is_active', 1)->get();
    
            $medicalInfo = \Illuminate\Support\Facades\DB::table('candidate_medical_informations')
                ->where('new_recruitment_id', $recruitment->id)
                ->first();
    
            $photoUrl = self::recruitmentPhotoForEmail($recruitment);

            $birthDate = $recruitment->tanggal_lahir ?? null;
            $shioElemen = \App\Helpers\ShioElemenHelper::resolve(
                $birthDate,
                $recruitment->shio ?? null,
                $recruitment->elemen ?? null
            );

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
                    'shio' => $shioElemen['shio'] ?? null,
                    'elemen' => $shioElemen['elemen'] ?? null,
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
                'medicalInfo' => $medicalInfo,
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

            $umur = '-';
            if (!empty($birthDate)) {
                try {
                    $umur = \Carbon\Carbon::parse($birthDate)->age;
                } catch (\Exception $e) {
                    $umur = $recruitment->umur ?? $recruitment->usia ?? '-';
                }
            } elseif (!empty($recruitment->umur ?? $recruitment->usia ?? null)) {
                $umur = $recruitment->umur ?? $recruitment->usia;
            }

            $alamat = trim((string) ($profile->alamat_domisili ?? $recruitment->alamat_domisili ?? $recruitment->alamat_ktp ?? ''));
            $kota = trim((string) ($profile->kota_domisili ?? ''));
            $provinsi = trim((string) ($profile->provinsi_domisili ?? ''));
            if ($alamat !== '' && $kota !== '' && stripos($alamat, $kota) === false) {
                $alamat .= ', ' . $kota;
            }
            if ($alamat !== '' && $provinsi !== '' && stripos($alamat, $provinsi) === false) {
                $alamat .= ', ' . $provinsi;
            }
            if ($alamat === '') {
                $alamat = '-';
            }

            $candidateInfo = (object) [
                'nama_lengkap' => $recruitment->nama_lengkap ?? '-',
                'shio' => $shioElemen['shio'] ?? '-',
                'elemen' => $shioElemen['elemen'] ?? '-',
                'nama_jabatan' => $pr->detailPosisi->nama_jabatan ?? $pr->posisi ?? '-',
                'umur' => $umur,
                'alamat' => $alamat,
            ];

            $contact = \App\Services\HrdEmailViewData::contactLine((object) [
                'no_hp' => $recruitment->no_telepon ?? null,
                'email' => $recruitment->email ?? null,
            ]);

            $hrdInterview = \Illuminate\Support\Facades\DB::table('recruitment_interviews')
                ->where('new_recruitment_id', $recruitment->id)
                ->where('stage', 'hrd')
                ->where('is_active', 1)
                ->orderBy('id', 'desc')
                ->first();

            if (empty($assessmentAttachments)) {
                $assessmentAttachments = app(GenerateAssessmentDocumentService::class)
                    ->listAttachmentLabels((int) $recruitment->id);
            }

            if (empty($candidateDocumentAttachments)) {
                $candidateDocumentAttachments = app(CandidateDocumentAttachmentService::class)
                    ->listAttachmentLabels((int) $recruitment->id);
            }
    
            return view('TemplateEmail.ats.hasil-interview-user', [
                'recruitment' => $recruitment,
                'pr' => $pr,
                'interview' => $interview,
                'hrdInterview' => $hrdInterview,
                'decision' => $decision,
                'candidateInfo' => $candidateInfo,
                'contact' => $contact,
                'photoUrl' => $photoUrl,
                'aiMatchingReason' => self::resolveAiMatchingReason($recruitment),
                'assessmentAttachments' => $assessmentAttachments,
                'candidateDocumentAttachments' => $candidateDocumentAttachments,
                'cv' => $cv,
                'btn' => self::directorDecisionButtons($recruitment),
            ])->render();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Email Template to Candidate for User Interview Schedule & Location / Link
     *
     * @param object $data
     * @return string
     */
    public static function bodyEmailUserInterviewCandidate($data)
    {
        $namaKandidat   = htmlspecialchars($data->nama_kandidat ?? 'Candidate');
        $posisi         = htmlspecialchars($data->posisi ?? '-');
        $jenisInterview = htmlspecialchars($data->jenis_interview ?? '-');
        $linkGmeet      = htmlspecialchars($data->link_gmeet ?? '');
        $ruangan        = htmlspecialchars($data->ruangan_interview ?? 'Office Room');
        $catatan        = $data->catatan ?? '';
        $tglInterview   = $data->tgl_interview ?? '-';

        $jenisTypeStr = strtolower(strip_tags($data->jenis_interview ?? 'online'));

        $detailLocationHtml = "";
        if ($jenisTypeStr === 'online') {
            $detailLocationHtml = "
            <tr>
                <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Link Google Meet</td>
                <td style='padding: 5px 0; color: #0284c7; font-weight: 600;'><a href='{$linkGmeet}' target='_blank' style='color: #2563eb;'>{$linkGmeet}</a></td>
            </tr>";
        } else {
            $detailLocationHtml = "
            <tr>
                <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Ruangan Interview</td>
                <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$ruangan}</td>
            </tr>";
        }

        $catatanHtml = "";
        if (!empty($catatan)) {
            $catatanHtml = "
            <div style='margin-top: 20px;'>
                <strong style='font-size: 14px; color: #475569;'>Catatan / Pesan Tambahan:</strong>
                <div style='font-size: 14px; line-height: 1.6; color: #334155; background-color: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 4px; margin-top: 6px;'>
                    {$catatan}
                </div>
            </div>";
        }

        $greeting = self::candidateGreetingHtml($data, $data->nama_kandidat ?? $data->nama_lengkap ?? 'Candidate');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Undangan User Interview - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                " . self::emailHeaderHtml() . "

                <!-- Status Banner -->
                <tr>
                    <td style='padding: 0;'>
                        <div style='background-color: #e0f2fe; border-left: 4px solid #0284c7; padding: 14px 32px;'>
                            <span style='font-size: 13px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;'>&#9432; Undangan User Interview</span>
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Sehubungan dengan proses seleksi penerimaan karyawan untuk posisi <strong>{$posisi}</strong> di PT Inti Surya Laboratorium, kami mengundang Anda untuk mengikuti sesi <strong>User Interview</strong> yang telah dijadwalkan sebagai berikut:
                        </p>

                        <!-- Candidate Info Box -->
                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Detail Jadwal Interview
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600; width: 150px;'>Nama Kandidat</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 700;'>{$namaKandidat}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Posisi</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tanggal Interview</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$tglInterview}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tipe Interview</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600; text-transform: capitalize;'>{$jenisInterview}</td>
                                </tr>
                                {$detailLocationHtml}
                            </table>
                        </div>

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0; margin-top: 24px;'>
                            Mohon konfirmasi kehadiran Anda dan harap mempersiapkan diri dengan baik sebelum waktu interview dimulai. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Email Template to Requesting User for User Interview Schedule & Location / Link
     *
     * @param object $data
     * @return string
     */
    public static function bodyEmailUserInterviewUserNotif($data)
    {
        $namaUser       = htmlspecialchars($data->nama_user ?? 'User');
        $namaKandidat   = htmlspecialchars($data->nama_kandidat ?? 'Candidate');
        $noRequest      = htmlspecialchars($data->no_request ?? '-');
        $posisi         = htmlspecialchars($data->posisi ?? '-');
        $jenisInterview = htmlspecialchars($data->jenis_interview ?? '-');
        $linkGmeet      = htmlspecialchars($data->link_gmeet ?? '');
        $ruangan        = htmlspecialchars($data->ruangan_interview ?? 'Office Room');
        $catatan        = $data->catatan ?? '';
        $tglInterview   = $data->tgl_interview ?? '-';

        $jenisTypeStr = strtolower(strip_tags($data->jenis_interview ?? 'online'));

        $detailLocationHtml = "";
        if ($jenisTypeStr === 'online') {
            $detailLocationHtml = "
            <tr>
                <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Link Google Meet</td>
                <td style='padding: 5px 0; color: #0284c7; font-weight: 600;'><a href='{$linkGmeet}' target='_blank' style='color: #2563eb;'>{$linkGmeet}</a></td>
            </tr>";
        } else {
            $detailLocationHtml = "
            <tr>
                <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Ruangan Interview</td>
                <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$ruangan}</td>
            </tr>";
        }

        $catatanHtml = "";
        if (!empty($catatan)) {
            $catatanHtml = "
            <div style='margin-top: 20px;'>
                <strong style='font-size: 14px; color: #475569;'>Catatan / Pesan Tambahan:</strong>
                <div style='font-size: 14px; line-height: 1.6; color: #334155; background-color: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 4px; margin-top: 6px;'>
                    {$catatan}
                </div>
            </div>";
        }

        $greeting = self::internalGreetingHtml($data->nama_user ?? 'User');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Pemberitahuan User Interview - PT Inti Surya Laboratorium</title>
        </head>
        <body style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 30px 0; color: #334155;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                <!-- Header -->
                " . self::emailHeaderHtml() . "

                <!-- Status Banner -->
                <tr>
                    <td style='padding: 0;'>
                        <div style='background-color: #e0f2fe; border-left: 4px solid #0284c7; padding: 14px 32px;'>
                            <span style='font-size: 13px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;'>&#9432; Sarana User Interview Telah Disediakan</span>
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style='padding: 32px;'>
                        {$greeting}

                        <p style='font-size: 14px; line-height: 1.6; color: #334155;'>
                            Melalui email ini kami informasikan bahwa sarana/ruangan untuk sesi <strong>User Interview</strong> kandidat pada Personnel Request Anda (No. Request: <strong>{$noRequest}</strong>) telah disiapkan:
                        </p>

                        <!-- Candidate Info Box -->
                        <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 4px; padding: 18px; margin: 20px 0;'>
                            <div style='font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;'>
                                Informasi Sesi Interview
                            </div>
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600; width: 150px;'>No. Request</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 700;'>{$noRequest}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Nama Kandidat</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 700;'>{$namaKandidat}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Posisi</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$posisi}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tanggal Interview</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600;'>{$tglInterview}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0; color: #475569; font-weight: 600;'>Tipe Interview</td>
                                    <td style='padding: 5px 0; color: #0f172a; font-weight: 600; text-transform: capitalize;'>{$jenisInterview}</td>
                                </tr>
                                {$detailLocationHtml}
                            </table>
                        </div>

                        {$catatanHtml}

                        <p style='font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 0; margin-top: 24px;'>
                            Demikian pemberitahuan ini disampaikan. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                " . self::emailFooterHtml() . "
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Professional Email Template for Candidate Salary Offer Approval (Permohonan Persetujuan Offering Salary)
     * 
     * @param object $data
     * @param object|null $btn
     * @param string|null $mark
     * @return string
     */
    public static function bodyEmailSallaryOffer($data, $btn = null, $mark = 'Offering Salary')
    {
        // $data isinya adalah new_recruitment, dikirim dari controller
        if ($data == null) {
            return '';
        }

        if ($btn === null && !empty($data->token_approval)) {
            $btn = self::buildSalaryDecisionButtons($data);
        }

        return view('TemplateEmail.hrd.permohonan-persetujuan-salary-offer', [
            'data'    => $data,
            'btn'     => $btn,
            'mark'    => $mark,
            'contact' => OfferingSalaryEmail::contactLine($data),

            // cv diambil dari data CandidateProfile dan relasi2 tabel candidate lain
            'cv'      => OfferingSalaryEmail::prepareCvData($data),
        ])->render();
    }
}
