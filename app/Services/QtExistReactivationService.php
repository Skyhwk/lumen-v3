<?php

namespace App\Services;

use App\Models\GenerateLink;
use App\Models\JobTask;
use App\Models\QtExistReactivationLog;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Jobs\RenderPdfPenawaran;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QtExistReactivationService
{
    private const MONTHS = 6;
    private const LOG_CHANNEL = 'qt_exist_reactivation';
    private const APPROVED_BY = 'Lani Febriana Safitri';
    private const TZ = 'Asia/Jakarta';

    private function cutoff(): Carbon
    {
        return Carbon::now(self::TZ)->subMonths(self::MONTHS);
    }

    /**
     * Preview calon pelanggan exist yang akan digenerate ulang QT-nya.
     */
    public function preview(?int $limit = null): array
    {
        Log::channel(self::LOG_CHANNEL)->info("\n\n\n== QT EXIST REACTIVATION PREVIEW STARTED ==\n\n", [
            'timestamp' => Carbon::now()->toDateTimeString(),
            'limit' => $limit,
        ]);

        try {
            $candidates = $this->collectCandidates($limit);

            Log::channel(self::LOG_CHANNEL)->info('== QT EXIST REACTIVATION PREVIEW FINISHED ==', [
                'timestamp' => Carbon::now()->toDateTimeString(),
                'total_candidates' => $candidates->count(),
            ]);

            return [
                'cutoff' => $this->cutoff()->toDateTimeString(),
                'total_candidates' => $candidates->count(),
                'limited' => $limit !== null,
                'data' => $candidates->values(),
            ];
        } catch (\Throwable $th) {
            Log::channel(self::LOG_CHANNEL)->error('preview error', [
                $th->getMessage(),
                $th->getLine(),
                $th->getFile(),
            ]);
            throw $th;
        }
    }

    /**
     * Eksekusi duplikat QT non-kontrak ke status draft + approved.
     */
    public function execute(string $actorName, ?int $limit = null): array
    {
        if (!Schema::hasTable('qt_exist_reactivation_logs')) {
            throw new \RuntimeException('Tabel qt_exist_reactivation_logs belum ada. Jalankan migration dulu.');
        }

        Log::channel(self::LOG_CHANNEL)->info("\n\n\n== QT EXIST REACTIVATION EXECUTE STARTED ==\n\n", [
            'timestamp' => Carbon::now()->toDateTimeString(),
            'actor' => $actorName,
            'limit' => $limit,
        ]);

        try {
            $candidates = $this->collectCandidates($limit);
            $success = [];
            $failed = [];
            $skipped = [];

            Log::channel(self::LOG_CHANNEL)->info('Candidates collected', [
                'total' => $candidates->count(),
            ]);

            foreach ($candidates as $row) {
                try {
                    if ($this->wasProcessedRecently($row['id_pelanggan'])) {
                        $reason = 'Sudah pernah diproses dalam 6 bulan (type=exist)';
                        $skipped[] = [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'reason' => $reason,
                        ];
                        Log::channel(self::LOG_CHANNEL)->info('SKIP', [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'reason' => $reason,
                        ]);
                        continue;
                    }

                    $source = QuotationNonKontrak::where('id', $row['source_qt_id'])->first();

                    if (!$source) {
                        $reason = 'QT sumber tidak ditemukan';
                        $skipped[] = [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'reason' => $reason,
                        ];
                        Log::channel(self::LOG_CHANNEL)->info('SKIP', [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'reason' => $reason,
                        ]);
                        continue;
                    }

                    if ($this->quotationUsesPromo($source)) {
                        $reason = 'QT sumber memakai promo';
                        $skipped[] = [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'no_qt' => $source->no_document,
                            'reason' => $reason,
                        ];
                        Log::channel(self::LOG_CHANNEL)->info('SKIP', [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'no_qt' => $source->no_document,
                            'reason' => $reason,
                        ]);
                        continue;
                    }

                    $created = DB::transaction(function () use ($source, $actorName) {
                        return $this->duplicateQuotation($source, $actorName);
                    });

                    try {
                        $delivery = $this->deliverQuotation($created);
                    } catch (\Throwable $deliveryError) {
                        $created->is_active = false;
                        $created->flag_status = 'failed_delivery';
                        $created->deleted_by = $actorName ?: 'SYSTEM';
                        $created->deleted_at = Carbon::now(self::TZ)->format('Y-m-d H:i:s');
                        $created->save();

                        Log::channel(self::LOG_CHANNEL)->error('DELIVERY FAILED - QT copy deactivated', [
                            'id_pelanggan' => $row['id_pelanggan'],
                            'no_qt_new' => $created->no_document,
                            'error' => $deliveryError->getMessage(),
                        ]);

                        throw $deliveryError;
                    }

                    try {
                        QtExistReactivationLog::create([
                            'id_pelanggan' => (string) $source->pelanggan_ID,
                            'no_qt' => (string) $source->no_document,
                            'no_qt_new' => (string) $created->no_document,
                            'type' => 'exist',
                            'created_at' => Carbon::now(self::TZ),
                        ]);
                    } catch (\Throwable $logError) {
                        Log::channel(self::LOG_CHANNEL)->error('LOG INSERT FAILED after delivery success', [
                            'id_pelanggan' => $source->pelanggan_ID,
                            'no_qt' => $source->no_document,
                            'no_qt_new' => $created->no_document,
                            'error' => $logError->getMessage(),
                        ]);
                        throw new \RuntimeException(
                            'QT & email sukses, tapi gagal insert qt_exist_reactivation_logs: ' . $logError->getMessage(),
                            0,
                            $logError
                        );
                    }

                    $successItem = [
                        'id_pelanggan' => $row['id_pelanggan'],
                        'nama_pelanggan' => $row['nama_pelanggan'],
                        'no_qt' => $source->no_document,
                        'no_qt_new' => $created->no_document,
                        'link' => $delivery['link'] ?? null,
                        'emailed' => (bool) ($delivery['emailed'] ?? false),
                        'emailed_to' => $delivery['to'] ?? null,
                    ];
                    $success[] = $successItem;

                    Log::channel(self::LOG_CHANNEL)->info("\n\n== QT EXIST REACTIVATED ==\n\n", [
                        'timestamp' => Carbon::now()->toDateTimeString(),
                        'actor' => $actorName,
                        'id_pelanggan' => $row['id_pelanggan'],
                        'nama_pelanggan' => $row['nama_pelanggan'],
                        'no_qt' => $source->no_document,
                        'no_qt_new' => $created->no_document,
                        'link' => $delivery['link'] ?? null,
                        'emailed' => (bool) ($delivery['emailed'] ?? false),
                        'emailed_to' => $delivery['to'] ?? null,
                        'sales' => $row['sales'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id_pelanggan' => $row['id_pelanggan'],
                        'no_qt' => $row['no_qt'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                    Log::channel(self::LOG_CHANNEL)->error('FAILED', [
                        'id_pelanggan' => $row['id_pelanggan'],
                        'no_qt' => $row['no_qt'] ?? null,
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ]);
                }
            }

            $summary = [
                'processed' => count($success),
                'failed_count' => count($failed),
                'skipped_count' => count($skipped),
                'success' => $success,
                'failed' => $failed,
                'skipped' => $skipped,
            ];

            Log::channel(self::LOG_CHANNEL)->info("\n\n== QT EXIST REACTIVATION EXECUTE FINISHED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
                'processed' => $summary['processed'],
                'failed_count' => $summary['failed_count'],
                'skipped_count' => $summary['skipped_count'],
            ]);

            return $summary;
        } catch (\Throwable $th) {
            Log::channel(self::LOG_CHANNEL)->error('execute error', [
                $th->getMessage(),
                $th->getLine(),
                $th->getFile(),
            ]);
            throw $th;
        }
    }

    private function collectCandidates(?int $limit = null): Collection
    {
        $cutoff = $this->cutoff();
        $cutoffStr = $cutoff->toDateTimeString();
        $startedAt = microtime(true);

        $alreadyIds = [];
        if (Schema::hasTable('qt_exist_reactivation_logs')) {
            $alreadyIds = QtExistReactivationLog::query()
                ->where('type', 'exist')
                ->where('created_at', '>=', $cutoff)
                ->pluck('id_pelanggan')
                ->unique()
                ->filter()
                ->values()
                ->all();
        }

        // Phase 1 (ringan): 1 order terbaru / pelanggan — tanpa join ke request_quotation
        $orderAgg = DB::table('order_header as oh')
            ->join('master_pelanggan as mp', function ($join) {
                $join->on('mp.id_pelanggan', '=', 'oh.id_pelanggan')
                    ->where('mp.is_active', '=', 1);
            })
            ->select([
                'oh.id_pelanggan',
                DB::raw('SUBSTRING_INDEX(GROUP_CONCAT(oh.id ORDER BY oh.created_at DESC, oh.id DESC), ",", 1) as oh_id'),
                DB::raw('MAX(oh.created_at) as last_order_at'),
            ])
            ->where('oh.is_active', 1)
            ->where('oh.created_at', '>=', $cutoffStr)
            ->where('oh.no_document', 'like', '%/QT/%')
            ->where('oh.no_document', 'not like', '%/QTC/%')
            ->where(function ($q) {
                $q->whereNull('oh.konsultan')
                    ->orWhere('oh.konsultan', '')
                    ->orWhereRaw("TRIM(oh.konsultan) = ''");
            })
            ->whereNotNull('oh.id_pelanggan')
            ->where('oh.id_pelanggan', '!=', '')
            ->when(!empty($alreadyIds), function ($q) use ($alreadyIds) {
                $q->whereNotIn('oh.id_pelanggan', $alreadyIds);
            })
            ->groupBy('oh.id_pelanggan')
            ->orderByDesc(DB::raw('MAX(oh.created_at)'));

        // Over-fetch sedikit: sebagian pelanggan bisa tidak punya QT non-promo di request_quotation
        if ($limit !== null && $limit > 0) {
            $orderAgg->limit(max($limit * 5, $limit));
        }

        $orderGroups = $orderAgg->get();
        if ($orderGroups->isEmpty()) {
            return collect();
        }

        $ohIds = $orderGroups->pluck('oh_id')->filter()->map(function ($id) {
            return (int) $id;
        })->values()->all();

        $ordersByPelanggan = DB::table('order_header')
            ->whereIn('id', $ohIds)
            ->get(['id', 'id_pelanggan', 'created_at', 'no_document'])
            ->keyBy('id_pelanggan');

        // Phase 2: QT sumber HARUS sama no_document dengan order terakhir
        $noDocuments = $ordersByPelanggan->pluck('no_document')->filter()->unique()->values()->all();

        $quotesByNoDocument = empty($noDocuments)
            ? collect()
            : DB::table('request_quotation')
                ->whereIn('no_document', $noDocuments)
                ->where(function ($q) {
                    $q->whereNull('konsultan')
                        ->orWhere('konsultan', '')
                        ->orWhereRaw("TRIM(konsultan) = ''");
                })
                ->where(function ($q) {
                    $q->whereNull('kode_promo')
                        ->orWhere('kode_promo', '')
                        ->orWhereRaw("TRIM(kode_promo) = ''");
                })
                ->orderByDesc('id')
                ->get([
                    'id',
                    'pelanggan_ID',
                    'nama_perusahaan',
                    'no_document',
                    'tanggal_penawaran',
                    'flag_status',
                    'is_approved',
                    'biaya_akhir',
                    'sales_id',
                    'kode_promo',
                ])
                ->unique('no_document')
                ->keyBy('no_document');

        $rows = collect();
        foreach ($orderGroups as $og) {
            $pid = $og->id_pelanggan;
            $oh = $ordersByPelanggan->get($pid);
            if (!$oh || empty($oh->no_document)) {
                continue;
            }

            $rq = $quotesByNoDocument->get($oh->no_document);
            if (!$rq) {
                continue;
            }
            if ($this->quotationUsesPromo($rq)) {
                continue;
            }

            $lastOrderAt = $oh->created_at ?? $og->last_order_at ?? null;
            if ($lastOrderAt instanceof \DateTimeInterface) {
                $lastOrderAt = Carbon::instance($lastOrderAt)->toDateTimeString();
            } elseif ($lastOrderAt) {
                $lastOrderAt = (string) $lastOrderAt;
            }

            $rows->push([
                'id_pelanggan' => $pid,
                'nama_pelanggan' => $rq->nama_perusahaan,
                'sales' => null,
                'last_order_at' => $lastOrderAt,
                'last_order_no_document' => $oh->no_document,
                'source_qt_id' => $rq->id,
                'no_qt' => $rq->no_document,
                'tanggal_penawaran' => $rq->tanggal_penawaran,
                'flag_status' => $rq->flag_status,
                'is_approved' => (bool) $rq->is_approved,
                'biaya_akhir' => (float) ($rq->biaya_akhir ?? 0),
            ]);

            if ($limit !== null && $limit > 0 && $rows->count() >= $limit) {
                break;
            }
        }

        Log::channel(self::LOG_CHANNEL)->info('Collect candidates via order no_document = request_quotation.no_document', [
            'cutoff' => $cutoffStr,
            'already_skipped' => count($alreadyIds),
            'order_groups' => $orderGroups->count(),
            'qt_matched' => $quotesByNoDocument->count(),
            'total_candidates' => $rows->count(),
            'limit' => $limit,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $rows->values();
    }

    private function quotationUsesPromo($quote): bool
    {
        return trim((string) ($quote->kode_promo ?? '')) !== '';
    }

    private function wasProcessedRecently(string $idPelanggan): bool
    {
        if (!Schema::hasTable('qt_exist_reactivation_logs')) {
            return false;
        }

        return QtExistReactivationLog::query()
            ->where('type', 'exist')
            ->where('id_pelanggan', $idPelanggan)
            ->where('created_at', '>=', $this->cutoff())
            ->exists();
    }

    private function duplicateQuotation(QuotationNonKontrak $source, string $actorName): QuotationNonKontrak
    {
        $now = Carbon::now();
        [$noQuotation, $noDocument] = $this->generateNoDocument((int) $source->id_cabang, $now);

        $copy = $source->replicate();
        $copy->no_quotation = $noQuotation;
        $copy->no_document = $noDocument;
        $copy->data_lama = $source->no_document;
        $copy->tanggal_penawaran = $now->toDateString();
        $copy->flag_status = 'draft';
        $copy->is_approved = true;
        $copy->approved_by = self::APPROVED_BY;
        $copy->approved_at = $now->format('Y-m-d H:i:s');
        $copy->is_active = true;
        $copy->is_rejected = false;
        $copy->keterangan_reject = null;
        $copy->rejected_at = null;
        $copy->rejected_by = null;
        $copy->is_emailed = false;
        $copy->emailed_at = null;
        $copy->emailed_by = null;
        $copy->is_generated = false;
        $copy->generated_at = null;
        $copy->generated_by = null;
        $copy->sp_by = null;
        $copy->keterangan_reject_sp = null;
        $copy->id_token = null;
        $copy->expired = null;
        $copy->konfirmasi_order = null;
        $copy->is_ready_order = 0;
        $copy->document_status = null;
        $copy->filename = null;
        $copy->jadwalfile = null;
        $copy->kode_promo = null;
        $copy->promo_id = null;
        $copy->discount_promo = 0;
        $copy->total_discount_promo = 0;
        $copy->created_by = $actorName ?: 'SYSTEM';
        $copy->created_at = $now->format('Y-m-d H:i:s');
        $copy->updated_by = null;
        $copy->updated_at = null;
        $copy->deleted_by = null;
        $copy->deleted_at = null;
        $copy->save();

        return $copy;
    }

    /**
     * Generate QR + PDF + token/link, lalu kirim email ke customer (pola QT Approved).
     */
    private function deliverQuotation(QuotationNonKontrak $quotation): array
    {
        $actor = self::APPROVED_BY;
        $quote = QuotationNonKontrak::with('sales')->where('id', $quotation->id)->firstOrFail();

        (new GenerateQrDocument())->insert('quotation_non_kontrak', $quote, $actor);

        JobTask::insert([
            'job' => 'RenderPdfPenawaran',
            'status' => 'processing',
            'no_document' => $quote->no_document,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        // Sync render supaya filename_pdf di token sudah terisi sebelum email
        try {
            $render = new RenderNonKontrak();
            $render->renderHeader($quote->id, 'id');
            $render = new RenderNonKontrak();
            $render->renderHeader($quote->id, 'en');
        } catch (\Throwable $th) {
            Log::channel(self::LOG_CHANNEL)->warning('PDF render sync failed, fallback dispatch job', [
                'no_document' => $quote->no_document,
                'error' => $th->getMessage(),
            ]);
            dispatch(new RenderPdfPenawaran($quote->id, 'non kontrak'));
        }

        $quote->refresh();

        $token = (new GenerateToken())->save('non_kontrak', $quote, $actor, 'quotation');
        if (!$token || empty($token->id)) {
            throw new \RuntimeException('Gagal generate token/link untuk ' . $quote->no_document);
        }

        $quote->is_generated = true;
        $quote->generated_by = $actor;
        $quote->generated_at = Carbon::now()->format('Y-m-d H:i:s');
        $quote->id_token = $token->id;
        $quote->expired = $token->expired;
        $quote->save();

        $linkRow = GenerateLink::where([
            'id_quotation' => $quote->id,
            'quotation_status' => 'non_kontrak',
            'type' => 'quotation',
        ])->latest('id')->first();

        if (!$linkRow || empty($linkRow->token)) {
            throw new \RuntimeException('Token link tidak ditemukan untuk ' . $quote->no_document);
        }

        $portalBase = (string) env('PORTALV3_LINK', '');
        if ($portalBase === '') {
            throw new \RuntimeException('PORTALV3_LINK belum di-set di environment');
        }
        $portalLink = $portalBase . $linkRow->token;

        $to = $this->normalizeEmail($quote->email_pic_order);
        if ($to === null) {
            throw new \RuntimeException('email_pic_order kosong untuk ' . $quote->no_document);
        }

        $cc = $this->buildCcEmails($quote);
        $bcc = $this->buildBccEmails((int) ($quote->sales_id ?: 0), $quote->email_cc);
        $subject = $this->buildEmailSubject($quote);
        $body = $this->buildEmailBody($quote, $portalLink);

        $sent = SendEmail::where('to', $to)
            ->where('subject', $subject)
            ->where('body', $body)
            ->where('cc', $cc)
            ->where('bcc', $bcc)
            ->where('attachments', [])
            ->where('karyawan', $actor)
            ->fromAdmsales()
            ->send();

        if (!$sent) {
            throw new \RuntimeException('Email gagal dikirim untuk ' . $quote->no_document);
        }

        $quote->flag_status = 'emailed';
        $quote->is_emailed = true;
        $quote->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
        $quote->emailed_by = $actor;
        $this->applyPostEmailFlags($quote);
        $quote->save();

        Log::channel(self::LOG_CHANNEL)->info('QT delivered (link + email)', [
            'no_document' => $quote->no_document,
            'link' => $portalLink,
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
        ]);

        return [
            'link' => $portalLink,
            'emailed' => true,
            'to' => $to,
        ];
    }

    private function applyPostEmailFlags(QuotationNonKontrak $data): void
    {
        $nonPengujian = false;
        $statusSampling = [];

        if (empty(json_decode($data->data_pendukung_sampling, true))) {
            $nonPengujian = true;
        }
        $statusSampling[] = $data->status_sampling;

        if ($data->data_lama !== null && $data->data_lama !== 'null') {
            $dataLama = json_decode($data->data_lama);
            if (is_object($dataLama) && isset($dataLama->status_sp) && $dataLama->status_sp == 'false') {
                $cekSp = SamplingPlan::where('no_quotation', $data->no_document)
                    ->where('is_active', 1)
                    ->where('is_approved', 1)
                    ->exists();
                if ($cekSp) {
                    $data->flag_status = 'sp';
                    $data->is_ready_order = 1;
                }
            }
        }

        $statusSampling = array_unique($statusSampling);
        if (count($statusSampling) === 1) {
            if (in_array('SD', $statusSampling, true) || in_array('SAR', $statusSampling, true)) {
                $data->flag_status = 'sp';
                $data->is_ready_order = 1;
            } elseif ($nonPengujian) {
                $data->flag_status = 'sp';
                $data->is_ready_order = 1;
            }
        }

        if ((int) ($data->is_generate_data_lab ?? 1) === 0) {
            $data->flag_status = 'sp';
            $data->is_ready_order = 1;
        }
    }

    private function buildEmailSubject(QuotationNonKontrak $quote): string
    {
        $nama = html_entity_decode((string) ($quote->nama_perusahaan ?? ''), ENT_QUOTES, 'UTF-8');
        $nama = str_replace('&amp;', '&', $nama);
        $konsultan = trim((string) ($quote->konsultan ?? ''));

        if ($konsultan !== '') {
            return 'Surat Penawaran (' . $quote->no_document . ') - ' . $konsultan . ' (' . $nama . ')';
        }

        return 'Surat Penawaran (' . $quote->no_document . ') - ' . $nama;
    }

    private function buildEmailBody(QuotationNonKontrak $quote, string $link): string
    {
        $statusMap = [
            'S' => 'SAMPLING',
            'SAR' => 'SAMPLING ANTI RIBET',
            'SD' => 'SAMPEL DIANTAR',
            'S24' => 'SAMPLING 24 JAM',
            'RS' => 'RE-SAMPLING',
        ];
        $statusSampling = $statusMap[$quote->status_sampling] ?? (string) ($quote->status_sampling ?? '');

        $pendukung = [];
        $rawPendukung = $quote->data_pendukung_sampling
            ? json_decode(str_replace('&quot;', '"', (string) $quote->data_pendukung_sampling), true)
            : [];
        if (is_array($rawPendukung)) {
            foreach ($rawPendukung as $item) {
                if (!empty($item['kategori_2'])) {
                    $parts = explode('-', $item['kategori_2']);
                    $pendukung[] = strtoupper(trim((string) end($parts)));
                }
            }
        }
        $pendukung = array_values(array_unique(array_filter($pendukung)));
        $htmlKategori = '';
        foreach ($pendukung as $i => $kat) {
            $htmlKategori .= '<br><b>' . ($i + 1) . '. ' . e($kat) . '</b>';
        }

        $ucapan = $this->ucapanSalam();
        $salesName = e((string) optional($quote->sales)->nama_lengkap);
        $salesPhone = e((string) optional($quote->sales)->no_telpon);
        $namaPic = e((string) ($quote->nama_pic_order ?? ''));
        $namaPerusahaan = e(html_entity_decode((string) ($quote->nama_perusahaan ?? ''), ENT_QUOTES, 'UTF-8'));
        $noDocument = e((string) $quote->no_document);
        $safeLink = e($link);
        $approver = e(self::APPROVED_BY);

        return <<<HTML
<p>
    Kepada yang terhormat, <br />
    <b>{$namaPic} <br />{$namaPerusahaan}</b>
</p>
<p>{$ucapan}</p>
<p>
    Berikut kami lampirkan : <br />
    <b>Surat Penawaran ({$noDocument}) - {$statusSampling}</b>
</p>
<p>Kategori Pengujian : {$htmlKategori}</p>
<p>
    Mohon agar file yang kami kirim melalui link berikut : <a href="{$safeLink}">Click Here</a>, dapat diperiksa lebih lanjut. Sehubungan mengenai konfirmasi penjadwalan dan hal yang ingin ditanyakan atau didiskusikan, dapat langsung menghubungi pihak
    kami melalui {$salesName} ({$salesPhone})
</p>
<p>Terima kasih atas perhatian, kepercayaan, serta kerjasama yang sangat baik.</p>
<p><u><strong>CATATAN PENTING</strong></u></p>
<p>Mohon Bapak/Ibu dapat meninjau kembali form penawaran ini untuk memastikan seluruh data kebutuhan telah sesuai. Sebagai bentuk persetujuan, mohon menandatangani dan mengirimkan kembali kepada kami melalui email: <a href="mailto:sales@intilab.com">sales@intilab.com</a></p>
<p><u><strong>INFORMASI PENTING</strong></u></p>
<p>
    <em>E-mail</em> ini dikirimkan secara otomatis oleh sistem PT Inti Surya Laboratorium (INTILAB) melalui
    <strong><u>alamat <i>E-mail</i> resmi perusahaan, yaitu <span style="color: rgb(255, 0, 0)"><strong><u>admsales01@intilab.com</u></strong></span></u></strong>.
</p>
<p>Best Regards,</p>
<br />
<p><strong>{$approver}</strong><br />Admin Sales<br />PT Inti Surya Laboratorium</p>
HTML;
    }

    private function ucapanSalam(): string
    {
        $h = (int) Carbon::now('Asia/Jakarta')->format('G');
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

    private function buildCcEmails(QuotationNonKontrak $quote): array
    {
        $emails = [];
        $decoded = json_decode((string) ($quote->email_cc ?? ''), true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $email = $this->normalizeEmail(is_array($item) ? ($item['email'] ?? reset($item)) : $item);
                if ($email) {
                    $emails[] = $email;
                }
            }
        }

        $picSampling = $this->normalizeEmail($quote->email_pic_sampling ?? null);
        $picOrder = $this->normalizeEmail($quote->email_pic_order ?? null);
        if ($picSampling && $picSampling !== $picOrder) {
            $emails[] = $picSampling;
        }

        return array_values(array_unique($emails));
    }

    private function buildBccEmails(int $salesId, $emailCcRaw): array
    {
        $emails = ['sales@intilab.com'];
        $filterEmails = [
            'inafitri@intilab.com',
            'kika@intilab.com',
            'trialif@intilab.com',
            'manda@intilab.com',
            'amin@intilab.com',
            'daud@intilab.com',
            'faidhah@intilab.com',
            'budiono@intilab.com',
            'yeni@intilab.com',
            'riri@intilab.com',
            'shalsa@intilab.com',
            'rudi@intilab.com',
        ];

        $decoded = json_decode((string) ($emailCcRaw ?? ''), true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $email = $this->normalizeEmail(is_array($item) ? ($item['email'] ?? reset($item)) : $item);
                if ($email) {
                    $emails[] = $email;
                }
            }
        }

        if ($salesId > 0) {
            $users = GetAtasan::where('id', $salesId)->get()->pluck('email');
            foreach ($users as $item) {
                if ($item === 'novva@intilab.com') {
                    $emails[] = 'sales02@intilab.com';
                    continue;
                }
                if (in_array($item, $filterEmails, true)) {
                    $emails[] = 'admsales04@intilab.com';
                }
                if ($this->normalizeEmail($item)) {
                    $emails[] = $this->normalizeEmail($item);
                }
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    private function normalizeEmail($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $email = trim((string) $value);
        if ($email === '') {
            return null;
        }
        // Ambil email pertama jika terpisah koma/semicolon
        $parts = preg_split('/[;,]+/', $email);
        $email = trim((string) ($parts[0] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $email;
    }

    private function generateNoDocument(int $idCabang, Carbon $date): array
    {
        $tahun = $date->format('y');
        $bulanRomawi = $this->romawi((int) $date->format('m'));

        $last = QuotationNonKontrak::where('id_cabang', $idCabang)
            ->where('no_document', 'not like', '%R%')
            ->where('no_document', 'like', '%/' . $tahun . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $seq = 1;
        if ($last && $last->no_document) {
            $parts = explode('/', $last->no_document);
            if (count($parts) > 3) {
                $tahunBulan = $parts[2] ?? '';
                $tahunDoc = explode('-', $tahunBulan)[0] ?? null;
                if ((int) $tahunDoc === (int) $tahun) {
                    $seq = ((int) ($parts[3] ?? 0)) + 1;
                }
            }
        }

        $noQuotation = sprintf('%06d', $seq);
        $noDocument = 'ISL/QT/' . $tahun . '-' . $bulanRomawi . '/' . $noQuotation;

        $tries = 0;
        while (
            QuotationNonKontrak::where('no_document', $noDocument)->exists()
            && $tries < 50
        ) {
            $seq++;
            $noQuotation = sprintf('%06d', $seq);
            $noDocument = 'ISL/QT/' . $tahun . '-' . $bulanRomawi . '/' . $noQuotation;
            $tries++;
        }

        return [$noQuotation, $noDocument];
    }

    private function romawi(int $bulan): string
    {
        $map = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $idx = max(1, min(12, $bulan)) - 1;
        return $map[$idx];
    }
}
