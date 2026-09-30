<?php

namespace App\Services\Quotation;

use App\Models\OrderHeader;
use App\Models\JobTask;
use App\Models\QtExistReactivationLog;
use App\Models\QuotationNonKontrak;
use App\Models\GenerateLink;
use App\Jobs\RenderPdfPenawaran;
use App\Services\GenerateQrDocument;
use App\Services\GenerateToken;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copy QT Non Kontrak untuk pelanggan yang belum pernah order.
 * Terpisah dari CopyNonKontrakJob — hasil copy langsung:
 * is_approved=1, flag_status=draft, is_emailed=false
 * agar muncul di menu QT Approved.
 */
class ReactivateNeverOrderedQuotationService
{
    public function findCandidates(array $months, $year, $limit = null)
    {
        $months = array_values(array_unique(array_map('intval', $months)));
        sort($months);
        $year = (int) $year;

        $query = QuotationNonKontrak::query()
            ->from('request_quotation as rq')
            ->select([
                'rq.id',
                'rq.no_document',
                'rq.flag_status',
                'rq.pelanggan_ID',
                'rq.id_cabang',
                'rq.is_active',
                'rq.created_at',
                'rq.updated_at',
            ])
            ->join('master_pelanggan as mp', function ($join) {
                $join->on('mp.id_pelanggan', '=', 'rq.pelanggan_ID')
                    ->where('mp.is_active', 1);
            })
            ->where('rq.is_active', 1)
            ->where(function ($q) {
                $q->whereIn('rq.flag_status', ['draft', 'emailed', 'sp'])
                    ->orWhereNull('rq.flag_status');
            })
            ->where(function ($q) {
                $q->whereNull('rq.konsultan')
                    ->orWhere('rq.konsultan', '')
                    ->orWhereRaw("TRIM(rq.konsultan) = ''");
            })
            ->whereYear('rq.created_at', $year)
            ->whereIn(DB::raw('MONTH(rq.created_at)'), $months)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('order_header as oh')
                    ->whereColumn('oh.id_pelanggan', 'rq.pelanggan_ID')
                    ->where('oh.is_active', 1);
            })
            // 1 pelanggan = 1 QT: ambil yang paling baru (created_at, lalu id)
            ->whereNotExists(function ($sub) use ($months, $year) {
                $sub->select(DB::raw(1))
                    ->from('request_quotation as rq2')
                    ->whereColumn('rq2.pelanggan_ID', 'rq.pelanggan_ID')
                    ->where('rq2.is_active', 1)
                    ->where(function ($q) {
                        $q->whereIn('rq2.flag_status', ['draft', 'emailed', 'sp'])
                            ->orWhereNull('rq2.flag_status');
                    })
                    ->where(function ($q) {
                        $q->whereNull('rq2.konsultan')
                            ->orWhere('rq2.konsultan', '')
                            ->orWhereRaw("TRIM(rq2.konsultan) = ''");
                    })
                    ->whereYear('rq2.created_at', $year)
                    ->whereIn(DB::raw('MONTH(rq2.created_at)'), $months)
                    ->where(function ($q) {
                        $q->whereColumn('rq2.created_at', '>', 'rq.created_at')
                            ->orWhere(function ($q2) {
                                $q2->whereColumn('rq2.created_at', '=', 'rq.created_at')
                                    ->whereColumn('rq2.id', '>', 'rq.id');
                            });
                    });
            });

        // Skip filter log bila tabel belum dibuat (dry-run / sebelum DBA migrate)
        if ($this->reactivationLogTableExists()) {
            $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('qt_exist_reactivation_logs as log')
                    ->whereColumn('log.no_qt', 'rq.no_document')
                    ->where('log.type', QtExistReactivationLog::TYPE_NEW);
            });
        }

        $query->orderBy('rq.created_at', 'desc');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @return array{ok:bool,source:?QuotationNonKontrak,copy:?QuotationNonKontrak,log:?QtExistReactivationLog,message:string,render:?string}
     */
    public function copyOne(QuotationNonKontrak $source, $createdBy, $dispatchRender = true, $syncRender = null)
    {
        if (!$source->id) {
            return $this->fail('Source QT tidak valid.');
        }

        if (!$this->reactivationLogTableExists()) {
            return $this->fail('Tabel qt_exist_reactivation_logs belum ada. Minta DBA membuat tabel terlebih dahulu.');
        }

        $already = QtExistReactivationLog::where('no_qt', $source->no_document)
            ->where('type', QtExistReactivationLog::TYPE_NEW)
            ->first();
        if ($already) {
            return $this->fail("Sudah pernah di-copy: {$source->no_document} → {$already->no_qt_new}");
        }

        $hasOrder = OrderHeader::where('id_pelanggan', $source->pelanggan_ID)
            ->where('is_active', 1)
            ->exists();
        if ($hasOrder) {
            return $this->fail("Pelanggan {$source->pelanggan_ID} sudah punya order aktif.");
        }

        DB::beginTransaction();
        try {
            $fresh = QuotationNonKontrak::where('id', $source->id)->where('is_active', 1)->firstOrFail();

            $noQuotation = $this->nextRunningNumber((int) $fresh->id_cabang);
            $noDocument = $this->buildNoDocument($noQuotation);

            $copy = $fresh->replicate();
            $copy->no_quotation = $noQuotation;
            $copy->no_document = $noDocument;
            $copy->konsultan = $fresh->konsultan;
            $copy->flag_status = 'draft';
            $copy->tanggal_penawaran = Carbon::now()->format('Y-m-d');
            $copy->created_by = $createdBy;
            $copy->created_at = Carbon::now();
            $copy->updated_by = null;
            $copy->updated_at = null;
            $copy->data_lama = null;
            $copy->keterangan_reject = null;
            $copy->keterangan_reject_sp = null;
            $copy->is_approved = 1;
            $copy->approved_by = 'Lani Febriana Safitri';
            $copy->approved_at = Carbon::now();
            $copy->is_emailed = 0;
            $copy->is_ready_order = 0;
            $copy->emailed_at = null;
            $copy->emailed_by = null;
            $copy->is_generated = 0;
            $copy->generated_at = null;
            $copy->generated_by = null;
            $copy->id_token = null;
            $copy->is_active = 1;
            $copy->save();

            $log = QtExistReactivationLog::create([
                'id_pelanggan' => $fresh->pelanggan_ID,
                'no_qt' => $fresh->no_document,
                'no_qt_new' => $copy->no_document,
                'type' => QtExistReactivationLog::TYPE_NEW,
                'created_at' => Carbon::now(),
            ]);

            DB::commit();

            $renderStatus = 'SKIP';
            if ($dispatchRender) {
                $renderStatus = $this->queueRenderPdf($copy, $syncRender);
            }

            Log::channel('quotation')->info('ReactivateNeverOrderedQuotation: copied', [
                'source' => $fresh->no_document,
                'new' => $copy->no_document,
                'pelanggan' => $fresh->pelanggan_ID,
                'render' => $renderStatus,
            ]);

            return [
                'ok' => true,
                'source' => $fresh,
                'copy' => $copy,
                'log' => $log,
                'render' => $renderStatus,
                'message' => "OK {$fresh->no_document} → {$copy->no_document} [{$renderStatus}]",
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('quotation')->error('ReactivateNeverOrderedQuotation: failed', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fail($e->getMessage());
        }
    }

    /**
     * Render PDF penawaran.
     * - sync=true / REACTIVATE_RENDER_SYNC=true → jalankan langsung (cocok local/data kecil)
     * - default production → dispatch queue job
     */
    public function queueRenderPdf(QuotationNonKontrak $quotation, $sync = null)
    {
        if ($sync === null) {
            $sync = filter_var(env('REACTIVATE_RENDER_SYNC', false), FILTER_VALIDATE_BOOLEAN);
        }

        try {
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => $sync ? 'processing_sync' : 'processing',
                'no_document' => $quotation->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($quotation->id, 'non kontrak');
            if ($sync) {
                $job->handle();
                return 'RENDER_SYNC_OK';
            }

            dispatch($job);
            return 'RENDER_QUEUED';
        } catch (\Throwable $e) {
            Log::channel('quotation')->error('ReactivateNeverOrderedQuotation: render failed', [
                'no_document' => $quotation->no_document,
                'sync' => (bool) $sync,
                'error' => $e->getMessage(),
            ]);

            return 'RENDER_FAIL: ' . $e->getMessage();
        }
    }

    /**
     * Pastikan QR + portal token ada (sama seperti QtApproveController::approve).
     *
     * @return array{ok:bool,token:?string,message:string}
     */
    public function ensureGeneratedLink(QuotationNonKontrak $quotation, $generatedBy)
    {
        try {
            $quotation = QuotationNonKontrak::with('sales.jabatan')->where('id', $quotation->id)->firstOrFail();

            (new GenerateQrDocument())->insert('quotation_non_kontrak', $quotation, $generatedBy);

            $link = GenerateLink::where([
                'id_quotation' => $quotation->id,
                'quotation_status' => 'non_kontrak',
                'type' => 'quotation',
            ])->latest()->first();

            if (!$link) {
                $tokenResult = (new GenerateToken())->save('non_kontrak', $quotation, $generatedBy, 'quotation');
                if (!$tokenResult || empty($tokenResult->id)) {
                    return ['ok' => false, 'token' => null, 'message' => 'Gagal generate token portal'];
                }
                $quotation->is_generated = 1;
                $quotation->generated_by = $generatedBy;
                $quotation->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $quotation->id_token = $tokenResult->id;
                $quotation->expired = $tokenResult->expired;
                $quotation->save();

                $link = GenerateLink::find($tokenResult->id);
            } elseif (!(int) $quotation->is_generated) {
                $quotation->is_generated = 1;
                $quotation->generated_by = $generatedBy;
                $quotation->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $quotation->id_token = $link->id;
                $quotation->expired = $link->expired;
                $quotation->save();
            }

            if (!$link || empty($link->token)) {
                return ['ok' => false, 'token' => null, 'message' => 'Token portal tidak ditemukan'];
            }

            return [
                'ok' => true,
                'token' => $link->token,
                'message' => 'OK',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'token' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * Kirim email seperti QtApproved / QtApproveController::sendEmail,
     * tanpa CC & BCC. Body + subject mirror ComposeMail.js.
     *
     * Keamanan: default TIDAK kirim ke PIC.
     * Pakai $emailOverride / env REACTIVATE_EMAIL_OVERRIDE.
     * Hanya jika $allowPicEmail=true (eksplisit) baru ke email_pic_order.
     *
     * @return array{ok:bool,message:string}
     */
    public function sendEmailLikeQtApproved(
        QuotationNonKontrak $quotation,
        $karyawan,
        $skipIfEmailed = true,
        $emailOverride = null,
        $allowPicEmail = false
    ) {
        $quotation = QuotationNonKontrak::with('sales.jabatan')->where('id', $quotation->id)->first();
        if (!$quotation) {
            return ['ok' => false, 'message' => 'QT tidak ditemukan'];
        }

        if ($skipIfEmailed && (int) $quotation->is_emailed === 1) {
            return ['ok' => false, 'message' => "SKIP sudah emailed: {$quotation->no_document}"];
        }

        $picEmail = trim((string) ($quotation->email_pic_order ?? ''));
        $override = $emailOverride !== null && $emailOverride !== ''
            ? trim((string) $emailOverride)
            : trim((string) env('REACTIVATE_EMAIL_OVERRIDE', ''));

        if ($allowPicEmail) {
            $to = $picEmail;
        } else {
            $to = $override;
            if ($to === '') {
                return [
                    'ok' => false,
                    'message' => 'Blokir kirim ke PIC. Set REACTIVATE_EMAIL_OVERRIDE atau --email-to=anda@intilab.com',
                ];
            }
        }

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => "Email tujuan invalid: {$to}"];
        }

        $linkReady = $this->ensureGeneratedLink($quotation, $karyawan);
        if (!$linkReady['ok']) {
            return ['ok' => false, 'message' => "Token gagal ({$quotation->no_document}): {$linkReady['message']}"];
        }

        $quotation->refresh();
        $quotation->load('sales.jabatan');

        $portalLink = env('PORTALV3_LINK') . $linkReady['token'];

        $subject = $this->buildQtApprovedSubject($quotation);
        $body = $this->buildQtApprovedEmailBody($quotation, $portalLink);
        if (!$allowPicEmail && $picEmail !== '' && strcasecmp($to, $picEmail) !== 0) {
            $body = '<p style="color:#b45309;font-weight:700;">[SAFE MODE] Email asli PIC: '
                . htmlspecialchars($picEmail, ENT_QUOTES, 'UTF-8')
                . ' — dikirim ke override: '
                . htmlspecialchars($to, ENT_QUOTES, 'UTF-8')
                . '</p>' . $body;
            $subject = '[SAFE] ' . $subject;
        }

        DB::beginTransaction();
        try {
            $email = SendEmail::where('to', $to)
                ->where('subject', $subject)
                ->where('body', $body)
                ->where('cc', [])
                ->where('bcc', [])
                ->where('attachment', [])
                ->where('karyawan', $karyawan)
                ->fromAdmsales()
                ->send();

            if (!$email) {
                DB::rollBack();
                return ['ok' => false, 'message' => "Email gagal dikirim: {$quotation->no_document}"];
            }

            // Base update (sama QtApproved sendEmail)
            $quotation->flag_status = 'emailed';
            $quotation->is_emailed = true;
            $quotation->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
            $quotation->emailed_by = $karyawan;

            // Reactivate rule:
            // - status_sampling != SD → flag_status = sp
            // - status_sampling == SD → is_ready_order = 1
            $statusSampling = strtoupper(trim((string) $quotation->status_sampling));
            if ($statusSampling === 'SD') {
                $quotation->is_ready_order = 1;
            } else {
                $quotation->flag_status = 'sp';
            }

            $quotation->save();
            DB::commit();

            Log::channel('quotation')->info('ReactivateNeverOrderedQuotation: emailed', [
                'no_document' => $quotation->no_document,
                'to' => $to,
                'flag_status' => $quotation->flag_status,
            ]);

            return ['ok' => true, 'message' => "EMAILED {$quotation->no_document} → {$to}"];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('quotation')->error('ReactivateNeverOrderedQuotation: email failed', [
                'no_document' => $quotation->no_document,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Batch email dari log type=new (filter sama seperti rollback).
     *
     * @return array{matched:int,sent:int,failed:int,skipped:array,rows:array}
     */
    public function sendEmailsFromLogs(
        array $filters,
        $karyawan,
        $skipIfEmailed = true,
        $emailOverride = null,
        $allowPicEmail = false
    ) {
        $logs = $this->getLogs($filters);
        $sent = 0;
        $failed = 0;
        $skipped = [];
        $rows = [];

        foreach ($logs as $log) {
            $qt = QuotationNonKontrak::where('no_document', $log->no_qt_new)
                ->where('is_active', 1)
                ->first();

            if (!$qt) {
                $failed++;
                $msg = "Log #{$log->id}: QT {$log->no_qt_new} tidak ditemukan/nonaktif";
                $skipped[] = $msg;
                $rows[] = [$log->no_qt_new, '-', 'FAIL', $msg];
                continue;
            }

            $result = $this->sendEmailLikeQtApproved(
                $qt,
                $karyawan,
                $skipIfEmailed,
                $emailOverride,
                $allowPicEmail
            );
            if ($result['ok']) {
                $sent++;
                $rows[] = [$qt->no_document, $emailOverride ?: ($qt->email_pic_order ?: '-'), 'OK', $result['message']];
            } elseif (strpos($result['message'], 'SKIP') === 0) {
                $skipped[] = $result['message'];
                $rows[] = [$qt->no_document, $qt->email_pic_order, 'SKIP', $result['message']];
            } else {
                $failed++;
                $rows[] = [$qt->no_document, $qt->email_pic_order ?? '-', 'FAIL', $result['message']];
            }
        }

        return [
            'matched' => $logs->count(),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    private function buildQtApprovedSubject(QuotationNonKontrak $quotation)
    {
        $nama = html_entity_decode((string) ($quotation->nama_perusahaan ?? ''), ENT_QUOTES);
        $nama = str_replace('&amp;', '&', $nama);
        $konsultan = trim((string) ($quotation->konsultan ?? ''));
        $noDoc = (string) ($quotation->no_document ?? '');

        if ($konsultan !== '') {
            return "Surat Penawaran ({$noDoc}) - {$konsultan} ({$nama})";
        }

        return "Surat Penawaran ({$noDoc}) - {$nama}";
    }

    private function buildQtApprovedEmailBody(QuotationNonKontrak $quotation, $portalLink)
    {
        $statusMap = [
            'S' => 'SAMPLING',
            'SAR' => 'SAMPLING ANTI RIBET',
            'SD' => 'SAMPEL DIANTAR',
            'S24' => 'SAMPLING 24 JAM',
            'RS' => 'RE-SAMPLING',
        ];
        $statusSampling = $statusMap[(string) $quotation->status_sampling] ?? (string) $quotation->status_sampling;

        $pendukung = [];
        $raw = $quotation->data_pendukung_sampling;
        if (is_string($raw)) {
            $raw = str_replace('&quot;', '"', $raw);
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                if (!empty($row['kategori_2'])) {
                    $parts = explode('-', $row['kategori_2']);
                    $pendukung[] = strtoupper(trim(end($parts)));
                }
            }
        }
        $pendukung = array_values(array_unique($pendukung));
        $htmlKat = '';
        foreach ($pendukung as $i => $kat) {
            $htmlKat .= '<br><b>' . ($i + 1) . '. ' . htmlspecialchars($kat, ENT_QUOTES, 'UTF-8') . '</b>';
        }

        $salesName = htmlspecialchars($quotation->sales ? ($quotation->sales->nama_lengkap ?? '') : '', ENT_QUOTES, 'UTF-8');
        $salesPhone = htmlspecialchars($quotation->sales ? ($quotation->sales->no_telpon ?? '') : '', ENT_QUOTES, 'UTF-8');
        $jabatanName = '';
        if ($quotation->sales && $quotation->sales->jabatan) {
            $jabatanName = $quotation->sales->jabatan->nama_jabatan ?? '';
        }
        $jabatan = htmlspecialchars($jabatanName, ENT_QUOTES, 'UTF-8');
        $pic = htmlspecialchars($quotation->nama_pic_order ?? '', ENT_QUOTES, 'UTF-8');
        $perusahaan = htmlspecialchars(html_entity_decode((string) ($quotation->nama_perusahaan ?? ''), ENT_QUOTES), ENT_QUOTES, 'UTF-8');
        $noDoc = htmlspecialchars($quotation->no_document ?? '', ENT_QUOTES, 'UTF-8');
        $ucapan = htmlspecialchars($this->ucapan(), ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($portalLink, ENT_QUOTES, 'UTF-8');
        $statusSamplingEsc = htmlspecialchars($statusSampling, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<p>
    Kepada yang terhormat, <br />
    <b>{$pic} <br />{$perusahaan}</b>
</p>
<p>{$ucapan}</p>
<p>
    Berikut kami lampirkan : <br />
    <b>Surat Penawaran ({$noDoc}) - {$statusSamplingEsc} </b>
</p>
<p>Kategori Pengujian : {$htmlKat}</p>
<p>
    Mohon agar file yang kami kirim melalui link berikut : <a href="{$link}">Click Here</a>, dapat diperiksa lebih lanjut. Sehubungan mengenai konfirmasi penjadwalan dan hal yang ingin ditanyakan atau didiskusikan, dapat langsung menghubungi pihak
    kami melalui {$salesName} ({$salesPhone})
</p>
<p>Terima kasih atas perhatian, kepercayaan, serta kerjasama yang sangat baik.</p>
<p><u><strong>CATATAN PENTING</strong></u></p>
<p>Mohon Bapak/Ibu dapat meninjau kembali form penawaran ini untuk memastikan seluruh data kebutuhan telah sesuai. Sebagai bentuk persetujuan, mohon menandatangani dan mengirimkan kembali kepada kami melalui email: <a href="mailto:sales@intilab.com">sales@intilab.com</a></p>
<p><u><strong>INFORMASI PENTING</strong></u></p>
<p>
    <em>E-mail</em> ini dikirimkan secara otomatis oleh sistem PT Inti Surya Laboratorium (INTILAB) melalui <br />
    <strong><u>alamat <i>E-mail</i> resmi perusahaan, yaitu <span style="color: rgb(255, 0, 0)"><strong><u>admsales01@intilab.com</u></strong></span></u></strong>.
    Untuk menjaga keamanan data dan <br />informasi, disarankan agar penerima <em>E-mail</em> : <br />
    a. <strong>Memastikan kembali alamat pengirim </strong><em><strong>E-mail</strong></em><strong> ini adalah sesuai alamat </strong><em><strong>E-mail</strong></em><strong> resmi perusahaan; dan,</strong><br />
    b. <strong>Tidak mengklik tautan dan/atau mengunduh lampiran apapun jika </strong><em><strong>E-mail</strong></em><strong> ini dikirimkan selain</strong><br />
    <strong>dari alamat </strong><em><strong>E-mail</strong></em><strong> resmi perusahaan.</strong>
</p>
<p><em>E-mail</em> dan dokumen lampiran ini bersifat rahasia (berisi data dan informasi rahasia) yang ditujukan <br />secara eksklusif kepada penerima <em>E-mail</em>.</p>
<p>Best Regards,</p>
<br />
<table border="0" cellspacing="0" cellpadding="0" style="width: 494px">
    <tbody>
        <tr>
            <td style="padding: 0 0 3.75pt; width: 493px">{$salesName}</td>
        </tr>
        <tr>
            <td style="padding: 0; width: 493px"><b><span style="font-size: 10pt; color: #3c3c3b">{$jabatan}</span></b></td>
        </tr>
        <tr>
            <td style="padding: 0 0 0.75pt; width: 493px">
                <b><span style="font-size: 9pt; color: #3c3c3b">T:</span></b>
                <span style="font-size: 9pt; color: #3c3c3b">021 50898988</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 0 0.75pt; width: 493px">
                <b><span style="font-size: 9pt; color: #3c3c3b">E:</span></b>
                <a href="mailto:admsales01@intilab.com">admsales01@intilab.com</a> | <a href="http://www.intilab.com">www.intilab.com</a>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 0 0.75pt; width: 493px">
                <b><span style="font-size: 9pt; color: #3c3c3b">PT Inti Surya Laboratorium</span></b>
                <span style="font-size: 9pt; color: #3c3c3b"><br />Ruko Icon Business Park Blok O No. 5 - 6 BSD City | Sampora, Cisauk, Kab. Tangerang</span>
            </td>
        </tr>
    </tbody>
</table>
HTML;
    }

    private function ucapan()
    {
        $h = (int) date('G');
        if ($h >= 4 && $h < 10) {
            return 'Selamat pagi,';
        }
        if ($h >= 10 && $h < 15) {
            return 'Selamat siang,';
        }
        if ($h >= 15 && $h < 18) {
            return 'Selamat sore,';
        }

        return 'Selamat malam,';
    }

    /**
     * Build query log type=new dengan filter fleksibel.
     *
     * filters:
     * - log_ids: int[]
     * - date: Y-m-d (satu hari penuh)
     * - since: Carbon|string
     * - until: Carbon|string (inclusive end of day jika string date)
     * - months: int[] + year: int  (berdasarkan created_at log)
     * - no_qt: string[] sumber
     * - no_qt_new: string[] hasil copy
     * - all: bool (tanpa filter waktu; tetap type=new)
     */
    public function queryLogs(array $filters = [])
    {
        if (!$this->reactivationLogTableExists()) {
            return null;
        }

        $query = QtExistReactivationLog::query()->where('type', QtExistReactivationLog::TYPE_NEW);

        if (!empty($filters['log_ids'])) {
            $query->whereIn('id', array_map('intval', $filters['log_ids']));
        }

        if (!empty($filters['no_qt'])) {
            $query->whereIn('no_qt', (array) $filters['no_qt']);
        }

        if (!empty($filters['no_qt_new'])) {
            $query->whereIn('no_qt_new', (array) $filters['no_qt_new']);
        }

        if (!empty($filters['date'])) {
            $day = Carbon::parse($filters['date']);
            $query->whereBetween('created_at', [
                $day->copy()->startOfDay(),
                $day->copy()->endOfDay(),
            ]);
        }

        if (!empty($filters['since'])) {
            $since = $filters['since'] instanceof Carbon
                ? $filters['since']
                : Carbon::parse($filters['since'])->startOfDay();
            $query->where('created_at', '>=', $since);
        }

        if (!empty($filters['until'])) {
            $until = $filters['until'] instanceof Carbon
                ? $filters['until']
                : Carbon::parse($filters['until'])->endOfDay();
            $query->where('created_at', '<=', $until);
        }

        if (!empty($filters['months']) && !empty($filters['year'])) {
            $months = array_values(array_unique(array_map('intval', (array) $filters['months'])));
            $year = (int) $filters['year'];
            $query->whereYear('created_at', $year)
                ->whereIn(DB::raw('MONTH(created_at)'), $months);
        }

        return $query->orderBy('id');
    }

    /**
     * @return \Illuminate\Support\Collection|QtExistReactivationLog[]
     */
    public function getLogs(array $filters = [])
    {
        $query = $this->queryLogs($filters);
        if ($query === null) {
            return collect();
        }

        return $query->get();
    }

    /**
     * Rollback: nonaktifkan QT baru dari log type=new, lalu hapus log.
     *
     * @return array{deactivated:int,logs_deleted:int,skipped:array,matched:int}
     */
    public function rollback(array $filters = [])
    {
        $logs = $this->getLogs($filters);
        $deactivated = 0;
        $deleted = 0;
        $skipped = [];

        foreach ($logs as $log) {
            DB::beginTransaction();
            try {
                $qt = QuotationNonKontrak::where('no_document', $log->no_qt_new)->first();
                if (!$qt) {
                    $skipped[] = "Log #{$log->id}: QT baru {$log->no_qt_new} tidak ditemukan";
                } elseif ((int) $qt->is_active === 0) {
                    $skipped[] = "Log #{$log->id}: {$log->no_qt_new} sudah nonaktif";
                } else {
                    $qt->is_active = 0;
                    $qt->updated_at = Carbon::now();
                    $qt->updated_by = 'SYSTEM_REACTIVATE_ROLLBACK';
                    $qt->save();
                    $deactivated++;
                }

                $log->delete();
                $deleted++;
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $skipped[] = "Log #{$log->id}: {$e->getMessage()}";
            }
        }

        return [
            'matched' => $logs->count(),
            'deactivated' => $deactivated,
            'logs_deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    private function reactivationLogTableExists()
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('qt_exist_reactivation_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function nextRunningNumber($idCabang)
    {
        $idCabang = (int) $idCabang;
        $tahunChek = date('y');

        $cek = QuotationNonKontrak::where('id_cabang', $idCabang)
            ->where('no_document', 'not like', '%R%')
            ->where('no_document', 'like', '%/' . $tahunChek . '-%')
            ->orderBy('id', 'DESC')
            ->first();

        $no = 1;
        if ($cek !== null) {
            $parts = explode('/', $cek->no_document);
            if (count($parts) > 3) {
                $tahunCekFull = $parts[2];
                [$tahunCekDocLast] = explode('-', $tahunCekFull);
                if ((int) $tahunChek === (int) $tahunCekDocLast) {
                    $no = (int) $parts[3] + 1;
                }
            }
        }

        return sprintf('%06d', $no);
    }

    private function buildNoDocument($noQuotation)
    {
        $tahunChek = date('y');
        $bulanRomawi = $this->romawi((int) date('m'));

        return 'ISL/QT/' . $tahunChek . '-' . $bulanRomawi . '/' . $noQuotation;
    }

    private function romawi($bulan)
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $idx = max(0, min(11, ((int) $bulan) - 1));

        return $romawi[$idx];
    }

    private function fail($message)
    {
        return [
            'ok' => false,
            'source' => null,
            'copy' => null,
            'log' => null,
            'render' => null,
            'message' => $message,
        ];
    }
}
