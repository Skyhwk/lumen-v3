<?php

namespace App\Services;

use App\Models\DokumenSkppa;
use App\Models\Ftc;
use App\Models\LinkLhp;
use App\Models\OrderBerjalan;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\PengesahanLhp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Combine LHP PDF gabungan.
 *
 * Sumber daftar CFR / status rilis / file: order_berjalan.dataOrderDetail.
 * Tidak mewajibkan baris link_lhp. Metadata link_lhp di-sync untuk kompatibilitas.
 *
 * Perilaku lama (LinkLhp) diarsip di {@see CombineLHPServiceOri}.
 *
 * Paket lengkap (is_completed) = semua CFR rilis DAN semua PDF berhasil digabung.
 */
class CombineLHPService
{
    public const LOG_PREFIX = 'CombineLHP';

    /** @var string[] */
    private $trace = [];

    /**
     * Dry-run / preview: resolve snapshot + file PDF tanpa merge.
     */
    public function preview(string $noOrder, ?string $periode = null): array
    {
        return $this->run($noOrder, $periode, 'system', true);
    }

    /**
     * Signature lama (CombineLHPJob / pemanggil Draft*).
     * $noLhp / $fileLhp hanya untuk jejak trigger — daftar merge dari order_berjalan.
     */
    public function combine($noLhp, $fileLhp, $noOrder, $karyawan, $periode = null)
    {
        if (!$noOrder) {
            Log::error(self::LOG_PREFIX . ': No. Order is required');
            return [
                'ok' => false,
                'spot' => 'validation.no_order_required',
                'message' => 'No. Order wajib diisi.',
            ];
        }

        Log::info(self::LOG_PREFIX . ': combine triggered', [
            'no_order' => $noOrder,
            'periode' => $periode,
            'trigger_cfr' => $noLhp,
            'trigger_file' => $fileLhp,
            'karyawan' => $karyawan,
        ]);

        return $this->combineFromOrderBerjalan(
            (string) $noOrder,
            $periode !== null && $periode !== '' ? (string) $periode : null,
            (string) ($karyawan ?: 'system'),
            false
        );
    }

    /**
     * API eksplisit dari snapshot order_berjalan.
     *
     * @param  bool  $dryRun  true = hanya resolve + bentuk hasil; false = merge PDF ke disk
     */
    public function combineFromOrderBerjalan(string $noOrder, ?string $periode = null, string $karyawan = 'system', bool $dryRun = false): array
    {
        return $this->run($noOrder, $periode, $karyawan, $dryRun);
    }

    private function run(string $noOrder, ?string $periode, string $karyawan, bool $dryRun): array
    {
        $this->trace = [];
        $startedAt = microtime(true);
        $noOrder = trim($noOrder);
        $periode = $this->normalizePeriode($periode);

        $this->spot('start', [
            'no_order' => $noOrder,
            'periode' => $periode,
            'dry_run' => $dryRun,
            'karyawan' => $karyawan,
        ]);

        try {
            if ($noOrder === '') {
                return $this->fail('validation.no_order_required', 'No. Order wajib diisi.');
            }

            $orderHeader = OrderHeader::where('no_order', $noOrder)
                ->where('is_active', true)
                ->latest()
                ->first();

            if (!$orderHeader) {
                return $this->fail('validation.order_not_found', "Order tidak ditemukan (noOrder: {$noOrder}).");
            }

            // Cocokkan ID order header aktif bila ada revisi (order_berjalan.id = order_header.id)
            $orderBerjalan = OrderBerjalan::where('id', $orderHeader->id)->first();
            if (!$orderBerjalan) {
                $orderBerjalan = OrderBerjalan::where('no_order', $noOrder)
                    ->orderByDesc('updated_at')
                    ->first();
            }
            if (!$orderBerjalan) {
                return $this->fail(
                    'validation.order_berjalan_missing',
                    "Snapshot order_berjalan tidak ditemukan (noOrder: {$noOrder}, orderHeaderId: {$orderHeader->id})."
                );
            }
            $this->spot('snapshot.resolved', [
                'order_berjalan_id' => $orderBerjalan->id,
                'order_header_id' => $orderHeader->id,
                'matched_by_header_id' => (int) $orderBerjalan->id === (int) $orderHeader->id,
            ]);

            $isKontrak = $this->isKontrakOrder($orderBerjalan, $orderHeader);
            $this->spot('order.loaded', [
                'order_id' => $orderHeader->id,
                'no_document' => $orderHeader->no_document,
                'jenis_order' => $orderBerjalan->jenis_order,
                'is_kontrak' => $isKontrak,
            ]);

            if ($isKontrak && ($periode === null || $periode === '')) {
                return $this->fail(
                    'validation.kontrak_periode_required',
                    "Order KONTRAK/QTC wajib pakai periode (noOrder: {$noOrder})."
                );
            }

            $groups = $this->decodeDataOrderDetail($orderBerjalan->dataOrderDetail);
            if ($groups === null) {
                return $this->fail(
                    'validation.data_order_detail_invalid',
                    "dataOrderDetail bukan JSON/array valid (noOrder: {$noOrder})."
                );
            }

            $this->spot('snapshot.decoded', [
                'group_count' => count($groups),
                'periodes' => collect($groups)->map(fn ($g) => (string) ($g['periode'] ?? ''))->values()->all(),
            ]);

            $selectedGroups = $this->filterGroupsByPeriode($groups, $periode, $isKontrak);
            if (empty($selectedGroups)) {
                return $this->fail(
                    'validation.periode_group_empty',
                    $periode !== null && $periode !== ''
                        ? "Tidak ada group periode '{$periode}' di order_berjalan (noOrder: {$noOrder})."
                        : "dataOrderDetail kosong setelah filter (noOrder: {$noOrder})."
                );
            }

            $plan = $this->buildCombinePlan($selectedGroups, $noOrder, $periode);
            $this->spot('plan.built', [
                'jumlah_lhp' => $plan['jumlah_lhp'],
                'jumlah_lhp_rilis' => $plan['jumlah_lhp_rilis'],
                'list_lhp_rilis' => $plan['list_lhp_rilis'],
                'all_cfr_rilis' => $plan['all_cfr_rilis'],
                'all_files_exist' => $plan['all_files_exist'],
                'is_completed' => $plan['is_completed'],
                'files_found' => collect($plan['items'])->where('file_exists', true)->count(),
                'files_missing' => collect($plan['items'])->where('file_exists', false)->count(),
                'live_enriched' => $plan['live_enriched_cfrs'],
            ]);

            $finalDirectoryPath = public_path('laporan/hasil_pengujian');
            $finalFilename = ($periode !== null && $periode !== '')
                ? $noOrder . '_' . $periode . '.pdf'
                : $noOrder . '.pdf';
            $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

            $result = [
                'ok' => true,
                'dry_run' => $dryRun,
                'spot' => 'plan.ready',
                'no_order' => $noOrder,
                'periode' => $periode,
                'is_kontrak' => $isKontrak,
                'karyawan' => $karyawan,
                'final_filename' => $finalFilename,
                'final_path' => $finalFullPath,
                'plan' => $plan,
                'merge' => null,
                'link_lhp' => null,
                'skppa' => null,
                'elapsed_ms' => null,
                'trace' => [],
            ];

            if ($plan['jumlah_lhp_rilis'] === 0) {
                $this->spot('plan.empty_rilis', ['message' => 'Tidak ada detail dengan lhp_rilis=true.']);
                $result['spot'] = 'plan.empty_rilis';
                $result['ok'] = false;
                $result['message'] = 'Tidak ada LHP dengan lhp_rilis=true untuk digabung.';
                $result['elapsed_ms'] = $this->elapsedMs($startedAt);
                $result['trace'] = $this->trace;

                return $result;
            }

            $missingFiles = collect($plan['items'])->where('file_exists', false)->values()->all();
            if (!empty($missingFiles)) {
                $this->spot('plan.missing_files', [
                    'count' => count($missingFiles),
                    'cfrs' => collect($missingFiles)->pluck('cfr')->all(),
                ]);
            }

            if ($dryRun) {
                $this->spot('dry_run.done', ['final_filename' => $finalFilename]);
                $result['spot'] = 'dry_run.done';
                $result['message'] = 'Dry-run OK. Pakai --execute untuk merge PDF.';
                $result['elapsed_ms'] = $this->elapsedMs($startedAt);
                $result['trace'] = $this->trace;

                return $result;
            }

            // Lock per order+periode agar approve berurutan/bersamaan tidak menimpa daftar lebih lengkap
            $lockKey = 'combine-lhp:' . $noOrder . ':' . ($periode ?? '_');
            $lock = null;
            try {
                $lock = Cache::lock($lockKey, 180);
                if (!$lock->block(45)) {
                    return $this->fail(
                        'concurrency.lock_timeout',
                        "Combine sedang berjalan untuk order {$noOrder}" . ($periode ? " periode {$periode}" : '') . '.'
                    );
                }
            } catch (\Throwable $lockError) {
                $this->spot('concurrency.lock_unavailable', [
                    'message' => $lockError->getMessage(),
                    'note' => 'Lanjut tanpa lock (cache driver tidak mendukung).',
                ]);
                $lock = null;
            }

            try {
                // Rebuild plan di dalam lock (baca ulang snapshot + enrich live)
                $orderBerjalan = OrderBerjalan::where('id', $orderBerjalan->id)->first() ?: $orderBerjalan;
                $groups = $this->decodeDataOrderDetail($orderBerjalan->dataOrderDetail) ?? [];
                $selectedGroups = $this->filterGroupsByPeriode($groups, $periode, $isKontrak);
                $plan = $this->buildCombinePlan($selectedGroups, $noOrder, $periode);
                $missingFiles = collect($plan['items'])->where('file_exists', false)->values()->all();

                $attachable = collect($plan['items'])->where('file_exists', true)->values();
                if ($attachable->isEmpty()) {
                    return $this->fail(
                        'merge.no_pdf_on_disk',
                        'Semua file PDF LHP rilis tidak ditemukan di dokumen/LHP_DOWNLOAD.'
                    );
                }

                // Jangan timpa PDF valid sebelumnya dengan hasil kosong bila partial tanpa file
                $previousBytes = File::exists($finalFullPath) ? File::size($finalFullPath) : 0;

                if (!File::isDirectory($finalDirectoryPath)) {
                    File::makeDirectory($finalDirectoryPath, 0777, true);
                    $this->spot('merge.dir_created', ['path' => $finalDirectoryPath]);
                }

                $this->spot('merge.start', [
                    'attach_count' => $attachable->count(),
                    'combiner' => env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge',
                ]);

                $httpClient = Http::asMultipart();
                $fileMetadata = [];

                foreach ($attachable as $item) {
                    $httpClient->attach('pdfs[]', File::get($item['path']), $item['file']);
                    $fileMetadata[] = 'skyhwk12';
                }

                $httpClient->attach('metadata', json_encode($fileMetadata));
                $response = $httpClient->post(env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge');

                if (!$response->successful()) {
                    throw new \RuntimeException(
                        'Python PDF Service failed (' . $response->status() . '): ' . $response->body()
                    );
                }

                $body = $response->body();
                if ($body === '' || $body === null) {
                    throw new \RuntimeException('Python PDF Service mengembalikan body kosong.');
                }

                File::put($finalFullPath, $body);
                $bytes = File::exists($finalFullPath) ? File::size($finalFullPath) : 0;

                if ($bytes <= 0 && $previousBytes > 0) {
                    $this->spot('merge.preserve_previous', [
                        'note' => 'Output baru kosong; PDF sebelumnya dipertahankan.',
                        'previous_bytes' => $previousBytes,
                    ]);
                }

                // Paket lengkap = semua CFR rilis + semua PDF ikut merge
                $paketLengkap = $plan['all_cfr_rilis'] && empty($missingFiles) && $bytes > 0;
                $plan['is_completed'] = $paketLengkap;

                $this->spot('merge.done', [
                    'final_path' => $finalFullPath,
                    'bytes' => $bytes,
                    'paket_lengkap' => $paketLengkap,
                    'is_completed' => $paketLengkap,
                ]);

                $linkSync = $this->syncLinkLhpMetadata(
                    $noOrder,
                    $periode,
                    $plan,
                    $finalFilename,
                    $karyawan,
                    $paketLengkap
                );
                $result['link_lhp'] = $linkSync;

                $skppaResult = null;
                if ($paketLengkap) {
                    $this->spot('post.skppa.start', ['note' => 'Semua CFR rilis + semua PDF masuk merge.']);
                    $skppaOk = $this->generateSuratKeterangan($noOrder, $periode);
                    $skppaResult = ['ok' => (bool) $skppaOk];
                    $this->spot('post.skppa.done', $skppaResult);
                } else {
                    $this->spot('post.partial', [
                        'note' => 'Combine partial — belum paket lengkap. SKPPA ditunda. PDF valid sebelumnya tetap tersedia.',
                        'pending_cfrs' => $plan['pending_cfrs'],
                        'missing_files' => collect($missingFiles)->pluck('cfr')->values()->all(),
                    ]);
                }
                $result['skppa'] = $skppaResult;

                $result['spot'] = 'merge.done';
                $result['ok'] = true;
                $result['message'] = $paketLengkap
                    ? 'Combine PDF berhasil (paket lengkap).'
                    : $this->buildPartialMessage($plan['pending_cfrs'] ?? [], $missingFiles);
                $result['plan'] = $plan;
                $result['merge'] = [
                    'attached' => $attachable->pluck('cfr')->values()->all(),
                    'attached_files' => $attachable->pluck('file')->values()->all(),
                    'skipped_missing' => collect($missingFiles)->pluck('cfr')->values()->all(),
                    'output' => $finalFullPath,
                    'bytes' => $bytes,
                    'paket_lengkap' => $paketLengkap,
                    'combined_at' => Carbon::now()->toDateTimeString(),
                ];
                $result['elapsed_ms'] = $this->elapsedMs($startedAt);
                $result['trace'] = $this->trace;

                return $result;
            } finally {
                optional($lock)->release();
            }
        } catch (\Throwable $e) {
            Log::error(self::LOG_PREFIX . ' exception', [
                'no_order' => $noOrder,
                'periode' => $periode,
                'message' => $e->getMessage(),
            ]);
            Log::error($e);

            return $this->fail('exception', $e->getMessage(), [
                'exception_class' => get_class($e),
            ]);
        }
    }

    private function isKontrakOrder(OrderBerjalan $orderBerjalan, OrderHeader $orderHeader): bool
    {
        $jenis = strtoupper((string) ($orderBerjalan->jenis_order ?? ''));
        if ($jenis === 'KONTRAK') {
            return true;
        }

        return str_contains((string) $orderHeader->no_document, 'QTC');
    }

    /**
     * Sync metadata ke link_lhp agar StatusOrder / rekap lama tetap jalan.
     * Sumber kebenaran list tetap order_berjalan; link_lhp hanya mirror.
     */
    private function syncLinkLhpMetadata(
        string $noOrder,
        ?string $periode,
        array $plan,
        string $finalFilename,
        string $karyawan,
        bool $paketLengkap
    ): array {
        $query = LinkLhp::where('no_order', $noOrder)->where('is_active', true);
        if ($periode !== null && $periode !== '') {
            $query->where('periode', $periode);
        } else {
            $query->where(function ($q) {
                $q->whereNull('periode')->orWhere('periode', '');
            });
        }

        $linkLhp = $query->latest()->first();
        if (!$linkLhp) {
            $this->spot('link_lhp.missing', [
                'note' => 'Tidak ada baris link_lhp aktif untuk sync metadata.',
            ]);

            return ['synced' => false, 'reason' => 'not_found'];
        }

        DB::beginTransaction();
        try {
            $locked = LinkLhp::where('id', $linkLhp->id)->lockForUpdate()->first();
            if (!$locked) {
                DB::rollBack();
                return ['synced' => false, 'reason' => 'row_gone'];
            }

            $existingList = json_decode($locked->list_lhp_rilis ?: '[]', true);
            if (!is_array($existingList)) {
                $existingList = [];
            }

            // Union + natural sort — jangan pernah mengecilkan daftar rilis akibat race
            $mergedList = array_values(array_unique(array_merge(
                array_map('strval', $existingList),
                array_map('strval', $plan['list_lhp_rilis'] ?? [])
            )));
            sort($mergedList, SORT_NATURAL);

            $jumlahLhp = max((int) $locked->jumlah_lhp, (int) $plan['jumlah_lhp']);
            $jumlahRilis = count($mergedList);

            $locked->list_lhp_rilis = json_encode($mergedList);
            $locked->jumlah_lhp_rilis = $jumlahRilis;
            $locked->jumlah_lhp = $jumlahLhp;
            // is_completed hanya naik ke true saat paket lengkap (rilis + PDF merge sukses).
            // Partial tidak menandai lengkap; nilai true yang sudah ada tidak diturunkan di sini.
            if ($paketLengkap) {
                $locked->is_completed = 1;
            }
            $locked->filename = $finalFilename;
            $locked->updated_by = $karyawan;
            $locked->updated_at = Carbon::now();
            $locked->save();
            DB::commit();

            $this->spot('link_lhp.synced', [
                'id' => $locked->id,
                'jumlah_lhp' => $locked->jumlah_lhp,
                'jumlah_lhp_rilis' => $locked->jumlah_lhp_rilis,
                'is_completed' => (bool) $locked->is_completed,
                'filename' => $locked->filename,
                'union_count' => $jumlahRilis,
            ]);

            return [
                'synced' => true,
                'id' => $locked->id,
                'is_completed' => (bool) $locked->is_completed,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->spot('link_lhp.sync_failed', ['message' => $e->getMessage()]);

            return ['synced' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Normalisasi dataOrderDetail → list group [{periode, detail: [...], ...}, ...]
     */
    private function decodeDataOrderDetail($raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        // Bentuk: { detail: [...], periode: "" } → bungkus jadi 1 group
        if (array_key_exists('detail', $decoded) && $this->isAssoc($decoded)) {
            return [$decoded];
        }

        // Bentuk: [ { detail: [...], periode: "" }, ... ]
        if ($this->isList($decoded)) {
            return array_values($decoded);
        }

        return null;
    }

    /**
     * NORMAL: tanpa periode → semua group.
     * KONTRAK/QTC: wajib cocokkan periode group.
     */
    private function filterGroupsByPeriode(array $groups, ?string $periode, bool $isKontrak): array
    {
        if ($periode === null || $periode === '') {
            if (!$isKontrak) {
                $nonEmptyPeriodes = collect($groups)
                    ->map(fn ($g) => trim((string) ($g['periode'] ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                if ($nonEmptyPeriodes->count() > 1) {
                    $this->spot('filter.multi_periode_without_arg', [
                        'periodes' => $nonEmptyPeriodes->all(),
                        'note' => 'Snapshot punya >1 periode. Disarankan kirim periode. Sementara ambil semua.',
                    ]);
                }

                return array_values($groups);
            }

            return [];
        }

        return array_values(array_filter($groups, function ($group) use ($periode) {
            return (string) ($group['periode'] ?? '') === (string) $periode;
        }));
    }

    private function buildCombinePlan(array $groups, string $noOrder, ?string $periode): array
    {
        $details = collect($groups)
            ->flatMap(fn ($group) => $group['detail'] ?? [])
            ->filter(fn ($detail) => !empty($detail['cfr']))
            ->unique('cfr')
            ->values();

        // Enrich live: snapshot bisa stale vs OrderDetail yang baru di-approve
        $liveEnriched = [];
        $details = $details->map(function ($detail) use ($noOrder, &$liveEnriched) {
            if (!empty($detail['lhp_rilis'])) {
                return $detail;
            }

            $cfr = (string) $detail['cfr'];
            $approved = OrderDetail::where('no_order', $noOrder)
                ->where('cfr', $cfr)
                ->where('is_active', true)
                ->where('status', 3)
                ->exists();

            if ($approved) {
                $detail['lhp_rilis'] = true;
                if (empty($detail['tgl_lhp_rilis'])) {
                    $detail['tgl_lhp_rilis'] = Carbon::now()->format('Y-m-d');
                }
                $liveEnriched[] = $cfr;
            }

            return $detail;
        });

        $rilisDetails = $details
            ->filter(fn ($detail) => (bool) ($detail['lhp_rilis'] ?? false))
            ->sortBy('cfr', SORT_NATURAL)
            ->values();

        $resolver = new ResolveLhpFile();
        $items = [];

        foreach ($rilisDetails as $detail) {
            $cfr = (string) $detail['cfr'];
            $noSampel = $this->extractNoSampel($detail);
            $file = $this->resolvePdfFilename($resolver, $noOrder, $cfr, $noSampel, $detail);
            $path = $file ? public_path('dokumen/LHP_DOWNLOAD/' . $file) : null;
            $exists = $file && $path && File::exists($path);

            $items[] = [
                'cfr' => $cfr,
                'no_sampel' => $noSampel,
                'kategori_3' => $detail['kategori_3'] ?? null,
                'tgl_lhp_rilis' => $detail['tgl_lhp_rilis'] ?? null,
                'lhp_rilis' => true,
                'file' => $file,
                'path' => $path,
                'file_exists' => $exists,
            ];
        }

        $listLhpRilis = collect($items)->pluck('cfr')->values()->all();
        $jumlahLhp = $details->count();
        $jumlahLhpRilis = count($listLhpRilis);
        $allCfrRilis = $jumlahLhp > 0 && $jumlahLhp === $jumlahLhpRilis;
        $allFilesExist = $jumlahLhpRilis > 0 && collect($items)->every(fn ($i) => $i['file_exists']);

        return [
            'periode' => $periode,
            'jumlah_lhp' => $jumlahLhp,
            'jumlah_lhp_rilis' => $jumlahLhpRilis,
            'list_lhp_rilis' => $listLhpRilis,
            'all_cfr_rilis' => $allCfrRilis,
            'all_files_exist' => $allFilesExist,
            // is_completed / paket lengkap = semua CFR rilis + semua PDF ada di disk
            'is_completed' => $allCfrRilis && $allFilesExist,
            'items' => $items,
            'pending_cfrs' => $details
                ->reject(fn ($d) => (bool) ($d['lhp_rilis'] ?? false))
                ->pluck('cfr')
                ->values()
                ->all(),
            'live_enriched_cfrs' => $liveEnriched,
        ];
    }

    private function resolvePdfFilename(
        ResolveLhpFile $resolver,
        string $noOrder,
        string $cfr,
        ?string $noSampel,
        array $detail = []
    ): ?string {
        $dir = public_path('dokumen/LHP_DOWNLOAD/');

        // 0) Snapshot detail.file_lhp (sudah dinormalisasi dari file_lhp/no_dokumen/name_file)
        if (!empty($detail['file_lhp'])) {
            $fromSnapshot = (string) $detail['file_lhp'];
            if (File::exists($dir . $fromSnapshot)) {
                return $fromSnapshot;
            }
        }

        // 1) Header LHP live (file_lhp / no_dokumen / name_file)
        $fromDb = $resolver->byCfr($noOrder, $cfr, $noSampel);
        if (!empty($fromDb) && File::exists($dir . $fromDb)) {
            return $fromDb;
        }

        // 2) Konvensi lama — hanya setelah terverifikasi ada di disk
        $conventional = 'LHP-' . str_replace('/', '-', $cfr) . '.pdf';
        if (File::exists($dir . $conventional)) {
            return $conventional;
        }

        // Nama diketahui tapi file hilang — kembalikan nama untuk logging missing
        if (!empty($fromDb)) {
            return $fromDb;
        }
        if (!empty($detail['file_lhp'])) {
            return (string) $detail['file_lhp'];
        }

        // Tidak ada nama terverifikasi
        return null;
    }

    private function extractNoSampel(array $detail): ?string
    {
        if (!empty($detail['sampelNumbers'][0])) {
            return (string) $detail['sampelNumbers'][0];
        }

        if (!empty($detail['hasil_uji'][0]['no_sampel'])) {
            return (string) $detail['hasil_uji'][0]['no_sampel'];
        }

        // Banyak CFR air = no_sampel
        return isset($detail['cfr']) ? (string) $detail['cfr'] : null;
    }

    private function normalizePeriode(?string $periode): ?string
    {
        if ($periode === null) {
            return null;
        }

        $periode = trim($periode);

        return $periode === '' ? null : $periode;
    }

    /**
     * Pesan partial yang eksplisit — hindari asumsi "file missing" bila penyebabnya CFR belum rilis.
     */
    private function buildPartialMessage(array $pendingCfrs, array $missingFiles): string
    {
        $pendingCount = count($pendingCfrs);
        $missingCount = count($missingFiles);
        $parts = [];

        if ($pendingCount > 0) {
            $parts[] = "{$pendingCount} CFR belum rilis";
        }

        if ($missingCount > 0) {
            $missingCfrs = collect($missingFiles)->pluck('cfr')->filter()->values()->all();
            $parts[] = $missingCfrs
                ? ('file PDF tidak ditemukan untuk: ' . implode(', ', $missingCfrs))
                : ("{$missingCount} file PDF tidak ditemukan");
        }

        if (empty($parts)) {
            return 'Combine PDF partial — belum paket lengkap (SKPPA ditunda).';
        }

        return 'Combine PDF partial — ' . implode('; ', $parts) . ' (SKPPA ditunda).';
    }

    private function spot(string $name, array $context = []): void
    {
        $entry = array_merge([
            'spot' => $name,
            'at' => Carbon::now()->toDateTimeString(),
        ], $context);

        $this->trace[] = $entry;

        Log::info(self::LOG_PREFIX . ' [' . $name . ']', $context);
    }

    private function fail(string $spot, string $message, array $extra = []): array
    {
        $this->spot($spot, array_merge(['message' => $message], $extra));

        return array_merge([
            'ok' => false,
            'dry_run' => null,
            'spot' => $spot,
            'message' => $message,
            'trace' => $this->trace,
        ], $extra);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }

    public function testRendersuratKeterangan($no_order, $periode = null){
        $this->generateSuratKeterangan($no_order, $periode);
    }

    public function generateSuratKeterangan($no_order, $periode = null)
    {
        try {
            $order = OrderHeader::with(['orderDetail','invoices'])->where('no_order', $no_order)->first();

            $yearFull = date('Y');
            $yearShort = date('y');

            // Ambil terakhir di tahun yang sama
            $last = DokumenSkppa::whereYear('generate_at', $yearFull)
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 1;

            if ($last) {
                // contoh: ISL-04-SKET/260001
                $explode = explode('/', $last->no_document);

                if (isset($explode[1])) {
                    $lastCode = $explode[1]; // 260001
                    $lastNumber = (int) substr($lastCode, 2); // ambil 0001 → 1
                    $nextNumber = $lastNumber + 1;
                }
            }

            $dataDetail = $order->orderDetail;
            $no_sampel = $dataDetail->where('periode', $periode)->where('is_active', 1)->pluck('no_sampel')->toArray();
            $tanggal_sampling = $dataDetail->where('periode', $periode)->where('is_active', 1)->pluck('tanggal_sampling')->toArray();
            $tanggal_terima = $dataDetail->where('periode', $periode)->where('is_active', 1)->pluck('tanggal_terima')->toArray();

            // Sesudah
            $cekTracking = Ftc::whereIn('no_sample', $no_sampel)
                ->selectRaw('CAST(ftc_laboratory AS DATE) as ftc_laboratory')
                ->pluck('ftc_laboratory')
                ->toArray();

            $cekTracking = array_filter($cekTracking);
            sort($cekTracking);

            $tanggal_analisa_awal  = !empty($cekTracking) ? $cekTracking[0] : null;

            // Ambil tgl_release_lhp terakhir dari semua tabel LHP Header
            $lhpModels = [
                \App\Models\LhpsAirHeader::class,
                \App\Models\LhpsEmisiCHeader::class,
                \App\Models\LhpsEmisiHeader::class,
                \App\Models\LhpsEmisiIsokinetikHeader::class,
                \App\Models\LhpsErgonomiHeader::class,
                \App\Models\LhpsGetaranHeader::class,
                \App\Models\LhpsHygieneSanitasiHeader::class,
                \App\Models\LhpsIklimHeader::class,
                \App\Models\LhpsKebisinganHeader::class,
                \App\Models\LhpsKebisinganPersonalHeader::class,
                \App\Models\LhpsLingHeader::class,
                \App\Models\LhpsMedanLMHeader::class,
                \App\Models\LhpsMicrobiologiHeader::class,
                \App\Models\LhpsPadatanHeader::class,
                \App\Models\LhpsPencahayaanHeader::class,
                \App\Models\LhpsSinarUVHeader::class,
                \App\Models\LhpsSwabTesHeader::class,
                \App\Models\LhpUdaraPsikologiHeader::class, // tanpa 's' sesuai nama file
            ];

            $allTanggalRelease = [];
            foreach ($lhpModels as $model) {
                $instance = new $model;
                $kolom = \Schema::hasColumn($instance->getTable(), 'tanggal_lhp') 
                    ? 'tanggal_lhp' 
                    : 'tanggal_rilis_lhp';

                $tanggal = $model::where('no_order', $no_order)
                    ->whereNotNull($kolom)
                    ->pluck($kolom)
                    ->map(fn($t) => Carbon::parse($t)->toDateString())
                    ->toArray();

                $allTanggalRelease = array_merge($allTanggalRelease, $tanggal);
            }

            $allTanggalRelease = array_filter(array_unique($allTanggalRelease));
            sort($allTanggalRelease);

            $tanggal_analisa_akhir = !empty($allTanggalRelease) ? end($allTanggalRelease) : null;

            // Jika awal dan akhir sama, kosongkan akhir
            if ($tanggal_analisa_awal === $tanggal_analisa_akhir) {
                $tanggal_analisa_akhir = null;
            }

            $tanggal_sampling = array_filter($tanggal_sampling);
            sort($tanggal_sampling);
            if (!empty($tanggal_sampling)) {
                $tanggal_sampling_awal = $tanggal_sampling[0];
                $tanggal_sampling_akhir = $tanggal_sampling[count($tanggal_sampling) - 1];
                if ($tanggal_sampling_awal == $tanggal_sampling_akhir) {
                    $tanggal_sampling_akhir = null;
                }
            } else {
                $tanggal_sampling_awal = null;
                $tanggal_sampling_akhir = null;
            }

            $tanggal_terima = array_filter($tanggal_terima); // Hilangkan nilai null/false
            sort($tanggal_terima);
            if (!empty($tanggal_terima)) {
                $tanggal_terima_awal = $tanggal_terima[0];
                $tanggal_terima_akhir = $tanggal_terima[count($tanggal_terima) - 1];
                if ($tanggal_terima_awal == $tanggal_terima_akhir) {
                    $tanggal_terima_akhir = null;
                }
            } else {
                $tanggal_terima_awal = null;
                $tanggal_terima_akhir = null;
            }

            // gabung YY + 4 digit
            $number = $yearShort . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $no_document = "ISL-04-SKET/{$number}";

            $no_po = '-';

            if($periode != null) {
                $check_periode = $order->invoices->where('periode', $periode)->first();
                if($check_periode) {
                    $no_po = $check_periode->no_po;
                } else {
                    $check_periode = $order->invoices->where('periode', "all")->first();
                    if($check_periode) {
                        $no_po = $check_periode->no_po;
                    }
                }
            } else {
                $no_po = $order->invoices->first()->no_po;
            }

            if(empty($no_po)) {
                $no_po = '-';
            }

            $pengesah = PengesahanLhp::where('berlaku_mulai', '<=', $tanggal_analisa_akhir)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $cek_skppa = DokumenSkppa::where('no_order', $no_order)->where('periode', $periode)->first();
            if($cek_skppa) {
                return true;
            } else {
                $skppa = new DokumenSkppa();
                $skppa->id_order = $order->id;
                $skppa->no_order = $order->no_order;
                $skppa->tanggal_order = $order->tanggal_order;
                $skppa->no_quotation = $order->no_document;
                $skppa->tanggal_penawaran = $order->tanggal_penawaran;
                $skppa->periode = $periode;
                $skppa->no_document = $no_document;
                $skppa->tanggal_rilis = Carbon::now()->format('Y-m-d');
                $skppa->filename = str_replace('/', '_', $no_document) . '.pdf';
                $skppa->id_pelanggan = $order->id_pelanggan;
                $skppa->nama_perusahaan = $order->nama_perusahaan;
                $skppa->alamat_perusahaan = $order->alamat_kantor;
                $skppa->alamat_sampling = $order->alamat_sampling;
                $skppa->nama_pengesah = $pengesah->nama_karyawan;
                $skppa->jabatan_pengesah = $pengesah->jabatan_karyawan;
                $skppa->no_po = $no_po;

                $details = $order->orderDetail->when($periode, fn($q) => $q->where('periode', $periode));
                
                $skppa->total_sampel = $details->count();
                $skppa->total_lhp = $details->pluck('cfr')->filter()->unique()->count();
                $skppa->tanggal_sampling = $details->min('tanggal_sampling');
                $skppa->subkategori = json_encode($details->groupBy('kategori_3')->map(fn($items, $kategori) => ['kategori' => $kategori, 'jumlah' => $items->count()])->values()->toArray());
                $skppa->kategori = json_encode($details->pluck('kategori_1')->filter()->unique()->toArray());
                $skppa->tanggal_sampling_awal = $tanggal_sampling_awal;
                $skppa->tanggal_sampling_akhir = $tanggal_sampling_akhir;
                $skppa->tanggal_sampel_diterima_awal = $tanggal_terima_awal;
                $skppa->tanggal_sampel_diterima_akhir = $tanggal_terima_akhir;
                $skppa->tanggal_penyelesaian_analisa_awal = $tanggal_analisa_awal;
                $skppa->tanggal_penyelesaian_analisa_akhir = $tanggal_analisa_akhir;
                $skppa->generate_at = Carbon::now()->format('Y-m-d H:i:s');
                $skppa->generate_by = 'system';
                $skppa->save();
            }
            
            $filename = \str_replace("/", "_", $skppa->no_document);
            $path = public_path() . "/qr_documents/" . $filename . '.svg';
            if (!file_exists($path)) {
                $link = 'https://www.intilab.com/validation/';
                $unique = 'isldc' . (int) floor(microtime(true) * 1000);

                QrCode::size(200)->generate($link . $unique, $path);
                $dataQr = [
                    'type_document' => 'skppa',
                    'kode_qr' => $unique,
                    'file' => $filename,
                    'data' => json_encode([
                        'no_document' => $skppa->no_document,
                        'nama_customer' => $order->nama_perusahaan,
                        'type_document' => 'Surat Keterangan Penyelesaian Pekerjaan Analisa',
                        'Tanggal_Pengesahan' => Carbon::parse($tanggal_analisa_akhir)->locale('id')->isoFormat('DD MMMM YYYY'),
                        'Disahkan_Oleh' => $pengesah->nama_karyawan,
                        'Jabatan' => $pengesah->jabatan_karyawan
                    ]),
                    'created_at' => Carbon::now(),
                    'created_by' => 'System',
                ];

                DB::table('qr_documents')->insert($dataQr);
                // self::generatePDF($request->no_invoice);
            }

            $render = new RenderDokumenSkppa();

            $fileName = $render->execute($skppa, public_path() . '/qr_documents/' . $filename . '.svg');

            $skppa->filename = $fileName;
            $skppa->save();


            return true;
        } catch (\Throwable $th) {
            //throw $th;
            return false;
        }
    }

}
