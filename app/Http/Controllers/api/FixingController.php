<?php

namespace App\Http\Controllers\api;

use App\Helpers\Fixing;
use App\Helpers\Slice;
use App\Http\Controllers\Controller;
use App\Jobs\RenderPdfPenawaran;
use App\Models\AksesMenu;
use App\Models\ExpiredLink;
use App\Models\GenerateLink;
use App\Models\HistoryLevelSampler;
use App\Models\Invoice;
use App\Models\Jadwal;
use App\Models\JobTask;
use App\Models\LinkLhp;
use App\Models\MasterFeeSampling;
use App\Models\MasterKaryawan;
use App\Models\Menu;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterPelanggan;
use App\Models\PengajuanFeeSampling;
use App\Models\PengajuanFeeSamplingDetail;
use App\Models\QrDocument;
use App\Models\RecordPembayaranInvoice;
use App\Models\SalesInDetail;
use App\Models\SummaryInvoice;
use App\Models\TemplateAkses;
use App\Models\Withdraw;

// model LHP
use App\Models\LhpsAdverseOdorHeader;
use App\Models\LhpsAirHeader;
use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsEmisiIsokinetikHeader;
use App\Models\LhpsErgonomiHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsHygieneSanitasiHeader;
use App\Models\LhpsIklimHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsKebisinganPersonalHeader;
use App\Models\LhpsLingHeader;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMicrobiologiHeader;
use App\Models\LhpsPadatanHeader;
use App\Models\LhpsPencahayaanHeader;
use App\Models\LhpsSinarUVHeader;
use App\Models\LhpsSwabTesHeader;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\NewRecruitment;
use App\Models\SalaryAdjustmentRequest;
use App\Services\SalaryAdjustmentEmailService;
use App\Services\SalaryAdjustmentWorkflowService;
use App\Services\SalaryAdjustmentLogService;
use App\Services\EmployeeAdjustmentTypeRegistry;
use App\Services\GenerateAssessmentDocumentService;
use App\Services\CandidateDocumentAttachmentService;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateToken;
use App\Services\SallaryOfferService;
use App\Services\SendEmail;
// end model LHP


use App\Services\GenerateFeeSampling;
use App\Services\DisiplinerPdfFixingService;
use App\Services\FdlBasTimingExportService;
use App\Services\RenderInvoice;
use App\Services\RenderInvoiceTitik;
use App\Services\RenderJadwalKontrakCopy;
use App\Services\RenderKontrakCopy;
use App\Services\RenderNonKontrakCopy;
use App\Services\ResolveLhpFile;
use App\Services\SalesKpiMonthly;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FixingController extends Controller
{
    public function previewTrackingSamplerHistory(Request $request)
    {
        return $this->backfillTrackingSamplerHistory($request, true);
    }

    public function saveTrackingSamplerHistory(Request $request)
    {
        return $this->backfillTrackingSamplerHistory($request, false);
    }

    private function backfillTrackingSamplerHistory(Request $request, bool $preview)
    {
        if (!$this->user_id || (int) $this->department !== 7
            || in_array((int) $this->user_id, [10, 15, 93, 123], true)) {
            abort(403, 'Backfill history hanya untuk IT Programmer.');
        }
        $this->validate($request, ['session_id' => 'required|integer|min:1']);
        $result = DB::transaction(function () use ($request, $preview) {
            $result = (new \App\Services\SamplerTrackingJourneyBackfill())->run((int) $request->session_id, $preview);
            if (!$preview) {
                \App\Services\SamplerTrackingHistory::append((int) $request->session_id, 'journey_backfill_requested', [
                    'actor' => $this->karyawan ?: 'Programmer', 'actor_id' => (int) $this->user_id,
                    'source' => 'fixing', 'entries_added' => $result['entries_added'],
                    'summary' => 'Backfill detail per session melalui Fixing: ' . $result['entries_added'] . ' catatan ditambahkan.',
                ]);
            }
            return $result;
        });
        return response()->json([
            'success' => true, 'preview' => $preview,
            'message' => $preview ? 'Preview selesai; belum ada data yang disimpan.' : 'Meta history session berhasil dilengkapi.',
            'data' => $result,
        ]);
    }

    public function searchTrackingSamplerRoute(Request $request)
    {
        $this->validate($request, ['q' => 'required|string|min:2|max:100']);

        $samplers = \App\Models\MasterKaryawan::query()
            ->where('is_active', 1)
            ->where('nama_lengkap', 'like', '%' . trim($request->q) . '%')
            ->orderBy('nama_lengkap')
            ->limit(20)
            ->get(['id', 'nama_lengkap']);

        return response()->json(['success' => true, 'data' => $samplers]);
    }

    public function previewTrackingSamplerRoute(Request $request)
    {
        $this->validate($request, [
            'tanggal' => 'required|date_format:Y-m-d',
            'sampler_id' => 'required|integer',
        ]);

        $sampler = \App\Models\MasterKaryawan::where('is_active', 1)->findOrFail($request->sampler_id);
        $service = new \App\Services\SamplerTrackingService();
        $sessions = $service->listByDate($request->tanggal, $sampler->id, $sampler->nama_lengkap);

        return response()->json([
            'success' => true,
            'sampler' => ['id' => $sampler->id, 'name' => $sampler->nama_lengkap],
            'data' => $this->trackingSamplerRouteStops($sessions, $sampler->id),
        ]);
    }

    public function saveTrackingSamplerRoute(Request $request)
    {
        $this->validate($request, [
            'tanggal' => 'required|date_format:Y-m-d',
            'sampler_id' => 'required|integer',
            'reason' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.session_id' => 'required|integer',
        ]);

        $sampler = \App\Models\MasterKaryawan::where('is_active', 1)->findOrFail($request->sampler_id);
        $service = new \App\Services\SamplerTrackingService();
        $sessions = $service->updateRouteOrder([
            'sampler_id' => $sampler->id,
            'sampler_name' => $sampler->nama_lengkap,
            'reason' => $request->reason,
            'items' => $request->items,
        ], $this->karyawan ?: 'Programmer', true, $request->tanggal);

        return response()->json([
            'success' => true,
            'message' => 'Urutan tujuan tim berhasil diperbarui.',
            'data' => $this->trackingSamplerRouteStops($sessions, $sampler->id),
        ]);
    }

    public function previewMissingTrackingSamplerReturn(Request $request)
    {
        $this->validate($request, [
            'tanggal' => 'required|date_format:Y-m-d',
            'sampler_id' => 'required|integer',
        ]);

        $sampler = MasterKaryawan::where('is_active', 1)->findOrFail($request->sampler_id);
        $sessions = \App\Models\SamplerTrackingSession::with(['activeMembers.events'])
            ->whereDate('tanggal_sampling', $request->tanggal)
            ->whereHas('activeMembers', function ($query) use ($sampler) {
                $query->where('sampler_id', $sampler->id);
            })
            ->orderBy('jam_mulai')
            ->get();

        $data = $sessions->map(function ($session) use ($sampler) {
            $member = $session->activeMembers->first(function ($item) use ($sampler) {
                return (int) $item->sampler_id === (int) $sampler->id;
            });
            $events = $member ? $member->events : collect();
            $checkout = $events->where('event_type', 'checkout')->sortByDesc('event_at')->first();

            return [
                'session_id' => $session->id,
                'member_id' => $member ? $member->id : null,
                'nama_perusahaan' => $session->nama_perusahaan,
                'no_order' => $session->no_order,
                'tanggal_sampling' => $session->tanggal_sampling,
                'checkout_at' => $checkout ? $checkout->event_at : null,
                'return_at' => $events->firstWhere('event_type', 'return')->event_at ?? null,
                'can_fix' => (bool) ($session->is_active && $member && $checkout
                    && !$events->contains('event_type', 'return')),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function createMissingTrackingSamplerReturn(Request $request)
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');
        $this->validate($request, [
            'session_id' => 'required|integer',
            'sampler_id' => 'required|integer',
            'event_at' => 'required|date_format:Y-m-d H:i:s',
            'note' => 'required|string|max:1000',
        ]);

        $event = (new \App\Services\SamplerTrackingService())->createMissingReturn(
            $request->session_id,
            $request->sampler_id,
            $request->event_at,
            $request->note,
            $this->user_id,
            $this->karyawan ?: 'Programmer'
        );

        return response()->json([
            'success' => true,
            'message' => 'Event Pulang tanpa foto berhasil dicatat.',
            'data' => ['event_id' => $event->id, 'event_at' => $event->event_at, 'note' => $event->note],
        ]);
    }

    protected function trackingSamplerRouteStops($sessions, $samplerId)
    {
        return $sessions->map(function ($session) use ($samplerId) {
            $member = $session->activeMembers->first(function ($item) use ($samplerId) {
                return (int) $item->sampler_id === (int) $samplerId;
            });
            $events = $member ? $member->events->pluck('event_type')->unique()->values()->all() : [];

            return [
                'session_id' => (int) $session->id,
                'no_order' => $session->no_order,
                'nama_perusahaan' => $session->nama_perusahaan,
                'jam_mulai' => $session->jam_mulai,
                'jam_selesai' => $session->jam_selesai,
                'events' => $events,
            ];
        })->values();
    }

    public function repairWsFinalApprovalEmptyResults(Request $request)
    {
        try {
            $result = \App\Services\WsFinalApprovalService::repairEmptyDetailResults([
                'dry_run' => $request->input('dry_run', true),
                'limit' => $request->input('limit'),
                'no_sampel' => $request->input('no_sampel'),
                'category' => $request->input('category'),
            ]);

            return response()->json([
                'success' => true,
                'message' => ($result['summary']['dry_run'] ?? true)
                    ? 'Preview repair WS final approval selesai.'
                    : 'Repair WS final approval selesai.',
                'summary' => $result['summary'],
                'changes' => $result['changes'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateQrLink(Request $request)
    {
        try {
            $link = $request->input('link');
            if (empty($link)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link tidak boleh kosong.',
                ], 400);
            }

            // Generate QR Code as base64 string (default format is SVG)
            $qrCode = base64_encode(QrCode::size(300)->generate($link));

            return response()->json([
                'success' => true,
                'message' => 'QR Code berhasil di-generate.',
                'data' => 'data:image/svg+xml;base64,' . $qrCode,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat generate QR Code: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function exportFdlBasTiming(Request $request)
    {
        try {
            $service = new FdlBasTimingExportService();
            $result = $service->export($request->all());

            if (filter_var($request->download, FILTER_VALIDATE_BOOLEAN)) {
                return response()->download($result['full_path']);
            }

            unset($result['full_path']);

            return response()->json([
                'success' => true,
                'message' => 'Excel tracking FDL BAS berhasil dibuat',
                'data' => $result,
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }
    public function syncLinkLhpRilis(Request $request)
    {
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        $limit = (int) $request->input('limit', 0);

        $summary = [
            'checked' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_empty_lhp' => 0,
            'missing_order_berjalan' => 0,
            'errors' => 0,
            'dry_run' => $dryRun,
        ];
        $changes = [];
        $missing = [];
        $errors = [];

        try {
            $query = LinkLhp::query()
                ->select([
                    'id',
                    'no_order',
                    'periode',
                    'jumlah_lhp',
                    'jumlah_lhp_rilis',
                    'list_lhp_rilis',
                    'is_completed',
                ])
                ->orderBy('id');

            if ($limit > 0) {
                $query->limit($limit);
            }

            $linkRows = $query->get();
            $orderBerjalanByOrder = DB::table('order_berjalan')
                ->whereIn('no_order', $linkRows->pluck('no_order')->filter()->unique()->values())
                ->pluck('dataOrderDetail', 'no_order');

            foreach ($linkRows as $linkLhp) {
                $summary['checked']++;

                try {
                    $dataOrderDetail = $orderBerjalanByOrder[$linkLhp->no_order] ?? null;
                    if (!$dataOrderDetail) {
                        $summary['missing_order_berjalan']++;
                        $missing[] = [
                            'id' => $linkLhp->id,
                            'no_order' => $linkLhp->no_order,
                            'periode' => $linkLhp->periode,
                        ];
                        continue;
                    }

                    $calculated = $this->calculateLhpRilisFromOrderBerjalan($dataOrderDetail, $linkLhp->periode);
                    if ($calculated['jumlah_lhp'] === 0) {
                        $summary['skipped_empty_lhp']++;
                        continue;
                    }

                    $currentList = $this->normalizeJsonArray($linkLhp->list_lhp_rilis);

                    $needsUpdate =
                        (int) $linkLhp->jumlah_lhp !== $calculated['jumlah_lhp'] ||
                        (int) $linkLhp->jumlah_lhp_rilis !== $calculated['jumlah_lhp_rilis'] ||
                        (int) $linkLhp->is_completed !== (int) $calculated['is_completed'] ||
                        $currentList !== $calculated['list_lhp_rilis'];

                    if (!$needsUpdate) {
                        $summary['unchanged']++;
                        continue;
                    }

                    $change = [
                        'id' => $linkLhp->id,
                        'no_order' => $linkLhp->no_order,
                        'periode' => $linkLhp->periode,
                        'before' => [
                            'jumlah_lhp' => (int) $linkLhp->jumlah_lhp,
                            'jumlah_lhp_rilis' => (int) $linkLhp->jumlah_lhp_rilis,
                            'is_completed' => (int) $linkLhp->is_completed,
                            'list_lhp_rilis' => $currentList,
                        ],
                        'after' => $calculated,
                    ];
                    $changes[] = $change;

                    if (!$dryRun) {
                        $linkLhp->update([
                            'jumlah_lhp' => $calculated['jumlah_lhp'],
                            'jumlah_lhp_rilis' => $calculated['jumlah_lhp_rilis'],
                            'list_lhp_rilis' => json_encode($calculated['list_lhp_rilis']),
                            'is_completed' => $calculated['is_completed'],
                            'updated_by' => $this->karyawan,
                            'updated_at' => Carbon::now(),
                        ]);
                    }

                    $summary['updated']++;
                } catch (\Throwable $th) {
                    $summary['errors']++;
                    $errors[] = [
                        'id' => $linkLhp->id,
                        'no_order' => $linkLhp->no_order,
                        'periode' => $linkLhp->periode,
                        'message' => $th->getMessage(),
                    ];
                }
            }

            return response()->json([
                'message' => $dryRun ? 'Preview sync link LHP selesai.' : 'Sync link LHP selesai.',
                'summary' => $summary,
                'changes' => array_slice($changes, 0, 100),
                'missing_order_berjalan' => array_slice($missing, 0, 100),
                'errors' => array_slice($errors, 0, 100),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal sync link LHP: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function calculateLhpRilisFromOrderBerjalan($dataOrderDetail, ?string $periode = null): array
    {
        $decoded = is_string($dataOrderDetail)
            ? json_decode($dataOrderDetail, true)
            : $dataOrderDetail;

        if (!is_array($decoded)) {
            throw new \Exception('dataOrderDetail bukan JSON valid.');
        }

        $periodGroups = collect($decoded)
            ->filter(function ($group) use ($periode) {
                if ($periode === null || $periode === '') {
                    return true;
                }

                return (string) ($group['periode'] ?? '') === (string) $periode;
            })
            ->values();

        $details = $periodGroups
            ->flatMap(function ($group) {
                return $group['detail'] ?? [];
            })
            ->filter(fn ($detail) => !empty($detail['cfr']))
            ->unique('cfr')
            ->values();

        $listLhpRilis = $details
            ->filter(fn ($detail) => (bool) ($detail['lhp_rilis'] ?? false))
            ->pluck('cfr')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values()
            ->toArray();

        $jumlahLhp = $details->count();
        $jumlahLhpRilis = count($listLhpRilis);

        return [
            'jumlah_lhp' => $jumlahLhp,
            'jumlah_lhp_rilis' => $jumlahLhpRilis,
            'list_lhp_rilis' => $listLhpRilis,
            'is_completed' => $jumlahLhp > 0 && $jumlahLhp === $jumlahLhpRilis ? 1 : 0,
        ];
    }

    private function normalizeJsonArray($value): array
    {
        if (is_array($value)) {
            $data = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $data = json_decode($value, true);
        } else {
            $data = [];
        }

        if (!is_array($data)) {
            return [];
        }

        return collect($data)
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values()
            ->toArray();
    }

    public function checkLhpByQuotation(Request $request)
    {
        $noDocument = trim((string) $request->input('no_quotation', $request->input('no_document', '')));

        if ($noDocument === '') {
            return response()->json(['message' => 'Nomor quotation wajib diisi.'], 422);
        }

        try {
            $resolved = $this->resolveLhpCheckTarget($noDocument);
            if (!$resolved) {
                return response()->json(['message' => 'Quotation / order tidak ditemukan.'], 404);
            }

            $orderHeader = $resolved['order'];
            $isKontrak = $resolved['is_kontrak'];
            $periodKeys = $this->collectLhpPeriodKeys($orderHeader, $isKontrak, $resolved['quotation']);

            $linkRows = LinkLhp::where('no_order', $orderHeader->no_order)
                ->orderBy('id')
                ->get();

            $orderBerjalan = DB::table('order_berjalan')
                ->where('no_order', $orderHeader->no_order)
                ->first();
            $dataOrderDetail = $orderBerjalan ? $orderBerjalan->dataOrderDetail : null;

            $orderDetails = OrderDetail::where('no_order', $orderHeader->no_order)
                ->where('is_active', true)
                ->whereNotNull('cfr')
                ->get(['id', 'cfr', 'periode', 'no_sampel']);

            $resolver = new ResolveLhpFile();
            $periods = [];
            $allIssues = [];

            foreach ($periodKeys as $periode) {
                $periodResult = $this->inspectLhpPeriod(
                    $orderHeader,
                    $periode,
                    $isKontrak,
                    $linkRows,
                    $dataOrderDetail,
                    $orderDetails,
                    $resolver
                );
                $periods[] = $periodResult;
                foreach ($periodResult['issues'] as $issue) {
                    $allIssues[] = $issue;
                }
            }

            if (!$orderBerjalan) {
                $allIssues[] = [
                    'code' => 'MISSING_ORDER_BERJALAN',
                    'severity' => 'warning',
                    'title' => 'order_berjalan kosong',
                    'message' => 'Status rilis dihitung dari order_detail + file LHP, bukan dataOrderDetail.',
                    'fixable' => false,
                    'periode' => null,
                ];
            }

            $problemCount = collect($periods)->filter(fn ($row) => !empty($row['issues']))->count();
            $missingLink = collect($periods)->filter(fn ($row) => empty($row['link_lhp']))->count();
            $completed = collect($periods)->filter(fn ($row) => (int) ($row['link_lhp']['is_completed'] ?? 0) === 1)->count();

            return response()->json([
                'message' => 'Data LHP quotation ditemukan.',
                'data' => [
                    'quotation' => [
                        'no_document' => $resolved['no_document'],
                        'type' => $isKontrak ? 'kontrak' : 'non_kontrak',
                        'nama_perusahaan' => $orderHeader->nama_perusahaan,
                        'pelanggan_id' => $orderHeader->id_pelanggan ?? null,
                    ],
                    'order' => [
                        'id' => $orderHeader->id,
                        'no_order' => $orderHeader->no_order,
                        'no_document' => $orderHeader->no_document,
                        'created_at' => $orderHeader->created_at,
                    ],
                    'summary' => [
                        'total_periode' => count($periods),
                        'ok' => count($periods) - $problemCount,
                        'problem' => $problemCount,
                        'missing_link' => $missingLink,
                        'completed' => $completed,
                        'has_order_berjalan' => (bool) $orderBerjalan,
                    ],
                    'periods' => $periods,
                    'diagnostics' => [
                        'has_error' => !empty($allIssues),
                        'issues' => $allIssues,
                    ],
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
        }
    }

    public function fixLhpIssue(Request $request)
    {
        $noDocument = trim((string) $request->input('no_quotation', $request->input('no_document', '')));
        $action = trim((string) $request->input('action'));
        $periode = $request->input('periode');
        $periode = $periode === null || $periode === '' || $periode === 'all' ? null : (string) $periode;

        $allowed = ['create_missing_link', 'sync_counts', 'rebuild_bundle'];
        if ($noDocument === '') {
            return response()->json(['message' => 'Nomor quotation wajib diisi.'], 422);
        }
        if (!in_array($action, $allowed, true)) {
            return response()->json(['message' => 'Action fixing LHP tidak dikenali.'], 422);
        }

        try {
            $resolved = $this->resolveLhpCheckTarget($noDocument);
            if (!$resolved) {
                return response()->json(['message' => 'Quotation / order tidak ditemukan.'], 404);
            }

            $orderHeader = $resolved['order'];
            $isKontrak = $resolved['is_kontrak'];
            if ($isKontrak && $periode === null && in_array($action, ['create_missing_link', 'sync_counts', 'rebuild_bundle'], true)) {
                return response()->json(['message' => 'Periode wajib diisi untuk quotation kontrak.'], 422);
            }

            $payload = [];
            if ($action === 'create_missing_link') {
                $payload = $this->createMissingLinkLhp($orderHeader, $periode);
                try {
                    $payload['sync'] = $this->syncLinkLhpCounts($orderHeader, $periode);
                } catch (\Throwable $th) {
                    $payload['sync_error'] = $th->getMessage();
                }
            } elseif ($action === 'sync_counts') {
                $payload = $this->syncLinkLhpCounts($orderHeader, $periode);
            } else {
                $payload = $this->rebuildLhpBundleForPeriod($orderHeader, $periode);
            }

            return response()->json([
                'message' => $payload['message'] ?? 'Fixing LHP berhasil.',
                'data' => $payload,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal fixing LHP: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function resolveLhpCheckTarget(string $input): ?array
    {
        $kontrak = QuotationKontrakH::where('no_document', $input)->first();
        if ($kontrak) {
            $order = OrderHeader::where('no_document', $kontrak->no_document)
                ->where('is_active', true)
                ->latest('id')
                ->first();
            if (!$order) {
                return null;
            }
            return [
                'quotation' => $kontrak,
                'order' => $order,
                'is_kontrak' => true,
                'no_document' => $kontrak->no_document,
            ];
        }

        $nonKontrak = QuotationNonKontrak::where('no_document', $input)->first();
        if ($nonKontrak) {
            $order = OrderHeader::where('no_document', $nonKontrak->no_document)
                ->where('is_active', true)
                ->latest('id')
                ->first();
            if (!$order) {
                return null;
            }
            return [
                'quotation' => $nonKontrak,
                'order' => $order,
                'is_kontrak' => false,
                'no_document' => $nonKontrak->no_document,
            ];
        }

        $order = OrderHeader::where('is_active', true)
            ->where(function ($q) use ($input) {
                $q->where('no_document', $input)->orWhere('no_order', $input);
            })
            ->latest('id')
            ->first();
        if (!$order) {
            return null;
        }

        $isKontrak = str_contains((string) $order->no_document, '/QTC/');
        $quotation = $isKontrak
            ? QuotationKontrakH::where('no_document', $order->no_document)->first()
            : QuotationNonKontrak::where('no_document', $order->no_document)->first();

        return [
            'quotation' => $quotation,
            'order' => $order,
            'is_kontrak' => $isKontrak,
            'no_document' => $order->no_document,
        ];
    }

    private function collectLhpPeriodKeys($orderHeader, bool $isKontrak, $quotation): array
    {
        $keys = collect();

        if ($isKontrak) {
            if ($quotation && method_exists($quotation, 'quotationKontrakD')) {
                $keys = $keys->merge(
                    $quotation->quotationKontrakD()->pluck('periode_kontrak')
                );
            } elseif ($quotation && method_exists($quotation, 'detail')) {
                $keys = $keys->merge($quotation->detail()->pluck('periode_kontrak'));
            }

            $keys = $keys->merge(
                OrderDetail::where('no_order', $orderHeader->no_order)
                    ->where('is_active', true)
                    ->whereNotNull('periode')
                    ->pluck('periode')
            );

            $keys = $keys->merge(
                LinkLhp::where('no_order', $orderHeader->no_order)->pluck('periode')
            );

            $orderBerjalan = DB::table('order_berjalan')->where('no_order', $orderHeader->no_order)->value('dataOrderDetail');
            $decoded = is_string($orderBerjalan) ? json_decode($orderBerjalan, true) : $orderBerjalan;
            if (is_array($decoded)) {
                $keys = $keys->merge(collect($decoded)->pluck('periode'));
            }

            return $keys
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => $this->lhpPeriodKey($value))
                ->unique()
                ->sort()
                ->values()
                ->toArray();
        }

        return [null];
    }

    private function inspectLhpPeriod(
        $orderHeader,
        $periode,
        bool $isKontrak,
        $linkRows,
        $dataOrderDetail,
        $orderDetails,
        ResolveLhpFile $resolver
    ): array {
        $periodKey = $this->lhpPeriodKey($periode);
        $periodLinks = $linkRows->filter(function ($row) use ($periodKey, $isKontrak) {
            if (!$isKontrak) {
                return true;
            }
            return $this->lhpPeriodKey($row->periode) === $periodKey;
        })->values();

        $link = $periodLinks->sortByDesc('id')->first();
        $detailsForPeriod = $orderDetails->filter(function ($detail) use ($periodKey, $isKontrak) {
            if (!$isKontrak) {
                return true;
            }
            return $this->lhpPeriodKey($detail->periode) === $periodKey;
        });

        $expected = $this->calculateExpectedLhpStatus(
            $orderHeader->no_order,
            $periode,
            $isKontrak,
            $dataOrderDetail,
            $detailsForPeriod,
            $resolver
        );

        $currentList = $link ? $this->normalizeJsonArray($link->list_lhp_rilis) : [];
        $filename = $link->filename ?? null;
        $bundlePath = $filename
            ? public_path('laporan/hasil_pengujian/' . ltrim($filename, '/'))
            : $this->expectedBundlePath($orderHeader->no_order, $periode);
        $bundleExists = $filename ? File::exists($bundlePath) : File::exists($this->expectedBundlePath($orderHeader->no_order, $periode));
        if (!$filename && $bundleExists) {
            $filename = basename($this->expectedBundlePath($orderHeader->no_order, $periode));
        }

        $issues = [];
        $pushIssue = function (array $issue) use (&$issues, $periode) {
            $issue['periode'] = $this->lhpPeriodKey($periode);
            $issues[] = $issue;
        };

        if ($periodLinks->count() > 1) {
            $pushIssue([
                'code' => 'DUPLICATE_LINK_LHP',
                'severity' => 'warning',
                'title' => 'Link LHP dobel',
                'message' => 'Ada ' . $periodLinks->count() . ' row link_lhp untuk periode ini. Pengecekan memakai id terbaru.',
                'fixable' => false,
                'detail' => ['ids' => $periodLinks->pluck('id')->values()],
            ]);
        }

        if (!$link) {
            $pushIssue([
                'code' => 'MISSING_LINK_LHP',
                'severity' => 'danger',
                'title' => 'Link LHP belum ada',
                'message' => 'Periode ini tidak punya row di link_lhp (status table: Belum rilis). Approve LHP tidak akan combine.',
                'fixable' => true,
                'action' => 'create_missing_link',
                'button_label' => 'Buat Link',
            ]);
        } else {
            if ((int) ($link->is_active ?? 1) === 0) {
                $pushIssue([
                    'code' => 'LINK_INACTIVE',
                    'severity' => 'warning',
                    'title' => 'Link LHP nonaktif',
                    'message' => 'Row link_lhp ada tapi is_active = 0.',
                    'fixable' => false,
                ]);
            }

            if (empty($link->id_token) || empty($link->link)) {
                $pushIssue([
                    'code' => 'MISSING_TOKEN',
                    'severity' => 'warning',
                    'title' => 'Token/link portal kosong',
                    'message' => 'id_token atau link portal LHP belum terisi.',
                    'fixable' => false,
                ]);
            }

            if ((int) $link->jumlah_lhp !== (int) $expected['jumlah_lhp']) {
                $pushIssue([
                    'code' => 'JUMLAH_LHP_MISMATCH',
                    'severity' => 'warning',
                    'title' => 'Jumlah LHP beda',
                    'message' => 'link_lhp.jumlah_lhp = ' . (int) $link->jumlah_lhp . ', seharusnya ' . (int) $expected['jumlah_lhp'] . ' (unique CFR).',
                    'fixable' => true,
                    'action' => 'sync_counts',
                    'button_label' => 'Sync Jumlah',
                    'detail' => [
                        'stored' => (int) $link->jumlah_lhp,
                        'expected' => (int) $expected['jumlah_lhp'],
                    ],
                ]);
            }

            if ((int) $link->jumlah_lhp_rilis !== (int) $expected['jumlah_lhp_rilis']
                || count($currentList) !== (int) $link->jumlah_lhp_rilis
            ) {
                $pushIssue([
                    'code' => 'JUMLAH_RILIS_MISMATCH',
                    'severity' => 'warning',
                    'title' => 'Jumlah rilis beda',
                    'message' => 'Stored rilis ' . (int) $link->jumlah_lhp_rilis
                        . ' / list ' . count($currentList)
                        . ', aktual rilis ' . (int) $expected['jumlah_lhp_rilis'] . '.',
                    'fixable' => true,
                    'action' => 'sync_counts',
                    'button_label' => 'Sync Jumlah',
                    'detail' => [
                        'stored' => (int) $link->jumlah_lhp_rilis,
                        'list_count' => count($currentList),
                        'expected' => (int) $expected['jumlah_lhp_rilis'],
                    ],
                ]);
            }

            if ($currentList !== $expected['list_lhp_rilis']) {
                $pushIssue([
                    'code' => 'LIST_RILIS_MISMATCH',
                    'severity' => 'warning',
                    'title' => 'List LHP rilis tidak sama',
                    'message' => 'Isi list_lhp_rilis tidak match CFR yang sudah rilis.',
                    'fixable' => true,
                    'action' => 'sync_counts',
                    'button_label' => 'Sync List',
                    'detail' => [
                        'stored' => $currentList,
                        'expected' => $expected['list_lhp_rilis'],
                    ],
                ]);
            }

            $expectedCompleted = (int) $expected['is_completed'];
            if ((int) $link->is_completed !== $expectedCompleted) {
                $pushIssue([
                    'code' => 'IS_COMPLETED_MISMATCH',
                    'severity' => 'warning',
                    'title' => 'Flag completed salah',
                    'message' => 'is_completed = ' . (int) $link->is_completed . ', seharusnya ' . $expectedCompleted . '.',
                    'fixable' => true,
                    'action' => 'sync_counts',
                    'button_label' => 'Sync Flag',
                ]);
            }
        }

        $releasedCount = (int) $expected['jumlah_lhp_rilis'];
        if ($releasedCount > 0) {
            if (empty($filename)) {
                $pushIssue([
                    'code' => 'BUNDLE_NOT_COMBINED',
                    'severity' => 'danger',
                    'title' => 'Rekap belum di-combine',
                    'message' => 'Sudah ada LHP rilis tapi filename bundle kosong.',
                    'fixable' => true,
                    'action' => 'rebuild_bundle',
                    'button_label' => 'Combine PDF',
                ]);
            } elseif (!File::exists($bundlePath)) {
                $pushIssue([
                    'code' => 'BUNDLE_FILE_MISSING',
                    'severity' => 'danger',
                    'title' => 'File rekap belum ke-render',
                    'message' => 'Filename ada (' . $filename . ') tapi file tidak ketemu di laporan/hasil_pengujian.',
                    'fixable' => true,
                    'action' => 'rebuild_bundle',
                    'button_label' => 'Render Ulang',
                    'detail' => ['filename' => $filename, 'path' => 'laporan/hasil_pengujian/' . ltrim($filename, '/')],
                ]);
            }
        }

        $cfrs = array_map(function ($cfrRow) use ($currentList) {
            $cfrRow['in_list'] = in_array($cfrRow['cfr'], $currentList, true);
            return $cfrRow;
        }, $expected['cfrs']);

        foreach ($cfrs as $cfrRow) {
            if ($cfrRow['released'] && !$cfrRow['in_list'] && $link) {
                $pushIssue([
                    'code' => 'CFR_RELEASED_NOT_COMBINED',
                    'severity' => 'danger',
                    'title' => 'CFR rilis belum masuk list',
                    'message' => $cfrRow['cfr'] . ' sudah rilis tapi belum masuk list_lhp_rilis.',
                    'fixable' => true,
                    'action' => 'rebuild_bundle',
                    'button_label' => 'Combine PDF',
                    'detail' => ['cfr' => $cfrRow['cfr']],
                ]);
            }
            if ($cfrRow['released'] && !$cfrRow['file_exists']) {
                $pushIssue([
                    'code' => 'CFR_FILE_MISSING',
                    'severity' => 'warning',
                    'title' => 'PDF LHP per CFR belum ada',
                    'message' => $cfrRow['cfr'] . ' dianggap rilis tapi file individual tidak ketemu.',
                    'fixable' => false,
                    'detail' => ['cfr' => $cfrRow['cfr'], 'file_lhp' => $cfrRow['file_lhp']],
                ]);
            }
        }

        $status = 'ok';
        if (!$link) {
            $status = 'belum_rilis';
        } elseif (!empty($issues)) {
            $status = 'problem';
        } elseif ((int) ($link->is_completed ?? 0) === 1) {
            $status = 'selesai';
        } else {
            $status = 'belum_selesai';
        }

        return [
            'periode' => $periodKey === 'all' ? ($isKontrak ? null : 'non_kontrak') : $periodKey,
            'status' => $status,
            'link_lhp' => $link ? [
                'id' => $link->id,
                'periode' => $link->periode,
                'jumlah_lhp' => (int) $link->jumlah_lhp,
                'jumlah_lhp_rilis' => (int) $link->jumlah_lhp_rilis,
                'list_lhp_rilis' => $currentList,
                'is_completed' => (int) $link->is_completed,
                'filename' => $link->filename,
                'file_exists' => $filename ? File::exists($bundlePath) : false,
                'is_active' => (int) ($link->is_active ?? 1),
                'is_system' => (int) ($link->is_system ?? 0),
                'created_by' => $link->created_by,
                'created_at' => $link->created_at,
            ] : null,
            'expected' => [
                'jumlah_lhp' => $expected['jumlah_lhp'],
                'jumlah_lhp_rilis' => $expected['jumlah_lhp_rilis'],
                'list_lhp_rilis' => $expected['list_lhp_rilis'],
                'is_completed' => $expected['is_completed'],
            ],
            'cfrs' => $cfrs,
            'issues' => $issues,
        ];
    }

    private function calculateExpectedLhpStatus(
        string $noOrder,
        $periode,
        bool $isKontrak,
        $dataOrderDetail,
        $detailsForPeriod,
        ResolveLhpFile $resolver
    ): array {
        $periodKey = $this->lhpPeriodKey($periode);
        $cfrMap = [];

        foreach ($detailsForPeriod as $detail) {
            $cfr = $detail->cfr;
            if (!$cfr) {
                continue;
            }
            if (!isset($cfrMap[$cfr])) {
                $fileLhp = $resolver->byCfr($noOrder, $cfr, $detail->no_sampel ?? null);
                $expectedName = 'LHP-' . str_replace('/', '-', $cfr) . '.pdf';
                $path = $fileLhp
                    ? public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp)
                    : public_path('dokumen/LHP_DOWNLOAD/' . $expectedName);
                $cfrMap[$cfr] = [
                    'cfr' => $cfr,
                    'released' => false,
                    'in_list' => false,
                    'file_lhp' => $fileLhp ?: $expectedName,
                    'file_exists' => File::exists($path),
                    'source' => [],
                ];
            }
        }

        $decoded = is_string($dataOrderDetail) ? json_decode($dataOrderDetail, true) : $dataOrderDetail;
        $seenInBerjalan = [];
        if (is_array($decoded)) {
            foreach ($decoded as $group) {
                if ($isKontrak && $this->lhpPeriodKey($group['periode'] ?? null) !== $periodKey) {
                    continue;
                }
                foreach (($group['detail'] ?? []) as $detail) {
                    $cfr = $detail['cfr'] ?? null;
                    if (!$cfr) {
                        continue;
                    }
                    $seenInBerjalan[$cfr] = true;
                    if (!isset($cfrMap[$cfr])) {
                        $fileLhp = $resolver->byCfr($noOrder, $cfr, is_array($detail['no_sampel'] ?? null) ? ($detail['no_sampel'][0] ?? null) : ($detail['no_sampel'] ?? null));
                        $expectedName = 'LHP-' . str_replace('/', '-', $cfr) . '.pdf';
                        $path = $fileLhp
                            ? public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp)
                            : public_path('dokumen/LHP_DOWNLOAD/' . $expectedName);
                        $cfrMap[$cfr] = [
                            'cfr' => $cfr,
                            'released' => false,
                            'in_list' => false,
                            'file_lhp' => $fileLhp ?: $expectedName,
                            'file_exists' => File::exists($path),
                            'source' => [],
                        ];
                    }
                    $released = (bool) ($detail['lhp_rilis'] ?? false)
                        || !empty($detail['steps']['lhp_release']['date']);
                    if ($released) {
                        $cfrMap[$cfr]['released'] = true;
                        $cfrMap[$cfr]['source'][] = 'order_berjalan';
                    }
                }
            }
        }

        foreach ($cfrMap as $cfr => &$row) {
            if ($row['file_exists'] && empty($seenInBerjalan[$cfr])) {
                $row['released'] = true;
                $row['source'][] = 'file';
            }
        }
        unset($row);

        $listRilis = collect($cfrMap)
            ->filter(fn ($row) => $row['released'])
            ->keys()
            ->sort(SORT_NATURAL)
            ->values()
            ->toArray();

        foreach ($cfrMap as $cfr => &$row) {
            $row['in_list'] = in_array($cfr, $listRilis, true);
            $row['source'] = array_values(array_unique($row['source']));
        }
        unset($row);

        $jumlahLhp = count($cfrMap);
        $jumlahRilis = count($listRilis);

        return [
            'jumlah_lhp' => $jumlahLhp,
            'jumlah_lhp_rilis' => $jumlahRilis,
            'list_lhp_rilis' => $listRilis,
            'is_completed' => $jumlahLhp > 0 && $jumlahLhp === $jumlahRilis ? 1 : 0,
            'cfrs' => array_values($cfrMap),
        ];
    }

    private function createMissingLinkLhp($orderHeader, ?string $periode): array
    {
        $query = LinkLhp::where('no_order', $orderHeader->no_order);
        if ($periode) {
            $query->where(function ($q) use ($periode) {
                $ym = substr($periode, 0, 7);
                $q->where('periode', $periode)->orWhere('periode', $ym)->orWhere('periode', 'like', $ym . '%');
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('periode')->orWhere('periode', '')->orWhere('periode', 'all');
            });
        }

        $existing = $query->latest('id')->first();
        if ($existing) {
            return [
                'message' => 'Link LHP periode ini sudah ada.',
                'id' => $existing->id,
                'created' => false,
            ];
        }

        $detailQuery = OrderDetail::where('no_order', $orderHeader->no_order)
            ->where('is_active', true)
            ->whereNotNull('cfr');
        if ($periode) {
            $ym = substr($periode, 0, 7);
            $detailQuery->where(function ($q) use ($periode, $ym) {
                $q->where('periode', $periode)->orWhere('periode', $ym)->orWhere('periode', 'like', $ym . '%');
            });
        }
        $jumlahLhp = $detailQuery->get()->unique('cfr')->count();

        $linkLhp = new LinkLhp();
        $linkLhp->no_quotation = $orderHeader->no_document;
        $linkLhp->periode = $periode;
        $linkLhp->no_order = $orderHeader->no_order;
        $linkLhp->nama_perusahaan = $orderHeader->nama_perusahaan;
        $linkLhp->jumlah_lhp = $jumlahLhp;
        $linkLhp->jumlah_lhp_rilis = 0;
        $linkLhp->list_lhp_rilis = json_encode([]);
        $linkLhp->is_completed = 0;
        $linkLhp->is_system = true;
        $linkLhp->is_active = 1;
        $linkLhp->created_by = $this->karyawan;
        $linkLhp->created_at = Carbon::now();
        $linkLhp->updated_by = $this->karyawan;
        $linkLhp->updated_at = Carbon::now();
        $linkLhp->save();

        $key = $orderHeader->no_order;
        $gen = md5($key);
        $dateSeed = $periode
            ? Carbon::createFromFormat('Y-m-d', substr($periode, 0, 7) . '-01')->startOfMonth()
            : Carbon::now();
        $token = $this->makeLhpLinkToken($gen . '|' . $dateSeed->format('Y-m-d'));

        $tokenId = GenerateLink::insertGetId([
            'token' => $token,
            'key' => $gen,
            'id_quotation' => $linkLhp->id,
            'quotation_status' => 'lhp_rilis',
            'type' => 'lhp_rilis',
            'expired' => Carbon::now()->addYear()->format('Y-m-d'),
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'created_by' => $this->karyawan,
        ]);

        $linkLhp->update([
            'id_token' => $tokenId,
            'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token,
        ]);

        return [
            'message' => 'Link LHP berhasil dibuat.',
            'id' => $linkLhp->id,
            'created' => true,
        ];
    }

    private function syncLinkLhpCounts($orderHeader, ?string $periode): array
    {
        $linkLhp = $this->findLinkLhpForPeriod($orderHeader->no_order, $periode);
        if (!$linkLhp) {
            throw new \Exception('Link LHP periode ini belum ada. Buat link dulu.');
        }

        $orderBerjalan = DB::table('order_berjalan')->where('no_order', $orderHeader->no_order)->value('dataOrderDetail');
        $details = OrderDetail::where('no_order', $orderHeader->no_order)
            ->where('is_active', true)
            ->whereNotNull('cfr')
            ->get(['id', 'cfr', 'periode', 'no_sampel']);
        if ($periode) {
            $ym = substr($periode, 0, 7);
            $details = $details->filter(fn ($row) => $this->lhpPeriodKey($row->periode) === $ym);
        }

        $expected = $this->calculateExpectedLhpStatus(
            $orderHeader->no_order,
            $periode,
            (bool) $periode,
            $orderBerjalan,
            $details,
            new ResolveLhpFile()
        );

        $before = [
            'jumlah_lhp' => (int) $linkLhp->jumlah_lhp,
            'jumlah_lhp_rilis' => (int) $linkLhp->jumlah_lhp_rilis,
            'is_completed' => (int) $linkLhp->is_completed,
            'list_lhp_rilis' => $this->normalizeJsonArray($linkLhp->list_lhp_rilis),
        ];

        $linkLhp->update([
            'jumlah_lhp' => $expected['jumlah_lhp'],
            'jumlah_lhp_rilis' => $expected['jumlah_lhp_rilis'],
            'list_lhp_rilis' => json_encode($expected['list_lhp_rilis']),
            'is_completed' => $expected['is_completed'],
            'updated_by' => $this->karyawan,
            'updated_at' => Carbon::now(),
        ]);

        return [
            'message' => 'Jumlah / list LHP rilis berhasil disync.',
            'id' => $linkLhp->id,
            'before' => $before,
            'after' => [
                'jumlah_lhp' => $expected['jumlah_lhp'],
                'jumlah_lhp_rilis' => $expected['jumlah_lhp_rilis'],
                'is_completed' => $expected['is_completed'],
                'list_lhp_rilis' => $expected['list_lhp_rilis'],
            ],
        ];
    }

    private function rebuildLhpBundleForPeriod($orderHeader, ?string $periode): array
    {
        $created = false;
        $linkLhp = $this->findLinkLhpForPeriod($orderHeader->no_order, $periode);
        if (!$linkLhp) {
            $createdPayload = $this->createMissingLinkLhp($orderHeader, $periode);
            $linkLhp = LinkLhp::find($createdPayload['id']);
            $created = true;
        }

        $sync = $this->syncLinkLhpCounts($orderHeader, $periode);
        $linkLhp->refresh();

        $list = $this->normalizeJsonArray($linkLhp->list_lhp_rilis);
        if (empty($list)) {
            return [
                'message' => 'Count sudah disync, tapi belum ada LHP rilis jadi PDF rekap tidak di-combine.',
                'id' => $linkLhp->id,
                'created_link' => $created,
                'merged' => false,
                'sync' => $sync,
            ];
        }

        $resolver = new ResolveLhpFile();
        $files = [];
        $missing = [];
        foreach ($list as $cfr) {
            $fileLhp = $resolver->byCfr($orderHeader->no_order, $cfr);
            if (!$fileLhp) {
                $fileLhp = 'LHP-' . str_replace('/', '-', $cfr) . '.pdf';
            }
            $path = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);
            if (File::exists($path)) {
                $files[] = ['name' => $fileLhp, 'path' => $path];
            } else {
                $missing[] = $cfr;
            }
        }

        if (empty($files)) {
            throw new \Exception('Tidak ada file LHP individual yang bisa di-combine.');
        }

        $finalDirectoryPath = public_path('laporan/hasil_pengujian');
        $finalFilename = $periode
            ? $orderHeader->no_order . '_' . substr($periode, 0, 7) . '.pdf'
            : $orderHeader->no_order . '.pdf';
        $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;
        if (!File::isDirectory($finalDirectoryPath)) {
            File::makeDirectory($finalDirectoryPath, 0777, true);
        }

        $httpClient = Http::asMultipart();
        foreach ($files as $file) {
            $httpClient->attach('pdfs[]', File::get($file['path']), $file['name']);
        }
        $httpClient->attach('metadata', json_encode(array_fill(0, count($files), 'skyhwk12')));

        $response = $httpClient->post(env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge');
        if (!$response->successful()) {
            throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
        }

        File::put($finalFullPath, $response->body());

        $linkLhp->filename = $finalFilename;
        $linkLhp->updated_by = $this->karyawan;
        $linkLhp->updated_at = Carbon::now();
        $linkLhp->save();

        GenerateLink::where('id', $linkLhp->id_token)->update([
            'fileName_pdf' => $finalFilename,
        ]);

        return [
            'message' => 'PDF rekap berhasil di-combine.' . ($missing ? ' CFR tanpa file: ' . implode(', ', $missing) : ''),
            'id' => $linkLhp->id,
            'created_link' => $created,
            'merged' => true,
            'filename' => $finalFilename,
            'combined_files' => count($files),
            'missing_cfr_files' => $missing,
            'sync' => $sync,
        ];
    }

    private function findLinkLhpForPeriod(string $noOrder, ?string $periode): ?LinkLhp
    {
        $query = LinkLhp::where('no_order', $noOrder);
        if ($periode) {
            $ym = substr($periode, 0, 7);
            $query->where(function ($q) use ($periode, $ym) {
                $q->where('periode', $periode)->orWhere('periode', $ym)->orWhere('periode', 'like', $ym . '%');
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('periode')->orWhere('periode', '')->orWhere('periode', 'all');
            });
        }

        return $query->orderByDesc('id')->first();
    }

    private function expectedBundlePath(string $noOrder, $periode): string
    {
        $filename = $periode ? $noOrder . '_' . substr((string) $periode, 0, 7) . '.pdf' : $noOrder . '.pdf';
        return public_path('laporan/hasil_pengujian/' . $filename);
    }

    private function lhpPeriodKey($periode): string
    {
        if ($periode === null || $periode === '' || $periode === 'all' || $periode === 'non_kontrak') {
            return 'all';
        }

        return substr((string) $periode, 0, 7);
    }

    private function makeLhpLinkToken(string $data): string
    {
        $encryptionKey = base64_decode('intilab_jaya');
        $algorithm = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($algorithm));
        $encrypted = openssl_encrypt($data, $algorithm, $encryptionKey, 0, $iv);

        return base64_encode($encrypted . '::' . $iv);
    }

    public function fixDetailStructure(Request $request)
    {
        try {
            $dataList = QuotationKontrakH::with('quotationKontrakD')
                ->whereIn('no_document', $request->no_document)
                ->where('is_active', true)
                ->get();

            $fixedCount   = 0;
            $skippedCount = 0;
            $errorCount   = 0;
            $errorDetails = [];

            foreach ($dataList as $data) {
                DB::beginTransaction();

                try {
                    foreach ($data->quotationKontrakD as $detailIndex => $detail) {
                        $dsDetail = json_decode($detail->data_pendukung_sampling, true);

                        if (! is_array($dsDetail)) {
                            $errorCount++;
                            continue;
                        }

                        $isValidStructure = false;
                        $needsFix         = false;

                        foreach ($dsDetail as $key => $item) {
                            if (is_array($item) && array_key_exists('periode_kontrak', $item)) {
                                $isValidStructure = true;
                                if (isset($item['data_sampling']) && is_string($item['data_sampling'])) {
                                    $decoded = json_decode($item['data_sampling'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $dsDetail[$key]['data_sampling'] = $decoded;
                                        $needsFix                        = true;
                                    }
                                }
                                break;
                            }

                            if (array_key_exists('periode_kontrak', $dsDetail)) {
                                $isValidStructure = true;
                                if (isset($dsDetail['data_sampling']) && is_string($dsDetail['data_sampling'])) {
                                    $decoded = json_decode($dsDetail['data_sampling'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $dsDetail['data_sampling'] = $decoded;
                                        $needsFix                  = true;
                                    }
                                }

                                // Gunakan key dinamis sesuai urutan detail
                                $dsDetail = [
                                    (string) ($detailIndex + 1) => $dsDetail,
                                ];
                                $needsFix = true;
                                break;
                            }
                        }

                        if ($isValidStructure && $needsFix) {
                            $detail->data_pendukung_sampling = json_encode($dsDetail);
                            $detail->save();
                            $fixedCount++;
                            continue;
                        }

                        if ($isValidStructure && ! $needsFix) {
                            $skippedCount++;
                            continue;
                        }

                        // Struktur lama dibungkus jadi struktur baru dengan key dinamis
                        $originalStructure = [
                            (string) ($detailIndex + 1) => [
                                "periode_kontrak" => $detail->periode_kontrak,
                                "data_sampling"   => $dsDetail,
                            ],
                        ];

                        $detail->data_pendukung_sampling = json_encode($originalStructure);
                        $detail->save();
                        $fixedCount++;
                    }

                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollback();
                    $errorCount++;
                    $errorDetails[] = [
                        'document' => $data->no_document,
                        'error'    => $th->getMessage(),
                    ];
                    Log::error('Error fixing detail structure: ' . $data->no_document, [
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString(),
                    ]);
                }
            }

            return response()->json([
                'message'         => 'Fix detail structure completed',
                'fixed'           => $fixedCount,
                'skipped'         => $skippedCount,
                'errors'          => $errorCount,
                'total_documents' => count($dataList),
                'error_details'   => $errorDetails,
            ], 200);
        } catch (\Throwable $th) {
            Log::error('System error in fixDetailStructure', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error'   => app()->environment('local') ? $th->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    public function searchQuotation(Request $request)
    {
        try {
            $data           = QuotationKontrakH::where('is_active', true)->where('no_document', 'like', '%' . $request->no_document . '%')->first();
            $tipe_penawaran = 'kontrak';

            if (! $data) {
                $data           = QuotationNonKontrak::where('is_active', true)->where('no_document', 'like', '%' . $request->no_document . '%')->first();
                $tipe_penawaran = 'non_kontrak';
            }

            if (! $data) {
                return response()->json([
                    'message' => 'Data Quotation tidak ditemukan!',
                ], 404);
            }

            return response()->json([
                'data'           => $data,
                'tipe_penawaran' => $tipe_penawaran,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function renderPdfQuotation(Request $request)
    {
        try {
            if ($request->mode == 'copy') {
                if ($request->tipe_penawaran == 'kontrak') {
                    $render = new RenderKontrakCopy();
                    $render->renderDataQuotation($request->id, 'id');
                } else {
                    $render = new RenderNonKontrakCopy();
                    $render->renderHeader($request->id, 'id');
                }
                return response()->json(['message' => 'Render copy selesai.']);
            } else {
                if ($request->tipe_penawaran == 'kontrak') {
                    $data      = QuotationKontrakH::find($request->id);
                    $jobTaskId = JobTask::insertGetId([
                        'job'         => 'RenderPdfPenawaran',
                        'status'      => 'processing',
                        'no_document' => $data->no_document,
                        'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    $job = new RenderPdfPenawaran($request->id, 'kontrak');
                    $this->dispatch($job);
                } else {
                    $data      = QuotationNonKontrak::find($request->id);
                    $jobTaskId = JobTask::insertGetId([
                        'job'         => 'RenderPdfPenawaran',
                        'status'      => 'processing',
                        'no_document' => $data->no_document,
                        'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    $job = new RenderPdfPenawaran($request->id, 'non kontrak');
                    $this->dispatch($job);
                }

                return response()->json([
                    'message' => 'Job rendering PDF sedang diproses.',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 401);
        }
    }

    public function searchTokenExpired(Request $request)
    {
        try {
            $data = ExpiredLink::where('id_quotation', $request->id)
                ->where('quotation_status', $request->tipe_penawaran)
                ->first();

            if (! $data) {
                return response()->json([
                    'message' => 'Data token expired link tidak ditemukan!',
                ], 404);
            }

            return response()->json([
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function searchDataPelanggan(Request $request)
    {
        try {
            $data = MasterPelanggan::where('id_pelanggan', $request->id_pelanggan)
                ->where('is_active', true)
                ->whereNotIn('sales_id', ['127'])
                ->first();
            if (! $data) {
                return response()->json([
                    'message' => 'Data pelanggan tidak ditemukan!',
                ], 404);
            }

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line'    => $th->getLine(),
            ], 500);
        }
    }

    public function generateToken(Request $request)
    {
        $token = $request['token']['token'];
        if (! $token) {
            return response()->json(['message' => 'Token not found.!', 'status' => '404'], 401);
        }
        $expired     = Carbon::now()->addMonths(3)->format('Y-m-d');
        $get_expired = DB::table('expired_link_quotation')
            ->where('token', $token)
            ->first();

        if ($get_expired) {
            $bodyToken = (object) $get_expired;
            unset($bodyToken->id);
            $bodyToken->expired = $expired;

            $id_quotation     = $bodyToken->id_quotation;
            $quotation_status = $bodyToken->quotation_status;

            if ($quotation_status == 'non_kontrak') {
                $data = QuotationNonKontrak::where('id', $id_quotation)->first();
            } else if ($quotation_status == 'kontrak') {
                $data = QuotationKontrakH::where('id', $id_quotation)->first();
            }

            $bodyToken = (array) $bodyToken;
            if ($data) {
                $id_token       = GenerateLink::insertGetId($bodyToken);
                $data->expired  = $expired;
                $data->id_token = $id_token;
                $data->save();

                return response()->json(['message' => 'Token has been reactivated', 'status' => '200'], 200);
            } else {
                return response()->json(['message' => 'Token not found', 'status' => '404'], 200);
            }
        } else {
            return response()->json(['message' => 'Token not found', 'status' => '404'], 200);
        }
    }

    public function updateCustomer(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            if ($request->tipe_penawaran == 'kontrak') {
                $quotation = QuotationKontrakH::where('no_document', $request->no_document)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            } else {
                $quotation = QuotationNonKontrak::where('no_document', $request->no_document)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            }

            if (! $quotation) {
                return response()->json(['message' => 'Quotation not found'], 404);
            }

            $quotation->nama_perusahaan = $request->nama_pelanggan;
            $quotation->konsultan       = $request->nama_konsultan ?: null;
            $quotation->alamat_sampling = $request->alamat_sampling ?: null;
            $quotation->save();

            $orderHeader = OrderHeader::where('no_document', $quotation->no_document)
                ->where('is_active', true)
                ->latest('id')
                ->first();

            if (! $orderHeader) {
                return response()->json(['message' => 'Order Header not found'], 404);
            }


            $orderHeader->nama_perusahaan = $request->nama_pelanggan;
            $orderHeader->konsultan       = $request->nama_konsultan ?: null;
            $orderHeader->alamat_sampling = $request->alamat_sampling ?: null;
            $orderHeader->save();

            OrderDetail::where('id_order_header', $orderHeader->id)
                ->update([
                    'nama_perusahaan' => $request->nama_pelanggan,
                    'konsultan'       => $request->nama_konsultan ?: null,
                ]);

            Jadwal::where('no_quotation', $request->no_document)
                ->where('is_active', true)
                ->update(['nama_perusahaan' => $request->nama_pelanggan], ['alamat' => $request->alamat_sampling]);

            DB::commit();
            return response()->json(['message' => 'Customer updated successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update customer'], 500);
        }
    }

    public function renderJadwalCopy(Request $request)
    {
        try {

            $timestamp = Carbon::now()->format('Y-m-d H:i:s');

            $dataRequest = (object) [
                'no_document'  => $request->no_quotation,
                'quotation_id' => $request->quotation_id,
                'karyawan'     => $this->karyawan,
                'karyawan_id'  => $this->user_id,
                'timestamp'    => $timestamp,
            ];
            (new RenderJadwalKontrakCopy($dataRequest))
                ->where('quotation_id', $request->quotation_id)
                ->where('tanggal_penawaran', $request->tanggal_penawaran)
                ->servisRenderJadwal();

            return response()->json([
                'status'  => true,
                'message' => 'Berhasil generate jadwal',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function encrypt(Request $request)
    {
        return response()->json(['slice' => Slice::makeSlice($request->controller_name, $request->method_name)], 200);
    }

    public function decrypt(Request $request)
    {
        $decryptedSlice = Slice::makeDecrypt($request->slice, false);
        $route          = json_decode($decryptedSlice);

        if (empty($route->controller) || empty($route->function)) {
            return response()->json(['error' => 'Invalid slice'], 400);
        }

        return response()->json(['controller' => $route->controller, 'method' => $route->function], 200);
    }

    // ===================== PERUBAHAN NO SAMPEL ====================
    public function updateDataNoSampel(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach ($request->list_data as $item) {
                $noSampelLama = $item['no_sampel_lama'];
                $noSampelBaru = $item['no_sampel_baru'];

                // =====================
                // VALIDASI KATEGORI
                // =====================
                $detailLama = OrderDetail::where('no_sampel', $noSampelLama)
                    ->where('is_active', 1)
                    ->first(['kategori_2', 'kategori_3', 'parameter', 'tanggal_terima']);

                $detailBaru = OrderDetail::where('no_sampel', $noSampelBaru)
                    ->where('is_active', 1)
                    ->first(['kategori_2', 'kategori_3', 'parameter']);

                $kategoriLama       = preg_replace('/^\d+-/', '', $detailLama->kategori_2);
                $kategoriBaru       = preg_replace('/^\d+-/', '', $detailBaru->kategori_2);
                $kategoriDetailLama = preg_replace('/^\d+-/', '', $detailLama->kategori_3);
                $kategoriDetailBaru = preg_replace('/^\d+-/', '', $detailBaru->kategori_3);

                if ($kategoriLama !== $kategoriBaru || $kategoriDetailLama !== $kategoriDetailBaru) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Kategori sampel tidak sama: {$noSampelLama} ({$kategoriLama} - {$kategoriDetailLama}) vs {$noSampelBaru} ({$kategoriBaru} - {$kategoriDetailBaru})",
                    ], 500);
                }

                // =====================
                // VALIDASI PARAMETER
                // =====================
                $paramLama = collect(
                    is_array($detailLama->parameter)
                        ? $detailLama->parameter
                        : (json_decode($detailLama->parameter, true) ?? [])
                )->sort()->values()->toArray();

                $paramBaru = collect(
                    is_array($detailBaru->parameter)
                        ? $detailBaru->parameter
                        : (json_decode($detailBaru->parameter, true) ?? [])
                )->sort()->values()->toArray();

                if ($paramLama !== $paramBaru) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Parameter sampel tidak sama: {$noSampelLama} vs {$noSampelBaru}",
                        'detail' => [
                            'lama' => $paramLama,
                            'baru' => $paramBaru,
                        ]
                    ], 500);
                }

                $tanggalTerima = OrderDetail::where('no_sampel', $noSampelLama)
                    ->where('is_active', 1)
                    ->value('tanggal_terima');

                if ($tanggalTerima) {
                    OrderDetail::where('no_sampel', $noSampelBaru)
                        ->where('is_active', 1)
                        ->update(['tanggal_terima' => $tanggalTerima]);
                }

                $models = Fixing::KategoriModel($kategoriLama);

                if (empty($models)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Kategori '{$kategoriLama}' tidak ditemukan di helper Fixing",
                    ], 422);
                }

                // =====================
                // BACKUP + DUPLICATE (model per kategori)
                // =====================
                $this->backupToSql($models, $noSampelLama);
                $this->backupToSql($models, $noSampelBaru);
                $this->duplicateData($models, $noSampelLama, $noSampelBaru);

                // =====================
                // UPDATE (model global: tftc, tftct)
                // =====================
                $globalModels = Fixing::GlobalUpdateModel();
                $this->updateFromLamaKeBaru($globalModels, $noSampelLama, $noSampelBaru);
            }

            DB::commit();
            return response()->json(['message' => 'Berhasil update no sampel'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }



    public function cekQrPsikologiExpired(Request $request)
    {
        try {
            $orderHeaderIds = OrderDetail::where('no_quotation', $request->no_quotation)
                ->pluck('id_order_header')
                ->unique()
                ->toArray();

            if (empty($orderHeaderIds)) {
                return response()->json(['message' => 'No Quotation tidak ditemukan pada Order Detail!'], 404);
            }

            $qrPsikologi = DB::table('qr_psikologi')
                ->whereIn('id_quotation', $orderHeaderIds)
                ->where('type', 'administrator')
                ->get();

            if ($qrPsikologi->isEmpty()) {
                return response()->json(['message' => 'Data QR Psikologi Administrator tidak ditemukan!'], 404);
            }

            return response()->json([
                'data' => $qrPsikologi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateQrPsikologiExpired(Request $request)
    {
        try {
            $update = DB::table('qr_psikologi')
                ->where('id', $request->id)
                ->where('type', 'administrator')
                ->update(['expired' => $request->expired]);

            return response()->json(['message' => 'Berhasil update expired QR Psikologi!'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function backupToSql(array $models, string $noSampel, string $prefix = ''): void
    {
        $safeName  = str_replace('/', '_', $noSampel);
        $directory = public_path('fixing/bckp');
        $fileName  = $prefix ? "{$safeName}_{$prefix}.sql" : "{$safeName}.sql";
        $filePath  = "{$directory}/{$fileName}";

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $sqlContent  = "-- Backup untuk no_sampel: {$noSampel}\n";
        $sqlContent .= "-- Dibuat pada: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($models as $modelClass) {
            $records = $modelClass::where('no_sampel', $noSampel)->get();

            if ($records->isEmpty()) continue;

            $table       = (new $modelClass)->getTable();
            $sqlContent .= "-- Tabel: {$table}\n";

            foreach ($records as $record) {
                $data    = $record->getAttributes();
                $columns = implode(', ', array_keys($data));
                $values  = implode(', ', array_map(function ($val) {
                    if (is_null($val)) return 'NULL';
                    return "'" . addslashes($val) . "'";
                }, array_values($data)));

                $sqlContent .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
            }

            $sqlContent .= "\n";
        }

        file_put_contents($filePath, $sqlContent);
    }


    private function duplicateData(array $models, string $noSampelLama, string $noSampelBaru): void
    {
        $wsValueAirClass = \App\Models\WsValueAir::class;
        $relasiWs        = Fixing::WsValueAirRelasi(); // ['id_colorimetri' => Colorimetri::class, ...]

        // Map: old_id => new_id untuk tiap model relasi
        // Ini dipakai nanti buat update FK di WsValueAir
        $idMapping = []; // ['id_colorimetri' => [old_id => new_id], ...]

        foreach ($models as $modelClass) {
            // WsValueAir diproses terakhir secara terpisah
            if ($modelClass === $wsValueAirClass) continue;

            $records = $modelClass::where('no_sampel', $noSampelLama)->get();

            foreach ($records as $record) {
                $oldId    = $record->id;
                $newData  = $record->replicate()->fill(['no_sampel' => $noSampelBaru]);
                $newData->save();
                $newId = $newData->id;

                // Cek apakah model ini punya FK di WsValueAir
                foreach ($relasiWs as $fkColumn => $relasiModel) {
                    if ($modelClass === $relasiModel) {
                        $idMapping[$fkColumn][$oldId] = $newId;
                    }
                }
            }
        }

        // =====================
        // Sekarang proses WsValueAir
        // =====================
        $wsRecords = $wsValueAirClass::where('no_sampel', $noSampelLama)->get();

        foreach ($wsRecords as $wsRecord) {
            $newWs = $wsRecord->replicate()->fill(['no_sampel' => $noSampelBaru]);

            // Update semua FK ke ID yang baru
            foreach ($relasiWs as $fkColumn => $relasiModel) {
                $oldFkId = $wsRecord->$fkColumn;

                if (!is_null($oldFkId) && isset($idMapping[$fkColumn][$oldFkId])) {
                    $newWs->$fkColumn = $idMapping[$fkColumn][$oldFkId];
                }
            }

            $newWs->save();
        }
    }

    private function updateFromLamaKeBaru(array $models, string $noSampelLama, string $noSampelBaru): void
    {
        $skipKolom = Fixing::SkipKolom();

        foreach ($models as $modelClass) {
            $dataLama = $modelClass::where('no_sample', $noSampelLama)->first();

            if (!$dataLama) continue;

            $dataBaru = $modelClass::where('no_sample', $noSampelBaru)->first();

            if (!$dataBaru) continue;

            // Ambil semua kolom dari data lama yang nilainya tidak null
            // dan bukan kolom yang di-skip
            $kolommYangDicopy = collect($dataLama->getAttributes())
                ->filter(function ($value, $key) use ($skipKolom) {
                    return !is_null($value) && !in_array($key, $skipKolom);
                })
                ->toArray();

            if (empty($kolommYangDicopy)) continue;

            $dataBaru->update($kolommYangDicopy);
        }
    }

    // ===================== END PERUBAHAN NO SAMPEL ====================







    // ==================================== INI PEMISAH TESTING DAN QUERY BACKEND ====================================

    public function test()
    {
        $data = SalesKpiMonthly::run();

        return response()->json($data, 200);
    }


    public function updateParentAksesMenu(Request $request)
    {

        try {
            $data = Menu::where('is_active', true)->get();

            $parentMap = $this->buildParentMapping($data);

            $aksesMenus = AksesMenu::all();


            $updated = 0;

            foreach ($aksesMenus as $aksesMenu) {
                $akses = $aksesMenu->akses;

                if (is_string($akses)) {
                    $akses = json_decode($akses, true);
                }


                if (is_array($akses) && ! empty($akses)) {
                    $updatedAkses = collect($akses)->map(function ($item) use ($parentMap) {
                        $menuName       = $item['name'] ?? '';
                        $item['parent'] = $parentMap[$menuName] ?? '';

                        return $item;
                    })->toArray();

                    $aksesMenu->akses = $updatedAkses;
                    $aksesMenu->save();

                    $updated++;
                }
            }

            return response()->json([
                'message' => "Successfully updated parent for {$updated} akses menu records",
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function buildParentMapping($menus)
    {
        $parentMap = [];

        foreach ($menus as $menu) {
            $parentName = $menu->menu;

            if (isset($menu->submenu) && is_array($menu->submenu)) {
                foreach ($menu->submenu as $submenu) {
                    $submenu     = (object) $submenu;
                    $submenuName = $submenu->nama_inden_menu;

                    $parentMap[$submenuName] = $parentName;

                    // Level 3: Menu di bawah submenu
                    if (isset($submenu->sub_menu) && is_array($submenu->sub_menu)) {
                        foreach ($submenu->sub_menu as $subMenuItem) {
                            $parentMap[$subMenuItem] = $parentName . '/' . $submenuName;
                        }
                    }
                }
            }
        }

        return $parentMap;
    }

    public function searchFeeSampling(Request $request)
    {
        $res = $this->processFeeSampling($request->batch_id, $request->tanggal);

        if (isset($res['error'])) {
            return response()->json(['message' => $res['error']], $res['code']);
        }

        $isDifferent = $res['current_fee'] != $res['updated_fee'];

        return response()->json([
            'message' => $isDifferent
                ? 'Berhasil mengambil data terbaru'
                : 'Tidak terdapat perbedaan fee sampling',
            'status' => $isDifferent,
            'data' => [
                'current_fee' => $res['current_fee'],
                'updated_fee' => $res['updated_fee'],
                'details' => $res['details'],
            ]
        ]);
    }

    public function updateFeeSampling(Request $request)
    {
        DB::beginTransaction();

        try {
            $res = $this->processFeeSampling($request->batch_id, $request->tanggal);

            if (isset($res['error'])) {
                return response()->json(['message' => $res['error']], $res['code']);
            }

            $fee = $res['fee_sampling'];
            $rekap = $res['rekap'];

            $fee->update([
                'total_fee_request' => $res['updated_fee']
            ]);

            foreach ($rekap['harian'] as $item) {
                PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $fee->id)
                    ->where('tanggal', $item['tanggal'])
                    ->update([
                        'total_fee' => $item['total_fee'],
                        'fee_pokok' => $item['fee_pokok'],
                        'fee_tambahan' => $item['fee_tambahan'],
                        'jumlah_tempat' => $item['jumlah_tempat'],
                        'rincian_fee_pokok' => $item['rincian_fee_pokok'],
                        'fee_tambahan_rincian' => $item['fee_tambahan_rincian'],
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Berhasil mengupdate fee sampling',
                'status' => true
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => $th->getMessage(),
                'status' => false
            ], 500);
        }
    }

    private function processFeeSampling($batch_id, $tanggal = [])
    {
        $fee_sampling = PengajuanFeeSampling::with('detail_fee')
            ->where('batch_id', $batch_id)
            ->first();

        if (!$fee_sampling) {
            return ['error' => 'Data tidak ditemukan', 'code' => 404];
        }
        $tanggal = $fee_sampling->detail_fee->pluck('tanggal')->toArray();
        $master = MasterKaryawan::find($fee_sampling->user_id);

        if (!$master || !$master->warna) {
            return ['error' => 'Level Sampler Belum Ditentukan', 'code' => 401];
        }

        $history = HistoryLevelSampler::where('user_id', $master->user_id)
            ->latest()
            ->first();
        $warnaFinal = $master->warna;
        if ($history && !empty($tanggal)) {
            $changeDate = Carbon::parse($history->created_at)->startOfDay();

            $before = collect($tanggal)->contains(fn($t) => Carbon::parse($t)->lt($changeDate));
            $after  = collect($tanggal)->contains(fn($t) => Carbon::parse($t)->gte($changeDate));

            if ($before && $after) {
                return [
                    'error' => 'Tanggal pengajuan tidak boleh melewati perubahan level sampler',
                    'code' => 422
                ];
            }

            $warnaFinal = $before ? $history->old_warna : $history->new_warna;
        }
        $level = MasterFeeSampling::where('warna', $warnaFinal)
            ->where('is_active', true)
            ->first();

        if (!$level) {
            return ['error' => 'Level Sampler Tidak Ditemukan', 'code' => 404];
        }

        $generate = new GenerateFeeSampling();

        $rekap = $generate->rekapFeeSampling(
            $fee_sampling->user_id,
            $level->kategori,
            $fee_sampling->detail_fee->pluck('tanggal')->toArray()
        );

        $detailNow = $fee_sampling->detail_fee->keyBy('tanggal');

        $details = collect($rekap['harian'])->map(function ($item) use ($detailNow) {
            $current = $detailNow[$item['tanggal']] ?? null;

            return [
                'tanggal' => $item['tanggal'],

                'current' => [
                    'total_fee' => $current->total_fee ?? 0,
                    'fee_pokok' => $current->fee_pokok ?? 0,
                    'fee_tambahan' => $current->fee_tambahan ?? 0,
                    'jumlah_tempat' => $current->jumlah_tempat ?? 0,
                    'rincian_fee_pokok' => $current->rincian_fee_pokok ?? null,
                    'fee_tambahan_rincian' => $current->fee_tambahan_rincian ?? null,
                ],

                'updated' => [
                    'total_fee' => $item['total_fee'] ?? 0,
                    'fee_pokok' => $item['fee_pokok'] ?? 0,
                    'fee_tambahan' => $item['fee_tambahan'] ?? 0,
                    'jumlah_tempat' => $item['jumlah_tempat'] ?? 0,
                    'rincian_fee_pokok' => $item['rincian_fee_pokok'] ?? null,
                    'fee_tambahan_rincian' => $item['fee_tambahan_rincian'] ?? null,
                ]
            ];
        })->values();

        return [
            'fee_sampling' => $fee_sampling,
            'rekap' => $rekap,
            'current_fee' => $fee_sampling->total_fee_request,
            'updated_fee' => $rekap['total_mingguan'],
            'details' => $details,
        ];
    }

    // public function exportPelangganBelumOrder(Request $request)
    // {
    //     ini_set('memory_limit', '-1');
    //     set_time_limit(0);

    //     try {
    //         $data = MasterPelanggan::with([
    //             'kontak_pelanggan:id,pelanggan_id,no_tlp_perusahaan',
    //             'pic_pelanggan:id,pelanggan_id,nama_pic,no_tlp_pic,jabatan_pic',
    //         ])
    //         ->whereNotIn('sales_id', [127])
    //         ->whereNotNull('sales_id')
    //         ->where('is_active', 1)
    //         ->whereDoesntHave('quotasiNonKontrak')
    //         ->whereDoesntHave('quotasiKontrak')
    //         ->whereYear('created_at', Carbon::now()->subYear(1))
    //         // ->whereDoesntHave('kontak_pelanggan', function ($query) {
    //         //     $query->whereHas('logWebphone', function ($q) {
    //         //         $q->where('created_at', '>=', Carbon::now()->subMonths(3));
    //         //     });
    //         // })
    //         ->get();

    //         // Setup Spreadsheet
    //         $spreadsheet = new Spreadsheet();
    //         $sheet       = $spreadsheet->getActiveSheet();

    //         $title = "LAPORAN PELANGGAN BELUM KELUAR QUOTATION - " . strtoupper(Carbon::now()->locale('id')->isoFormat('MMMM YYYY'));

    //         $sheet->setCellValue('A1', $title);
    //         $sheet->mergeCells('A1:G1'); // 7 kolom A-G
    //         $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    //         $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //         $headers = [
    //             'No',
    //             'ID Pelanggan',
    //             'Nama Pelanggan',
    //             'No. Telp Perusahaan',
    //             'Nama PIC',
    //             'No. Telp PIC',
    //             'Jabatan PIC',
    //         ];
    //         $sheet->fromArray($headers, null, 'A2');

    //         $headerStyle = [
    //             'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    //             'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    //             'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343A40']],
    //             'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    //         ];
    //         $sheet->getStyle('A2:G2')->applyFromArray($headerStyle); // fix: A2:G2

    //         $row = 3;
    //         $no  = 1;

    //         foreach ($data as $item) {
    //             $kontak = $item->kontak_pelanggan->first();
    //             $pic    = $item->pic_pelanggan->first();

    //             $sheet->setCellValue('A' . $row, $no++);
    //             $sheet->setCellValue('B' . $row, $item->id_pelanggan);
    //             $sheet->setCellValue('C' . $row, $item->nama_pelanggan);
    //             $sheet->setCellValue('D' . $row, $kontak->no_tlp_perusahaan ?? '-');
    //             $sheet->setCellValue('E' . $row, $pic->nama_pic ?? '-');
    //             $sheet->setCellValue('F' . $row, $pic->no_tlp_pic ?? '-');
    //             $sheet->setCellValue('G' . $row, $pic->jabatan_pic ?? '-');

    //             $row++;
    //         }

    //         $lastRow = $row - 1;

    //         foreach (range('A', 'G') as $columnID) { // fix: A-G
    //             $sheet->getColumnDimension($columnID)->setAutoSize(true);
    //         }

    //         $sheet->getStyle('A3:G' . $lastRow)->getAlignment()->setWrapText(false); // fix: A3:G
    //         $sheet->getStyle('A3:G' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    //         $sheet->getStyle('A2:G' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN); // fix: A2:G
    //         $sheet->freezePane('A3');

    //         $writer   = new Xlsx($spreadsheet);
    //         $fileName = "Pelanggan_Belum_Quotation_" . date('Ymd_His') . ".xlsx";

    //         header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //         header('Content-Disposition: attachment; filename="' . $fileName . '"');
    //         header('Cache-Control: max-age=0');

    //         $writer->save('php://output');
    //         exit;

    //     } catch (\Exception $e) {
    //         dd($e->getMessage(), $e->getLine(), $e->getFile());
    //     }
    // }

    public function checkInvoiceDetail(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        try {
            $invoiceRows = Invoice::with(['recordPembayaran.sales_in_detail.header', 'recordWithdraw.sales_in_detail.header'])
                ->where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $summary = SummaryInvoice::where('no_invoice', $noInvoice)->first();
            $payments = RecordPembayaranInvoice::with('sales_in_detail.header')
                ->where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('tgl_pembayaran')
                ->orderBy('id')
                ->get();

            $withdraws = Withdraw::with('sales_in_detail.header')
                ->where('no_invoice', $noInvoice)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $salesInDetails = SalesInDetail::with('header')
                ->where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            $firstInvoice = $invoiceRows->first();
            $totalTagihan = (float) ($summary->total_tagihan ?? 0);
            if ($totalTagihan <= 0) {
                $totalTagihan = (float) $invoiceRows->sum(function ($item) {
                    return (float) ($item->nilai_tagihan ?? $item->total_tagihan ?? 0);
                });
            }

            $totalPembayaran = (float) $payments->sum('nilai_pembayaran');
            $totalPengurangan = (float) $payments->sum('nilai_pengurangan');
            $totalWithdraw = (float) $withdraws->sum('nilai_pembayaran');
            $nilaiPelunasanInvoice = (float) $invoiceRows->sum('nilai_pelunasan');
            $lebihBayar = max(
                (float) $invoiceRows->sum('lebih_bayar'),
                (float) $payments->sum('lebih_bayar'),
                max(0, ($totalPembayaran + $totalPengurangan) - $totalTagihan)
            );
            $sisaBayar = max(0, $totalTagihan - $totalPembayaran - $totalPengurangan);

            if ($lebihBayar > 0) {
                $status = 'Kelebihan Pembayaran';
            } elseif ($sisaBayar <= 0) {
                $status = 'Lunas';
            } elseif ($totalPembayaran > 0 || $totalPengurangan > 0 || $totalWithdraw > 0) {
                $status = 'Belum Lunas';
            } else {
                $status = 'Belum Ada Pembayaran';
            }

            $filename = $summary->filename ?? $firstInvoice->filename ?? null;

            return response()->json([
                'message' => 'Data invoice ditemukan.',
                'data' => [
                    'invoice' => $firstInvoice,
                    'invoice_rows' => $invoiceRows->values(),
                    'summary' => $summary,
                    'calculation' => [
                        'total_tagihan' => $totalTagihan,
                        'total_pembayaran' => $totalPembayaran,
                        'total_pengurangan' => $totalPengurangan,
                        'total_withdraw' => $totalWithdraw,
                        'nilai_pelunasan_invoice' => $nilaiPelunasanInvoice,
                        'lebih_bayar' => $lebihBayar,
                        'sisa_bayar' => $sisaBayar,
                        'status' => $summary->status_lunas ?? $status,
                    ],
                    'payments' => $payments->values(),
                    'withdraws' => $withdraws->values(),
                    'sales_in_details' => $salesInDetails->values(),
                    'pdf' => [
                        'filename' => $filename,
                        'path' => $filename ? 'invoice/' . $filename : null,
                    ],
                    'diagnostics' => $this->buildInvoiceDiagnostics($noInvoice, $invoiceRows),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
    public function fixInvoiceIssue(Request $request)
    {
        $noInvoice = trim((string) $request->input('no_invoice'));
        $action = trim((string) $request->input('action'));

        if ($noInvoice === '') {
            return response()->json(['message' => 'Nomor invoice wajib diisi.'], 422);
        }

        $allowedActions = [
            'normalize_order_detail_konsultan',
            'sync_invoice_nilai_pelunasan',
            'clear_small_invoice_upload_file',
        ];

        if (!in_array($action, $allowedActions, true)) {
            return response()->json(['message' => 'Action fixing tidak dikenali.'], 422);
        }

        DB::beginTransaction();
        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->get();

            if ($invoiceRows->isEmpty()) {
                DB::rollBack();
                return response()->json(['message' => 'Invoice tidak ditemukan atau tidak aktif.'], 404);
            }

            $payload = [];
            $message = 'Data invoice berhasil diperbaiki.';

            if ($action === 'normalize_order_detail_konsultan') {
                $noOrders = $invoiceRows->pluck('no_order')->filter()->unique()->values();
                if ($noOrders->isEmpty()) {
                    DB::rollBack();
                    return response()->json(['message' => 'Invoice tidak memiliki no_order.'], 422);
                }

                $payload['updated_order_detail'] = OrderDetail::whereIn('no_order', $noOrders)
                    ->where('is_active', true)
                    ->where('konsultan', '')
                    ->update([
                        'konsultan' => null,
                        'updated_by' => $this->karyawan,
                        'updated_at' => Carbon::now(),
                    ]);

                $payload['updated_daily_qsd'] = DB::table('daily_qsd')
                    ->whereIn('no_order', $noOrders)
                    ->where('konsultan', '')
                    ->update([
                        'konsultan' => null,
                        'updated_at' => Carbon::now(),
                    ]);

                $message = 'Konsultan kosong berhasil dinormalisasi ke NULL. Jika nilai compare masih belum berubah, tunggu scheduler QSD / jalankan update daily_qsd.';
            }

            if ($action === 'sync_invoice_nilai_pelunasan') {
                $totalPembayaran = (float) RecordPembayaranInvoice::where('no_invoice', $noInvoice)
                    ->where('is_active', true)
                    ->sum('nilai_pembayaran');

                $firstInvoiceId = $invoiceRows->sortBy('id')->first()->id;

                $payload['updated_invoice_rows_reset'] = Invoice::where('no_invoice', $noInvoice)
                    ->where('is_active', true)
                    ->where('id', '!=', $firstInvoiceId)
                    ->update([
                        'nilai_pelunasan' => 0,
                        'updated_by' => $this->karyawan,
                        'updated_at' => Carbon::now(),
                    ]);

                $payload['updated_invoice_rows'] = Invoice::where('id', $firstInvoiceId)
                    ->where('is_active', true)
                    ->update([
                        'nilai_pelunasan' => $totalPembayaran,
                        'updated_by' => $this->karyawan,
                        'updated_at' => Carbon::now(),
                    ]);

                $payload['nilai_pelunasan_target'] = $totalPembayaran;
                $message = 'Nilai pelunasan invoice berhasil disamakan dengan total pembayaran real.';
            }

            if ($action === 'clear_small_invoice_upload_file') {
                $payload['updated_invoice_rows'] = Invoice::where('no_invoice', $noInvoice)
                    ->where('is_active', true)
                    ->whereNotNull('upload_file')
                    ->where(function ($query) {
                        $query->where('nilai_tagihan', '<', 5000000)
                            ->orWhere(function ($subQuery) {
                                $subQuery->whereNull('nilai_tagihan')
                                    ->where('total_tagihan', '<', 5000000);
                            });
                    })
                    ->update([
                        'upload_file' => null,
                        'updated_by' => $this->karyawan,
                        'updated_at' => Carbon::now(),
                    ]);

                $message = 'Upload file invoice kecil berhasil dihapus. Setelah ini invoice perlu dirender ulang.';
            }

            DB::commit();

            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->get();

            $payload['diagnostics'] = $this->buildInvoiceDiagnostics($noInvoice, $invoiceRows);

            return response()->json([
                'message' => $message,
                'data' => $payload,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function buildInvoiceDiagnostics(string $noInvoice, $invoiceRows): array
    {
        $issues = [];
        $noOrders = collect($invoiceRows)->pluck('no_order')->filter()->unique()->values();
        $invoiceValue = (float) collect($invoiceRows)->sum(function ($item) {
            return (float) ($item->nilai_tagihan ?? $item->total_tagihan ?? 0);
        });

        $totalPembayaranReal = (float) RecordPembayaranInvoice::where('no_invoice', $noInvoice)
            ->where('is_active', true)
            ->sum('nilai_pembayaran');
        $nilaiPelunasanInvoice = (float) collect($invoiceRows)->sum('nilai_pelunasan');
        $nilaiPelunasanDifference = abs($nilaiPelunasanInvoice - $totalPembayaranReal);

        if ($nilaiPelunasanDifference > 1) {
            $issues[] = [
                'code' => 'INVOICE_NILAI_PELUNASAN_MISMATCH',
                'severity' => 'warning',
                'title' => 'Nilai pelunasan invoice tidak sesuai',
                'message' => 'Nilai pelunasan invoice berbeda dari total pembayaran aktif. Fix ini hanya update nilai_pelunasan invoice.',
                'detail' => [
                    'nilai_pelunasan_invoice' => $nilaiPelunasanInvoice,
                    'total_pembayaran_real' => $totalPembayaranReal,
                    'difference' => $nilaiPelunasanDifference,
                ],
                'fixable' => true,
                'action' => 'sync_invoice_nilai_pelunasan',
                'button_label' => 'Fix Nilai Pelunasan',
            ];
        }

        $smallInvoiceUploads = collect($invoiceRows)
            ->filter(function ($row) {
                $nilaiTagihan = (float) ($row->nilai_tagihan ?? $row->total_tagihan ?? 0);
                return $nilaiTagihan < 5000000 && !empty($row->upload_file);
            })
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'no_order' => $row->no_order,
                    'nilai_tagihan' => (float) ($row->nilai_tagihan ?? $row->total_tagihan ?? 0),
                    'upload_file' => $row->upload_file,
                ];
            })
            ->values();

        if ($smallInvoiceUploads->isNotEmpty()) {
            $issues[] = [
                'code' => 'SMALL_INVOICE_UPLOAD_FILE_EXISTS',
                'severity' => 'warning',
                'title' => 'Invoice kecil masih punya file upload',
                'message' => 'Nilai tagihan di bawah 5 juta tapi invoice.upload_file masih terisi. Hapus upload_file lalu render ulang invoice.',
                'detail' => [
                    'rows' => $smallInvoiceUploads,
                ],
                'fixable' => true,
                'action' => 'clear_small_invoice_upload_file',
                'button_label' => 'Hapus Upload File',
                'needs_rerender' => true,
            ];
        }
        $dailyQsdRows = DB::table('daily_qsd')
            ->whereRaw("REPLACE(no_invoice, ' (Lunas)', '') = ?", [$noInvoice])
            ->get([
                'uuid',
                'no_order',
                'no_invoice',
                'no_quotation',
                'nama_perusahaan',
                'konsultan',
                'biaya_akhir',
                'nilai_invoice',
                'tanggal_sampling_min',
            ]);

        $dailyQsdRows->each(function ($row) use (&$issues) {
            $biayaAkhir = (float) ($row->biaya_akhir ?? 0);
            $nilaiInvoice = (float) ($row->nilai_invoice ?? 0);
            $difference = abs($biayaAkhir - $nilaiInvoice);

            if ($difference > 50) {
                $issues[] = [
                    'code' => 'DAILY_QSD_COMPARE_MISMATCH',
                    'severity' => 'danger',
                    'title' => 'Invoice terdeteksi masuk compare',
                    'message' => 'Nilai biaya akhir di daily_qsd tidak sesuai dengan nilai invoice.',
                    'detail' => [
                        'no_order' => $row->no_order,
                        'biaya_akhir' => $biayaAkhir,
                        'nilai_invoice' => $nilaiInvoice,
                        'difference' => $difference,
                    ],
                    'fixable' => false,
                ];
            }
        });

        $orderDetailStats = null;
        if ($noOrders->isNotEmpty()) {
            $orderDetailStats = DB::table('order_detail')
                ->whereIn('no_order', $noOrders)
                ->where('is_active', true)
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN konsultan IS NULL THEN 1 ELSE 0 END) as konsultan_null, SUM(CASE WHEN konsultan = '' THEN 1 ELSE 0 END) as konsultan_empty")
                ->first();

            $emptyCount = (int) ($orderDetailStats->konsultan_empty ?? 0);
            $nullCount = (int) ($orderDetailStats->konsultan_null ?? 0);

            if ($emptyCount > 0) {
                $issues[] = [
                    'code' => 'ORDER_DETAIL_KONSULTAN_EMPTY_STRING',
                    'severity' => 'warning',
                    'title' => 'Konsultan order detail belum seragam',
                    'message' => "Ada {$emptyCount} order_detail.konsultan berisi string kosong. Normalisasi ke NULL supaya grouping QSD tidak pecah.",
                    'detail' => [
                        'no_orders' => $noOrders->values(),
                        'konsultan_null' => $nullCount,
                        'konsultan_empty' => $emptyCount,
                    ],
                    'fixable' => true,
                    'action' => 'normalize_order_detail_konsultan',
                    'button_label' => 'Fix Konsultan Kosong',
                ];
            }

            if ($emptyCount > 0 && $nullCount > 0) {
                $issues[] = [
                    'code' => 'ORDER_DETAIL_KONSULTAN_MIXED_NULL_EMPTY',
                    'severity' => 'warning',
                    'title' => 'Konsultan order detail campur NULL dan string kosong',
                    'message' => 'Kondisi ini bisa membuat SalesDailyQSD membaca order yang sama sebagai grup berbeda sebelum digabung ke invoice.',
                    'detail' => [
                        'no_orders' => $noOrders->values(),
                        'konsultan_null' => $nullCount,
                        'konsultan_empty' => $emptyCount,
                    ],
                    'fixable' => true,
                    'action' => 'normalize_order_detail_konsultan',
                    'button_label' => 'Fix Konsultan Kosong',
                ];
            }
        }

        if ($dailyQsdRows->isEmpty()) {
            $issues[] = [
                'code' => 'DAILY_QSD_NOT_FOUND',
                'severity' => 'secondary',
                'title' => 'Data daily_qsd tidak ditemukan',
                'message' => 'Compare invoice membaca dari daily_qsd, tapi invoice ini belum ada di daily_qsd.',
                'fixable' => false,
            ];
        }

        return [
            'has_error' => !empty($issues),
            'issues' => $issues,
            'source' => [
                'compare_table' => 'daily_qsd',
                'compare_controller' => 'CompareInvoiceController::needCompareIndex',
                'qsd_builder' => 'SalesDailyQSD',
            ],
            'invoice_value' => $invoiceValue,
            'payment_check' => [
                'total_pembayaran_real' => $totalPembayaranReal,
                'nilai_pelunasan_invoice' => $nilaiPelunasanInvoice,
                'difference' => $nilaiPelunasanDifference,
            ],
            'small_invoice_uploads' => $smallInvoiceUploads,
            'daily_qsd_rows' => $dailyQsdRows->values(),
            'order_detail_konsultan' => $orderDetailStats,
        ];
    }
    public function checkSmallInvoiceSignature(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $totalTagihan = $this->getInvoiceTotalTagihan($noInvoice, $invoiceRows);
            $firstInvoice = $invoiceRows->first();

            return response()->json([
                'message' => 'Data invoice ditemukan.',
                'data' => [
                    'invoice' => $firstInvoice,
                    'total_tagihan' => $totalTagihan,
                    'is_below_threshold' => $totalTagihan < 5000000,
                    'can_use_fixing' => $totalTagihan < 5000000,
                    'filename' => $firstInvoice->filename,
                    'upload_file' => $firstInvoice->upload_file,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function generateQrInvoice(Request $request)
    {
        $noInvoice = trim((string) $request->input('no_invoice'));
        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $invoice = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (!$invoice) {
                DB::rollBack();
                return response()->json([
                    'message' => "Invoice {$noInvoice} tidak ditemukan atau tidak aktif.",
                ], 404);
            }

            $filename = str_replace('/', '_', $noInvoice);
            $directory = public_path('qr_documents');
            $path = $directory . '/' . $filename . '.svg';
            $link = 'https://www.intilab.com/validation/';

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $qr = QrDocument::where('type_document', 'invoice')
                ->where('file', $filename)
                ->first();

            if ($qr && file_exists($path)) {
                DB::rollBack();
                return response()->json([
                    'message' => "QR Invoice {$noInvoice} sudah ada pada server.",
                    'data' => [
                        'no_invoice' => $noInvoice,
                        'kode_qr' => $qr->kode_qr,
                        'file' => $filename,
                        'path' => $path,
                    ],
                ], 200);
            }

            $kodeQr = $qr->kode_qr ?? ('isldc' . (int) floor(microtime(true) * 1000));
            QrCode::size(200)->generate($link . $kodeQr, $path);

            $dataQr = [
                'type_document' => 'invoice',
                'kode_qr' => $kodeQr,
                'file' => $filename,
                'data' => json_encode([
                    'no_document' => $noInvoice,
                    'nama_customer' => $invoice->nama_perusahaan,
                    'type_document' => 'invoice',
                    'Tanggal_Pengesahan' => Carbon::parse($invoice->tgl_invoice)->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $invoice->nama_pj,
                    'Jabatan' => $invoice->jabatan_pj,
                ]),
                'created_at' => Carbon::now(),
                'created_by' => 'System Fixing',
            ];

            if ($qr) {
                $qr->update($dataQr);
                $status = 'updated';
            } else {
                QrDocument::create($dataQr);
                $status = 'created';
            }

            DB::commit();

            return response()->json([
                'message' => "QR Invoice {$noInvoice} berhasil digenerate.",
                'data' => [
                    'status' => $status,
                    'no_invoice' => $noInvoice,
                    'kode_qr' => $kodeQr,
                    'file' => $filename,
                    'path' => $path,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
    public function renderSmallInvoiceSignature(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $totalTagihan = $this->getInvoiceTotalTagihan($noInvoice, $invoiceRows);
            if ($totalTagihan >= 5000000) {
                return response()->json([
                    'message' => 'Invoice ini sudah Rp 5 juta ke atas. Gunakan flow invoice biasa.',
                ], 422);
            }

            $render = new RenderInvoice();
            $render->renderInvoice($noInvoice, true, true);

            $invoice = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'message' => 'Template invoice dengan area tanda tangan berhasil dirender.',
                'data' => [
                    'filename' => $invoice->filename,
                    'path' => $invoice->filename ? 'invoice/' . $invoice->filename : null,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function uploadSmallInvoiceSignature(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        $file = $request->file('file_input');
        if (!$file || strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            return response()->json([
                'message' => 'File tidak valid. Harus PDF.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $totalTagihan = $this->getInvoiceTotalTagihan($noInvoice, $invoiceRows);
            if ($totalTagihan >= 5000000) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invoice ini sudah Rp 5 juta ke atas. Gunakan flow invoice biasa.',
                ], 422);
            }

            $fileName = 'INVOICE_' . preg_replace('/\\//', '_', $noInvoice) . '.pdf';
            $folder = public_path('invoice-upload');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            $file->move($folder, $fileName);

            Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->update([
                    'upload_file' => $fileName,
                ]);

            DB::commit();

            $render = new RenderInvoice();
            $render->renderInvoice($noInvoice);

            $invoice = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'message' => 'PDF tanda tangan berhasil diupload dan invoice final berhasil dirender.',
                'data' => [
                    'filename' => $invoice->filename,
                    'upload_file' => $invoice->upload_file,
                    'path' => $invoice->filename ? 'invoice/' . $invoice->filename : null,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function getInvoiceTotalTagihan(string $noInvoice, $invoiceRows): float
    {
        $summary = SummaryInvoice::where('no_invoice', $noInvoice)->first();
        $totalTagihan = (float) ($summary->total_tagihan ?? 0);

        if ($totalTagihan <= 0) {
            $totalTagihan = (float) $invoiceRows->sum(function ($item) {
                return (float) ($item->nilai_tagihan ?? $item->total_tagihan ?? 0);
            });
        }

        return $totalTagihan;
    }
    public function renderInvoice(Request $request)
    {
        if ($request->is_copy && $request->is_copy != 'false') {
            foreach ($request->invoice_numbers as $item) {
                $render = new RenderInvoiceTitik();
                $render->renderInvoice($item);
            }
        } else {
            foreach ($request->invoice_numbers as $item) {
                $render = new RenderInvoice();
                $render->renderInvoice($item);
            }
        }

        return response()->json(['message' => 'Invoice has been rendered successfully'], 200);
    }
    public function renderInvoiceByDate(Request $request)
    {
        $tanggal = trim((string) $request->input('tanggal'));
        $dryRun = filter_var($request->input('dry_run', true), FILTER_VALIDATE_BOOLEAN);
        $isCopy = filter_var($request->input('is_copy', false), FILTER_VALIDATE_BOOLEAN);

        if ($tanggal === '') {
            return response()->json([
                'message' => 'Tanggal invoice wajib diisi.',
            ], 422);
        }

        try {
            $date = Carbon::parse($tanggal)->format('Y-m-d');
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Format tanggal invoice tidak valid.',
            ], 422);
        }

        $invoiceRows = Invoice::select([
                'no_invoice',
                'nama_perusahaan',
                'no_order',
                'no_quotation',
                'tgl_invoice',
                'filename',
                'is_custom',
                'is_generate',
            ])
            ->whereDate('tgl_invoice', $date)
            ->where('is_active', true)
            ->whereNotNull('no_invoice')
            ->where('no_invoice', '!=', '')
            ->orderBy('no_invoice')
            ->get()
            ->unique('no_invoice')
            ->values();

        $summary = [
            'tanggal' => $date,
            'dry_run' => $dryRun,
            'is_copy' => $isCopy,
            'total_invoice' => $invoiceRows->count(),
            'rendered' => 0,
            'failed' => 0,
        ];

        if ($dryRun) {
            return response()->json([
                'message' => 'Preview render ulang invoice per hari selesai.',
                'data' => [
                    'summary' => $summary,
                    'invoices' => $invoiceRows,
                ],
            ], 200);
        }

        $rendered = [];
        $failed = [];

        foreach ($invoiceRows as $invoice) {
            try {
                $render = $isCopy ? new RenderInvoiceTitik() : new RenderInvoice();
                $render->renderInvoice($invoice->no_invoice);

                $rendered[] = [
                    'no_invoice' => $invoice->no_invoice,
                    'nama_perusahaan' => $invoice->nama_perusahaan,
                ];
                $summary['rendered']++;
            } catch (\Throwable $th) {
                $failed[] = [
                    'no_invoice' => $invoice->no_invoice,
                    'nama_perusahaan' => $invoice->nama_perusahaan,
                    'message' => $th->getMessage(),
                ];
                $summary['failed']++;
            }
        }

        return response()->json([
            'message' => $summary['failed'] > 0
                ? 'Render ulang invoice per hari selesai dengan sebagian gagal.'
                : 'Render ulang invoice per hari berhasil.',
            'data' => [
                'summary' => $summary,
                'rendered' => $rendered,
                'failed' => $failed,
            ],
        ], $summary['failed'] > 0 ? 207 : 200);
    }

    public function fixInvoiceNumber(Request $request)
    {

        $oldInvoiceNumber = trim($request->no_invoice);
        $targetYear = (string) $request->tahun;
        $lockName = 'invoice-fix-' . $targetYear;
        $lockAcquired = false;
        $transactionStarted = false;

        try {
            $lockResult = DB::select('SELECT GET_LOCK(?, 30) AS acquired', [$lockName]);
            $lockAcquired = isset($lockResult[0]) && (int) $lockResult[0]->acquired === 1;

            if (!$lockAcquired) {
                return response()->json([
                    'message' => 'Gagal mendapatkan lock untuk generate nomor invoice.',
                ], 423);
            }

            $invoice = Invoice::where('no_invoice', $oldInvoiceNumber)
                ->where('is_active', true)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $newInvoiceNumber = $this->generateFixedInvoiceNumber($invoice, $targetYear);

            if ($newInvoiceNumber === $oldInvoiceNumber) {
                return response()->json([
                    'message' => 'Nomor invoice sudah sesuai dengan tahun yang dipilih.',
                    'data' => [
                        'old_invoice' => $oldInvoiceNumber,
                        'new_invoice' => $newInvoiceNumber,
                    ],
                ], 200);
            }

            $exists = Invoice::where('no_invoice', $newInvoiceNumber)
                ->where('id', '!=', $invoice->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => "Nomor invoice {$newInvoiceNumber} sudah digunakan.",
                ], 422);
            }

            DB::beginTransaction();
            $transactionStarted = true;

            $updatedTables = [];
            $updatedTables['invoice'] = Invoice::where('id', $invoice->id)->update([
                'no_invoice' => $newInvoiceNumber,
                'tgl_invoice' => $this->replaceYear($invoice->tgl_invoice, $targetYear),
                'filename' => null,
                'is_generate' => 0,
            ]);

            foreach ($this->invoiceReferenceTables() as $table) {
                $updated = $this->updateInvoiceReference($table, 'no_invoice', $oldInvoiceNumber, $newInvoiceNumber);
                if ($updated > 0) {
                    $updatedTables[$table] = $updated;
                }
            }

            $this->updateInvoiceQrDocument($oldInvoiceNumber, $newInvoiceNumber, $invoice, $targetYear);

            DB::commit();
            $transactionStarted = false;

            $render = new RenderInvoice();
            $render->renderInvoice($newInvoiceNumber);

            return response()->json([
                'message' => "Nomor invoice berhasil diubah menjadi {$newInvoiceNumber} dan invoice sudah dirender ulang.",
                'data' => [
                    'old_invoice' => $oldInvoiceNumber,
                    'new_invoice' => $newInvoiceNumber,
                    'updated_tables' => $updatedTables,
                ],
            ], 200);
        } catch (\Throwable $th) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        } finally {
            if ($lockAcquired) {
                DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    private function generateFixedInvoiceNumber(Invoice $invoice, string $targetYear): string
    {
        $rekening = $invoice->rekening == '4976688988' ? 'ppn' : 'non-ppn';
        $shortYear = substr($targetYear, -2);

        $lastInvoice = Invoice::where('rekening', $invoice->rekening)
            ->where('id', '!=', $invoice->id)
            ->whereYear('tgl_invoice', $targetYear)
            ->where('no_invoice', 'like', '%' . $shortYear . '%')
            ->orderBy('no_invoice', 'desc')
            ->value('no_invoice');

        $prefix = $rekening == 'ppn' ? 'INV' : 'IV';
        $defaultNo = $targetYear == '2024'
            ? ($rekening == 'ppn' ? '06767' : '00531')
            : '00001';

        $no = $lastInvoice ? str_pad(intval(substr($lastInvoice, -5)) + 1, 5, '0', STR_PAD_LEFT) : $defaultNo;

        return "ISL/{$prefix}/{$shortYear}{$no}";
    }

    private function replaceYear($date, string $targetYear): string
    {
        $invoiceDate = $date ? Carbon::parse($date) : Carbon::now();
        $day = min($invoiceDate->day, Carbon::create((int) $targetYear, $invoiceDate->month, 1)->daysInMonth);

        return $invoiceDate
            ->setYear((int) $targetYear)
            ->setDay($day)
            ->format('Y-m-d H:i:s');
    }

    private function invoiceReferenceTables(): array
    {
        return [
            'record_pembayaran_invoice',
            'sales_in_detail',
            'withdraw',
            'summary_invoice',
            'billing_list_detail',
            'distribusi_invoice_detail',
            'claim_fee_external',
            'claim_fee_external_expense',
            'claim_fee_external_tax',
        ];
    }

    private function updateInvoiceReference(string $table, string $column, string $oldInvoiceNumber, string $newInvoiceNumber): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->where($column, $oldInvoiceNumber)
            ->update([$column => $newInvoiceNumber]);
    }

    private function updateInvoiceQrDocument(string $oldInvoiceNumber, string $newInvoiceNumber, Invoice $invoice, string $targetYear): void
    {
        if (!Schema::hasTable('qr_documents')) {
            return;
        }

        $oldFile = str_replace('/', '_', $oldInvoiceNumber);
        $newFile = str_replace('/', '_', $newInvoiceNumber);
        $qr = DB::table('qr_documents')
            ->where('type_document', 'invoice')
            ->where('file', $oldFile)
            ->first();

        if (!$qr) {
            return;
        }

        $data = json_decode($qr->data, true) ?: [];
        $data['no_document'] = $newInvoiceNumber;
        $data['Tanggal_Pengesahan'] = Carbon::parse($this->replaceYear($invoice->tgl_invoice, $targetYear))
            ->locale('id')
            ->isoFormat('DD MMMM YYYY');

        DB::table('qr_documents')
            ->where('id', $qr->id)
            ->update([
                'file' => $newFile,
                'data' => json_encode($data),
            ]);

        $oldPath = public_path('qr_documents/' . $oldFile . '.svg');
        $newPath = public_path('qr_documents/' . $newFile . '.svg');

        if (file_exists($oldPath) && !file_exists($newPath)) {
            @rename($oldPath, $newPath);
        }
    }

    public function cekLhpDouble(Request $request)
    {
        $noOrder = $request->input('no_order');
        if (!$noOrder) {
            return response()->json(['message' => 'no_order is required'], 400);
        }

        $link = LinkLhp::where('no_order', $noOrder)->get();
        if ($link->isEmpty()) {
            return response()->json(['message' => 'Link Lhp not found'], 404);
        }

        $models = [
            LhpsAdverseOdorHeader::class,
            LhpsAirHeader::class,
            LhpsEmisiCHeader::class,
            LhpsEmisiHeader::class,
            LhpsEmisiIsokinetikHeader::class,
            LhpsErgonomiHeader::class,
            LhpsGetaranHeader::class,
            LhpsHygieneSanitasiHeader::class,
            LhpsIklimHeader::class,
            LhpsKebisinganHeader::class,
            LhpsKebisinganPersonalHeader::class,
            LhpsLingHeader::class,
            LhpsMedanLMHeader::class,
            LhpsMicrobiologiHeader::class,
            LhpsPadatanHeader::class,
            LhpsPencahayaanHeader::class,
            LhpsSinarUVHeader::class,
            LhpsSwabTesHeader::class,
            LhpUdaraPsikologiHeader::class,
        ];

        $duplicates = [];

        foreach ($models as $modelClass) {
            if (!class_exists($modelClass)) continue;

            $table = (new $modelClass)->getTable();
            $hasLhp = \Illuminate\Support\Facades\Schema::hasColumn($table, 'no_lhp');
            $hasCfr = \Illuminate\Support\Facades\Schema::hasColumn($table, 'no_cfr');
            $hasIsApprove = \Illuminate\Support\Facades\Schema::hasColumn($table, 'is_approve');
            $hasIsApproved = \Illuminate\Support\Facades\Schema::hasColumn($table, 'is_approved');

            $approveColumn = $hasIsApprove ? 'is_approve' : ($hasIsApproved ? 'is_approved' : null);

            // Step 1: Find no_lhp that appear more than once for this no_order
            if ($hasLhp) {
                $dupNos = DB::table($table)
                    ->select('no_lhp')
                    ->where('no_order', $noOrder)
                    ->whereNotNull('no_lhp')
                    ->where('no_lhp', '!=', '')
                    ->groupBy('no_lhp')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('no_lhp');

                if ($dupNos->isNotEmpty()) {
                    $selectColumns = ['id', 'no_lhp', 'no_order'];
                    if ($approveColumn) {
                        $selectColumns[] = $approveColumn;
                    }
                    $records = $modelClass::where('no_order', $noOrder)
                        ->whereIn('no_lhp', $dupNos)
                        ->get($selectColumns);

                    foreach ($records as $record) {
                        $duplicates[] = [
                            'id' => $record->id,
                            'no_lhp' => $record->no_lhp,
                            'no_order' => $record->no_order,
                            'is_approve' => $approveColumn ? $record->{$approveColumn} : null,
                            'model' => class_basename($modelClass)
                        ];
                    }
                }
            }

            // Step 2: Find no_cfr that appear more than once for this no_order
            if ($hasCfr) {
                $dupCfrs = DB::table($table)
                    ->select('no_cfr')
                    ->where('no_order', $noOrder)
                    ->whereNotNull('no_cfr')
                    ->where('no_cfr', '!=', '')
                    ->groupBy('no_cfr')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('no_cfr');

                if ($dupCfrs->isNotEmpty()) {
                    $selectColumns = ['id', 'no_cfr', 'no_order'];
                    if ($hasLhp) {
                        $selectColumns[] = 'no_lhp';
                    }
                    if ($approveColumn) {
                        $selectColumns[] = $approveColumn;
                    }
                    $recordsCfr = $modelClass::where('no_order', $noOrder)
                        ->whereIn('no_cfr', $dupCfrs)
                        ->get($selectColumns);

                    foreach ($recordsCfr as $record) {
                        // Avoid adding duplicates if already added by no_lhp check
                        $alreadyAdded = false;
                        foreach ($duplicates as $dup) {
                            if ($dup['id'] == $record->id && $dup['model'] == class_basename($modelClass)) {
                                $alreadyAdded = true;
                                break;
                            }
                        }

                        if (!$alreadyAdded) {
                            $duplicates[] = [
                                'id' => $record->id,
                                'no_lhp' => ($hasLhp && !empty($record->no_lhp)) ? $record->no_lhp : $record->no_cfr,
                                'no_order' => $record->no_order,
                                'is_approve' => $approveColumn ? $record->{$approveColumn} : null,
                                'model' => class_basename($modelClass)
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'data' => $duplicates,
            'links' => $link
        ], 200);
    }

    public function hapusLhpDouble(Request $request)
    {
        $id = $request->input('id');
        $modelName = $request->input('model');

        if (!$id || !$modelName) {
            return response()->json(['message' => 'id and model are required'], 400);
        }

        $modelClass = "\\App\\Models\\" . $modelName;
        if (!class_exists($modelClass)) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        $record = $modelClass::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        try {
            DB::beginTransaction();

            $detailModelName = str_replace('Header', 'Detail', $modelName);
            $detailModelClass = "\\App\\Models\\" . $detailModelName;

            if (class_exists($detailModelClass)) {
                $detailModelClass::where('id_header', $id)->delete();
            }

            $record->delete();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }

    public function runCheckOrderActive(Request $request)
    {
        try {
            $options = [];
            if ($request->has('start_date') && !empty($request->start_date)) {
                $options['--start_date'] = $request->start_date;
            }
            if ($request->has('end_date') && !empty($request->end_date)) {
                $options['--end_date'] = $request->end_date;
            }

            \Illuminate\Support\Facades\Artisan::call('checkorder', $options);
            return response()->json([
                'success' => true,
                'message' => 'Command checkorder berhasil dijalankan.',
                'output' => \Illuminate\Support\Facades\Artisan::output()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }


    private function hasSuccessfulAiMatching($recruitment): bool
    {
        if (!$recruitment) {
            return false;
        }

        if (!empty($recruitment->ai_matching_response)) {
            $parsed = json_decode($recruitment->ai_matching_response, true);
            if (is_array($parsed) && array_key_exists('score', $parsed)) {
                return true;
            }
        }

        $score = $recruitment->matching_score ?? $recruitment->nilai_kecocokan ?? null;
        if ($score === null || $score === '') {
            return false;
        }

        return (float) $score > 0;
    }

    private function logAiMatching(string $level, string $message, array $context = []): void
    {
        Log::channel('ats_ai_matching')->{$level}($message, $context);
    }

    private function candidateSkillsList($recruitment): array
    {
        $skills = [];

        if (!empty($recruitment->skill)) {
            foreach (json_decode($recruitment->skill, true) ?: [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $keahlian = trim((string) ($item['keahlian'] ?? $item['skill'] ?? ''));
                if ($keahlian === '') {
                    continue;
                }
                $skills[] = [
                    'keahlian' => $keahlian,
                    'rate' => isset($item['rate']) && $item['rate'] !== '' ? (int) $item['rate'] : null,
                ];
            }
        } elseif (!empty($recruitment->keahlian)) {
            foreach (json_decode($recruitment->keahlian, true) ?: [] as $item) {
                if (!is_array($item) || empty($item['keahlianSkill'])) {
                    continue;
                }
                $skills[] = [
                    'keahlian' => trim((string) $item['keahlianSkill']),
                    'rate' => null,
                ];
            }
        }

        return $skills;
    }

    private function summarizeAiMatchingPayload(array $payload): array
    {
        $prompt = (string) ($payload['prompt'] ?? '');

        return [
            'model' => $payload['model'] ?? null,
            'stream' => $payload['stream'] ?? null,
            'format' => $payload['format'] ?? null,
            'prompt_length' => strlen($prompt),
        ];
    }

    private function sendToOllamaAi(array $payload)
    {
        $endpoint = rtrim((string) env('ATS_AI_GENERATE_URL', 'http://10.88.209.240:11434/api/generate'), '/');

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $this->logAiMatching('error', 'sendToOllamaAi cURL error', [
                    'endpoint' => $endpoint,
                    'error' => $curlError,
                    'payload' => $this->summarizeAiMatchingPayload($payload),
                ]);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }

            $this->logAiMatching('warning', 'sendToOllamaAi HTTP error', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response,
                'payload' => $this->summarizeAiMatchingPayload($payload),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logAiMatching('error', 'sendToOllamaAi exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'payload' => $this->summarizeAiMatchingPayload($payload),
            ]);
            return null;
        }
    }

    private function candidateSkillsForAi($recruitment): string
    {
        $skillsList = collect($this->candidateSkillsList($recruitment))
            ->map(function ($skill) {
                if ($skill['rate'] !== null) {
                    return sprintf('%s (tingkat %d/10)', $skill['keahlian'], $skill['rate']);
                }

                return $skill['keahlian'];
            })
            ->filter()
            ->values()
            ->all();

        return !empty($skillsList)
            ? implode(', ', $skillsList)
            : 'Skill tidak spesifik';
    }

    public function getNewRecruitmentList(Request $request)
    {
        $data = NewRecruitment::where('is_active', 1)
            ->select('id', 'nama_lengkap')
            ->get();
        return response()->json([
            'data' => $data,
        ], 200);
    }

    public function getAiPayload(Request $request)
    {
        $recruitmentId = $request->input('recruitment_id') ?? $request->input('id');
        if (!$recruitmentId) {
            return response()->json(['message' => 'ID recruitment tidak ditemukan'], 400);
        }

        $attempt = DB::table('assessment_attempts')->where('recruitment_id', $recruitmentId)->orderBy('id', 'desc')->first();
        if (!$attempt) {
            return response()->json(['message' => 'Assessment attempt tidak ditemukan untuk kandidat ini'], 404);
        }

        $payloadData = $this->processAiMatching($attempt->id, $recruitmentId);
        if (!$payloadData) {
            return response()->json(['message' => 'Gagal memproses data AI payload'], 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => $payloadData
        ], 200);
    }

    private function summarizeAiMatchingResponse(?array $aiResult): ?array
    {
        if (!$aiResult) {
            return null;
        }

        $parsedResponse = null;
        if (!empty($aiResult['response'])) {
            $decoded = json_decode($aiResult['response'], true);
            $parsedResponse = is_array($decoded) ? $decoded : $aiResult['response'];
        }

        return [
            'model' => $aiResult['model'] ?? null,
            'created_at' => $aiResult['created_at'] ?? null,
            'done' => $aiResult['done'] ?? null,
            'done_reason' => $aiResult['done_reason'] ?? null,
            'response' => $parsedResponse,
            'prompt_eval_count' => $aiResult['prompt_eval_count'] ?? null,
            'eval_count' => $aiResult['eval_count'] ?? null,
            'total_duration' => $aiResult['total_duration'] ?? null,
        ];
    }

    public function processAiMatching($attemptId, $recruitmentId)
    {
        try {
            $recruitment = DB::table('new_recruitment')->where('id', $recruitmentId)->first();
            if (!$recruitment) {
                return null;
            }

            if ($this->hasSuccessfulAiMatching($recruitment)) {
                $this->logAiMatching('info', 'AI matching skipped (already processed)', [
                    'recruitment_id' => $recruitmentId,
                    'attempt_id' => $attemptId,
                ]);

                if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_data')
                    && !empty($recruitment->ai_matching_data)) {
                    return json_decode($recruitment->ai_matching_data, true);
                }

                return null;
            }

            // 1. Data Personal Request (Posisi Target & Kebutuhan)
            $personnelRequest = DB::table('personnel_requests')
                ->leftJoin('master_jabatan', 'master_jabatan.id', '=', 'personnel_requests.posisi')
                ->leftJoin('master_divisi', 'master_divisi.id', '=', 'personnel_requests.divisi')
                ->leftJoin('master_cabang', 'master_cabang.id', '=', 'personnel_requests.lokasi_penempatan_cabang')
                ->where('personnel_requests.id', $recruitment->personnel_request_id)
                ->select(
                    'personnel_requests.*',
                    'master_jabatan.nama_jabatan as nama_posisi',
                    'master_divisi.nama_divisi as nama_divisi',
                    'master_cabang.nama_cabang as nama_cabang'
                )
                ->first();

            // 2. Data Diri Pelamar / Candidate
            $candidateEducations = DB::table('candidate_educations')
                ->where('new_recruitment_id', $recruitmentId)
                ->get();

            $candidateWorkExps = DB::table('candidate_work_experiences')
                ->where('new_recruitment_id', $recruitmentId)
                ->get();

            // 3. Hasil Assessment (Seluruh Test)
            $sessions = DB::table('assessment_sessions')
                ->where('assessment_attempt_id', $attemptId)
                ->get();

            // Format Riwayat Pendidikan Kandidat
            $eduList = [];
            if ($candidateEducations->isNotEmpty()) {
                foreach ($candidateEducations as $edu) {
                    $eduList[] = implode(' ', array_filter([$edu->jenjang_pendidikan, $edu->jurusan, $edu->nama_institusi]));
                }
            } elseif (!empty($recruitment->pendidikan)) {
                $rawEdu = json_decode($recruitment->pendidikan, true) ?: [];
                foreach ($rawEdu as $e) {
                    if (is_array($e)) {
                        $eduList[] = implode(' ', array_filter([$e['jenjang'] ?? '', $e['jurusan'] ?? '', $e['institusi'] ?? '']));
                    }
                }
            }
            $candidateEduStr = implode('; ', array_filter($eduList)) ?: ($recruitment->pendidikan_terakhir ?? 'Pendidikan tidak diisi');

            // Format Pengalaman Kerja Kandidat
            $expList = [];
            if ($candidateWorkExps->isNotEmpty()) {
                foreach ($candidateWorkExps as $exp) {
                    $expList[] = implode(' ', array_filter([$exp->posisi_terakhir, 'di', $exp->nama_perusahaan]));
                }
            } elseif (!empty($recruitment->pengalaman_kerja)) {
                $rawExp = json_decode($recruitment->pengalaman_kerja, true) ?: [];
                foreach ($rawExp as $x) {
                    if (is_array($x)) {
                        $expList[] = implode(' ', array_filter([$x['posisi_kerja'] ?? '', 'di', $x['nama_perusahaan'] ?? '']));
                    }
                }
            }
            $candidateExpStr = implode('; ', array_filter($expList)) ?: 'Belum ada pengalaman kerja';

            // Format Skill & Keahlian Kandidat (kolom skill: [{"rate": 8, "keahlian": "JavaScript"}, ...])
            $candidateSkillsStr = $this->candidateSkillsForAi($recruitment);

            // Format Hasil Seluruh Test Assessment (Original JSON untuk DISC & PAPI Kostick)
            $structuredSessions = [];
            $assessmentSummary = [];
            foreach ($sessions as $session) {
                $res = json_decode($session->result_json, true) ?: [];
                $answers = json_decode($session->answers_json, true) ?: [];

                $structuredSessions[] = [
                    'session_id' => $session->id,
                    'category_name' => $session->category_name,
                    'session_order' => $session->session_order,
                    'status' => $session->status,
                    'answers' => $answers,
                    'result_json' => $res, // Original JSON result
                ];

                if (isset($res['engine']) && $res['engine'] === 'disc') {
                    $assessmentSummary[] = 'Hasil DISC (Original JSON): ' . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } elseif (isset($res['engine']) && $res['engine'] === 'papi_kostick') {
                    $assessmentSummary[] = 'Hasil PAPI Kostick (Original JSON): ' . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } elseif (isset($res['score'])) {
                    $assessmentSummary[] = $session->category_name . ': ' . $res['score'] . '/100';
                }
            }
            $testSummaryStr = implode(' | ', $assessmentSummary) ?: 'Assessment Selesai';

            // Clean HTML tags from requirement fields for plain text prompt
            $cleanEduReq = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($personnelRequest->pendidikan ?? '')))) ?: 'Kualifikasi pendidikan tidak ditentukan';
            $cleanExpReq = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($personnelRequest->pengalaman_kerja ?? '')))) ?: 'Pengalaman tidak ditentukan';
            $cleanSkillsReq = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($personnelRequest->skill_wajib ?? '')))) ?: 'Skill wajib tidak ditentukan';
            $posisiReq = $personnelRequest->nama_posisi ?? ($personnelRequest->posisi ?? 'Jabatan tidak ditentukan');
            $minScoreReq = $personnelRequest->minimum_matching ?? 75;

            // Formulate Prompt String for AI Ollama Server
            $promptText = sprintf(
                "Analisis kecocokan kandidat terhadap posisi yang dilamar.\n\nKandidat:\n- Pendidikan: %s\n- Pengalaman: %s\n- Skill: %s\n- Hasil assessment: %s\n\nPosisi: %s\nKebutuhan pendidikan: %s\nKebutuhan pengalaman: %s\nKebutuhan skill: %s\n\nBerikan skor kecocokan 0-100 (integer) dan alasan singkat dalam Bahasa Indonesia.",
                $candidateEduStr,
                $candidateExpStr,
                $candidateSkillsStr,
                $testSummaryStr,
                $posisiReq,
                $cleanEduReq,
                $cleanExpReq,
                $cleanSkillsReq,
                $minScoreReq
            );

            // Construct payload for Ollama structured JSON response
            $aiPayload = [
                'model' => 'intilab-ats',
                'prompt' => $promptText,
                'stream' => false,
                'format' => [
                    'type' => 'object',
                    'properties' => [
                        'score' => [
                            'type' => 'integer',
                        ],
                        'reason' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => [
                        'score',
                        'reason',
                    ],
                    'additionalProperties' => false,
                ],
            ];

            // Full Structured Data Object with Original JSON
            $structuredData = [
                'personnel_request' => $personnelRequest,
                'candidate_profile' => [
                    'id' => $recruitment->id,
                    'nama_lengkap' => $recruitment->nama_lengkap,
                    'email' => $recruitment->email,
                    'no_telepon' => $recruitment->no_telepon,
                    'pendidikan' => $candidateEduStr,
                    'pengalaman_kerja' => $candidateExpStr,
                    'skill' => $candidateSkillsStr,
                    'skills' => $this->candidateSkillsList($recruitment),
                ],
                'assessment_results' => $structuredSessions,
                'ai_payload' => $aiPayload,
            ];

            // Log AI request (prompt once, metadata only in payload summary)
            $this->logAiMatching('info', 'AI matching request', [
                'recruitment_id' => $recruitmentId,
                'attempt_id' => $attemptId,
                'kandidat' => $recruitment->nama_lengkap ?? '',
                'model' => $aiPayload['model'] ?? null,
                'prompt_length' => strlen($promptText),
                'prompt' => $promptText,
            ]);

            // Send to Ollama AI Server via HTTP POST cURL
            $aiResult = $this->sendToOllamaAi($aiPayload);

            // Log AI response (strip verbose token context from Ollama)
            $this->logAiMatching('info', 'AI matching response', [
                'recruitment_id' => $recruitmentId,
                'attempt_id' => $attemptId,
                'kandidat' => $recruitment->nama_lengkap ?? '',
                'ai_response' => $this->summarizeAiMatchingResponse($aiResult),
            ]);

            if ($aiResult && !empty($aiResult['response'])) {
                $parsedResponse = json_decode($aiResult['response'], true);
                $matchingScore = null;
                $matchingReason = null;
                $responseStr = $aiResult['response'];

                if (is_array($parsedResponse)) {
                    if (isset($parsedResponse['score'])) {
                        $matchingScore = max(0, min(100, (int) $parsedResponse['score']));
                    }
                    if (isset($parsedResponse['reason'])) {
                        $matchingReason = trim((string) $parsedResponse['reason']);
                    }
                    if ($matchingScore !== null || $matchingReason !== null) {
                        $responseStr = json_encode([
                            'score' => $matchingScore,
                            'reason' => $matchingReason ?? '',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                } elseif (preg_match('/(\d{1,3})\s*%?/', $aiResult['response'], $matches)) {
                    $scoreVal = (int) $matches[1];
                    if ($scoreVal <= 100) {
                        $matchingScore = $scoreVal;
                    }
                }

                $updateData = [
                    'updated_at' => Carbon::now(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_response')) {
                    $updateData['ai_matching_response'] = $responseStr;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_data')) {
                    $updateData['ai_matching_data'] = json_encode($structuredData);
                }
                if ($matchingReason !== null && $matchingReason !== ''
                    && \Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_reason')) {
                    $updateData['ai_matching_reason'] = $matchingReason;
                }
                if ($matchingScore !== null) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'matching_score')) {
                        $updateData['matching_score'] = $matchingScore;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'nilai_kecocokan')) {
                        $updateData['nilai_kecocokan'] = $matchingScore;
                    }
                }

                DB::table('new_recruitment')->where('id', $recruitmentId)->update($updateData);
                $structuredData['ai_response'] = $aiResult;
            }

            return $structuredData;
        } catch (\Throwable $e) {
            $this->logAiMatching('error', 'processAiMatching error', [
                'recruitment_id' => $recruitmentId,
                'attempt_id' => $attemptId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function deactiveOrder(Request $request)
    {
        $no_order = $request->no_order;
        $deactive_type = $request->deactive_type;

        if ($no_order == null || $no_order == '') {
            return response()->json([
                'message' => 'Nomor order tidak boleh kosong.',
            ], 400);
        }

        if ($deactive_type == null || $deactive_type == '') {
            return response()->json([
                'message' => 'Tipe deactive tidak boleh kosong.',
            ], 400);
        }

        try {
            $hasReceivedSample = OrderDetail::where('no_order', $no_order)
                ->whereNotNull('tanggal_terima')
                ->exists();

            if ($hasReceivedSample) {
                return response()->json([
                    'message' => 'Order tidak dapat di-deactive karena telah dilakukan sampling.',
                ], 422);
            }

            // Ambil daftar no_sampel dan no_quotation terkait order ini untuk relasi tabel turunan
            $sampleNumbers = OrderDetail::where('no_order', $no_order)
                ->pluck('no_sampel')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $quotationNumbers = OrderDetail::where('no_order', $no_order)
                ->pluck('no_quotation')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $orderHeader = OrderHeader::where('no_order', $no_order)->first();
            if ($orderHeader) {
                if (!empty($orderHeader->no_quotation)) {
                    $quotationNumbers[] = $orderHeader->no_quotation;
                }
                if (!empty($orderHeader->no_document)) {
                    $quotationNumbers[] = $orderHeader->no_document;
                }
            }
            $quotationNumbers = array_values(array_unique(array_filter($quotationNumbers)));

            DB::beginTransaction();

            // 2. Deactive order_header (jika deactive_type adalah 'all')
            if ($deactive_type === 'all') {
                OrderHeader::where('no_order', $no_order)->update(['is_active' => false]);
            }

            // 3. Deactive order_detail
            OrderDetail::where('no_order', $no_order)->update(['is_active' => false]);

            // 4. Deactive t_ftc
            if (!empty($sampleNumbers) && Schema::hasColumn('t_ftc', 'no_sample')) {
                DB::table('t_ftc')->whereIn('no_sample', $sampleNumbers)->update(['is_active' => false]);
            }

            // 5. Deactive t_ftc_t
            if (!empty($sampleNumbers) && Schema::hasColumn('t_ftc_t', 'no_sample')) {
                DB::table('t_ftc_t')->whereIn('no_sample', $sampleNumbers)->update(['is_active' => false]);
            }

            $rawDoc = $orderHeader->no_document;

            // Buang akhiran R1, R2, dll (kalo ada)
            $baseDoc = !empty($rawDoc) ? preg_replace('/R\d+$/', '', $rawDoc) : null;

            // 6. Deactive sampling_plan
            if (!empty($baseDoc)) {
                DB::table('sampling_plan')
                    ->where(function ($q) use ($baseDoc) {
                        $q->where('no_quotation', 'LIKE', $baseDoc . '%');
                        if (Schema::hasColumn('sampling_plan', 'no_document')) {
                            $q->orWhere('no_document', 'LIKE', $baseDoc . '%');
                        }
                    })
                    ->update(['is_active' => false]);
            }

            // 7. Deactive jadwal
            if (!empty($baseDoc) && Schema::hasColumn('jadwal', 'no_quotation')) {
                DB::table('jadwal')
                    ->where('no_quotation', 'LIKE', $baseDoc . '%')
                    ->update(['is_active' => false]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order ' . $no_order . ' berhasil di-deactive' . ($deactive_type === 'all' ? ' (Semua data termasuk Header)' : ' (Tanpa Header)') . '.',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil daftar kandidat / karyawan pada Permohonan Penyesuaian Karyawan untuk fixing tools.
     */
    public function getSalaryAdjustmentCandidatesForFixing(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_jabatan as j', 'karyawan.id_jabatan', '=', 'j.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->where('sar.is_active', true);

        if ($status !== '') {
            $query->where('sar.status', $status);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('sar.no_document', 'LIKE', "%{$q}%")
                    ->orWhere('karyawan.nama_lengkap', 'LIKE', "%{$q}%")
                    ->orWhere('karyawan.nik_karyawan', 'LIKE', "%{$q}%");
            });
        }

        $records = $query->select([
                'sar.id',
                'sar.no_document',
                'sar.employee_id',
                'sar.requested_by_id',
                'sar.jabatan',
                'sar.status',
                'sar.request_type',
                'sar.bulan_efektif',
                'sar.created_by',
                'sar.finance_approved_at',
                'sar.final_eval_approved_at',
                'sar.created_at',
                'karyawan.nama_lengkap as karyawan_nama',
                'karyawan.nik_karyawan',
                'd.nama_divisi',
                'j.nama_jabatan',
                'manager.nama_lengkap as manager_nama',
            ])
            ->orderBy('sar.id', 'desc')
            ->limit(50)
            ->get();

        $defaultEmailIbu = trim((string) env('EMAIL_DIREKTUR_IBU', ''));
        $defaultEmailBapak = trim((string) env('EMAIL_DIREKTUR_BAPAK', ''));

        $data = $records->map(function ($row) use ($defaultEmailIbu, $defaultEmailBapak) {
            $recommendedRole = $row->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK
                ? SalaryAdjustmentEmailService::ROLE_BAPAK
                : SalaryAdjustmentEmailService::ROLE_IBU;

            $recommendedEmail = $recommendedRole === SalaryAdjustmentEmailService::ROLE_BAPAK
                ? $defaultEmailBapak
                : $defaultEmailIbu;

            $createdAtFormatted = '-';
            if (!empty($row->created_at)) {
                try {
                    $createdAtFormatted = Carbon::parse($row->created_at)->format('d-m-Y H:i');
                } catch (\Throwable $th) {
                    $createdAtFormatted = (string) $row->created_at;
                }
            }

            return [
                'id' => $row->id,
                'no_document' => $row->no_document,
                'nama_lengkap' => $row->karyawan_nama ?? '-',
                'nik_karyawan' => $row->nik_karyawan ?? '-',
                'jabatan' => $row->jabatan ?: ($row->nama_jabatan ?? '-'),
                'department' => $row->nama_divisi ?? '-',
                'manager_nama' => $row->manager_nama ?? $row->created_by,
                'status' => $row->status,
                'status_label' => SalaryAdjustmentWorkflowService::statusLabel($row->status),
                'request_type' => $row->request_type,
                'request_type_label' => EmployeeAdjustmentTypeRegistry::label($row->request_type),
                'bulan_efektif' => $row->bulan_efektif,
                'recommended_role' => $recommendedRole,
                'recommended_email' => $recommendedEmail,
                'email_ibu' => $defaultEmailIbu,
                'email_bapak' => $defaultEmailBapak,
                'finance_approved_at' => $row->finance_approved_at,
                'final_eval_approved_at' => $row->final_eval_approved_at,
                'created_at' => $createdAtFormatted,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }

    /**
     * Kirim ulang email persetujuan Direktur beserta attachment PDF evaluasi final untuk Permohonan Penyesuaian Karyawan.
     */
    public function reEmailSalaryAdjustmentCandidate(Request $request)
    {
        $id = (int) ($request->input('id') ?? 0);
        if ($id <= 0) {
            return response()->json(['message' => 'id permohonan wajib diisi'], 400);
        }

        $record = SalaryAdjustmentRequest::find($id);
        if (!$record || !$record->is_active) {
            return response()->json(['message' => 'Data permohonan tidak ditemukan atau sudah tidak aktif'], 404);
        }

        $roleInput = strtolower(trim((string) $request->input('role', '')));
        if (in_array($roleInput, [SalaryAdjustmentEmailService::ROLE_IBU, SalaryAdjustmentEmailService::ROLE_BAPAK], true)) {
            $role = $roleInput;
        } else {
            $role = $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK
                ? SalaryAdjustmentEmailService::ROLE_BAPAK
                : SalaryAdjustmentEmailService::ROLE_IBU;
        }

        $targetEmail = trim((string) $request->input('target_email', ''));
        $sender = trim((string) ($request->input('sender') ?: ($this->karyawan ?? 'Programmer Tool')));

        try {
            $emailService = new SalaryAdjustmentEmailService();
            $resolvedEmail = $targetEmail !== '' ? $targetEmail : $emailService->resolveRecipient($role);

            if ($resolvedEmail === '') {
                return response()->json([
                    'message' => 'Alamat email tujuan Direktur belum dikonfigurasi di sistem (.env)',
                ], 422);
            }

            // Kirim ulang email lengkap dengan token approval baru dan attachment PDF ringkasan evaluasi
            $sent = $emailService->sendForRole($record, $role, $sender, [
                'email' => $resolvedEmail,
                'persist_token' => true,
                'throw' => true,
            ]);

            $approverLabel = SalaryAdjustmentEmailService::actorLabel($role);

            return response()->json([
                'status' => 'success',
                'message' => "Email permohonan ({$approverLabel}) beserta lampiran berkas PDF berhasil dikirim ulang ke {$resolvedEmail}.",
                'data' => [
                    'id' => $record->id,
                    'role' => $role,
                    'approver_label' => $approverLabel,
                    'target_email' => $resolvedEmail,
                    'sent_at' => Carbon::now()->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengirim ulang email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil daftar kandidat rekrutmen / ATS untuk fixing tools.
     */
    public function getRecruitmentCandidatesForFixing(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $query = NewRecruitment::where('is_active', 1);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nama_lengkap', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('posisi_dilamar', 'LIKE', "%{$q}%");
            });
        }

        $records = $query->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'nama_lengkap', 'email', 'posisi_dilamar', 'status', 'created_at']);

        return response()->json([
            'status' => 'success',
            'data' => $records,
        ], 200);
    }

    /**
     * Mengambil daftar kandidat untuk tools Re-Email Persetujuan Offering Salary.
     */
    public function getOfferingSalaryCandidatesForFixing(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $query = NewRecruitment::with([
            'sallaryOffer',
            'candidateDataOffer',
            'personalRequest.masterJabatan',
            'personnelRequest.masterJabatan',
        ])->where('is_active', 1);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nama_lengkap', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('posisi_dilamar', 'LIKE', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhereHas('personalRequest.masterJabatan', function ($mj) use ($q) {
                        $mj->where('nama_jabatan', 'LIKE', "%{$q}%");
                    })
                    ->orWhereHas('personnelRequest.masterJabatan', function ($mj) use ($q) {
                        $mj->where('nama_jabatan', 'LIKE', "%{$q}%");
                    })
                    ->orWhereHas('personalRequest', function ($pr) use ($q) {
                        $pr->where('no_request', 'LIKE', "%{$q}%");
                    })
                    ->orWhereHas('personnelRequest', function ($pr) use ($q) {
                        $pr->where('no_request', 'LIKE', "%{$q}%");
                    });
            });
        } else {
            $query->where(function ($w) {
                $w->whereHas('sallaryOffer')
                    ->orWhereHas('candidateDataOffer')
                    ->orWhereIn('status', [
                        'internal_sallary_offer',
                        'salary_offer',
                        'candidate_offering',
                        'offering_salary',
                        'management_decision',
                        'waiting_approval_salary',
                        'approval_salary',
                    ]);
            });
        }

        $records = $query->orderBy('id', 'desc')
            ->limit(40)
            ->get();

        $defaultDirectorEmail = trim((string) env('EMAIL_DIREKTUR_BAPAK', ''));

        $data = $records->map(function ($row) use ($defaultDirectorEmail) {
            $activeOffer = $row->sallaryOffer;
            $cdo = $row->candidateDataOffer;

            $gp = null;
            if ($activeOffer && !empty($activeOffer->sallary_offer_hrd)) {
                $gp = $activeOffer->sallary_offer_hrd;
            } elseif ($cdo && !empty($cdo->gaji_pokok)) {
                $gp = $cdo->gaji_pokok;
            }

            $tunjangan = $cdo ? ($cdo->tunjangan_kerja ?? 0) : 0;

            $posisi = $row->posisi_dilamar;
            if (empty($posisi) || is_numeric($posisi)) {
                $posisi = optional(optional($row->personalRequest)->masterJabatan)->nama_jabatan
                    ?? optional(optional($row->personnelRequest)->masterJabatan)->nama_jabatan
                    ?? '-';
            }

            $noRequest = optional($row->personalRequest)->no_request
                ?? optional($row->personnelRequest)->no_request
                ?? '-';

            return [
                'id' => $row->id,
                'nama_lengkap' => $row->nama_lengkap ?? '-',
                'email' => $row->email ?? '-',
                'posisi_dilamar' => $posisi,
                'no_request' => $noRequest,
                'status' => $row->status ?? '-',
                'has_token' => !empty($row->token_approval),
                'gaji_pokok_hrd' => $gp,
                'tunjangan_hrd' => $tunjangan,
                'total_offering' => ($gp !== null ? (float) $gp : 0) + (float) $tunjangan,
                'default_target_email' => $defaultDirectorEmail,
                'email_sent_at' => optional($activeOffer)->email_sent_at
                    ? Carbon::parse($activeOffer->email_sent_at)->format('d-m-Y H:i')
                    : null,
                'created_at' => optional($row->created_at)->format('d-m-Y H:i') ?: '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }

    /**
     * Preview HTML email Permohonan Persetujuan Offering Salary beserta daftar attachment.
     */
    public function previewOfferingSalaryEmailForFixing(Request $request)
    {
        $recruitmentId = (int) ($request->input('id') ?? $request->input('new_recruitment_id') ?? 0);
        if ($recruitmentId <= 0) {
            return response()->json(['message' => 'ID kandidat wajib diisi.'], 422);
        }

        $applicant = NewRecruitment::with([
            'personalRequest.masterJabatan',
            'personnelRequest.masterJabatan',
            'sallaryOffer',
            'candidateDataOffer',
            'candidateProfile',
            'candidateEducations',
            'candidateWorkExperiences',
            'candidateMedicalInformation',
            'hrdInterview',
            'userInterview',
        ])->find($recruitmentId);

        if (!$applicant) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan.'], 404);
        }

        $token = $applicant->token_approval;
        if (empty($token)) {
            $tokenService = new GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $token = $tokenService->encrypt(md5($tokenKey) . '|' . date('Y-m-d'));
        }

        $buttons = GenerateMessageAtsEmail::buildSalaryDecisionButtons($applicant, $token);
        $emailContent = GenerateMessageAtsEmail::bodyEmailSallaryOffer($applicant, $buttons);
        $subject = 'Permohonan Persetujuan Offering Salary - ' . ($applicant->nama_lengkap ?? 'Kandidat');
        $targetEmail = trim((string) ($request->input('target_email') ?: env('EMAIL_DIREKTUR_BAPAK', '')));

        // Ambil daftar sesi assessment jika ada
        $assessmentLabels = [];
        $attempt = DB::table('assessment_attempts')
            ->where('recruitment_id', $recruitmentId)
            ->orderByDesc('id')
            ->first();
        if ($attempt) {
            $sessions = DB::table('assessment_sessions')
                ->where('assessment_attempt_id', $attempt->id)
                ->whereNotNull('result_json')
                ->where('result_json', '!=', '')
                ->orderBy('session_order')
                ->get();
            foreach ($sessions as $s) {
                $assessmentLabels[] = 'Hasil Assessment: ' . trim((string) ($s->category_name ?? 'Assessment'));
            }
        }

        // Ambil berkas dokumen kandidat
        $docService = app(CandidateDocumentAttachmentService::class);
        $candidateDocLabels = $docService->listAttachmentLabels($recruitmentId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $applicant->id,
                'nama_lengkap' => $applicant->nama_lengkap,
                'subject' => $subject,
                'target_email' => $targetEmail,
                'has_token' => !empty($applicant->token_approval),
                'assessment_labels' => $assessmentLabels,
                'candidate_document_labels' => $candidateDocLabels,
                'total_attachment_count' => count($assessmentLabels) + count($candidateDocLabels),
                'html' => $emailContent,
            ],
        ], 200);
    }

    /**
     * Kirim ulang email Permohonan Persetujuan Offering Salary ke Direktur lengkap dengan seluruh attachment.
     */
    public function reEmailOfferingSalaryApproval(Request $request)
    {
        $recruitmentId = (int) ($request->input('id') ?? $request->input('new_recruitment_id') ?? 0);
        if ($recruitmentId <= 0) {
            return response()->json(['message' => 'ID kandidat wajib diisi.'], 422);
        }

        $applicant = NewRecruitment::with([
            'personalRequest.masterJabatan',
            'personnelRequest.masterJabatan',
            'sallaryOffer',
            'candidateDataOffer',
            'candidateProfile',
            'candidateEducations',
            'candidateWorkExperiences',
            'candidateMedicalInformation',
            'hrdInterview',
            'userInterview',
        ])->find($recruitmentId);

        if (!$applicant) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan.'], 404);
        }

        $targetEmail = trim((string) ($request->input('target_email') ?: env('EMAIL_DIREKTUR_BAPAK', '')));
        if ($targetEmail === '') {
            return response()->json([
                'message' => 'Alamat email tujuan Direktur belum diatur di .env (EMAIL_DIREKTUR_BAPAK) dan belum diisi di form.',
            ], 422);
        }

        $ensureNewToken = filter_var($request->input('ensure_new_token', false), FILTER_VALIDATE_BOOLEAN);
        if (empty($applicant->token_approval) || $ensureNewToken) {
            $tokenService = new GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $applicant->token_approval = $tokenService->encrypt(md5($tokenKey) . '|' . date('Y-m-d'));
            $applicant->save();
        }

        $sender = trim((string) ($request->input('sender') ?: ($this->karyawan ?? 'Programmer Tool')));

        $buttons = GenerateMessageAtsEmail::buildSalaryDecisionButtons($applicant, $applicant->token_approval);
        $emailContent = GenerateMessageAtsEmail::bodyEmailSallaryOffer($applicant, $buttons);
        $subject = 'Permohonan Persetujuan Offering Salary - ' . ($applicant->nama_lengkap ?? 'Kandidat');

        $assessmentService = app(GenerateAssessmentDocumentService::class);
        $documents = [];

        try {
            $attachmentBundle = $assessmentService->prepareDirectorEmailAttachments((int) $applicant->id);
            $documents = $attachmentBundle['documents'] ?? [];
            $attachments = $attachmentBundle['attachments'] ?? [];

            $emailQuery = SendEmail::where('to', $targetEmail)
                ->where('subject', $subject)
                ->where('body', $emailContent)
                ->where('karyawan', $sender)
                ->noReply();

            if (!empty($attachments)) {
                $emailQuery->where('attachment', $attachments);
            }

            $sent = $emailQuery->send();

            if (!$sent) {
                return response()->json([
                    'message' => 'Gagal mengirim email melalui SMTP server.',
                ], 500);
            }

            SallaryOfferService::upsertActive(
                (int) $applicant->id,
                ['email_sent_at' => Carbon::now()],
                $sender
            );

            return response()->json([
                'status' => 'success',
                'message' => "Email Permohonan Persetujuan Offering Salary beserta " . count($attachments) . " berkas lampiran berhasil dikirim ulang ke {$targetEmail}.",
                'data' => [
                    'id' => $applicant->id,
                    'nama_lengkap' => $applicant->nama_lengkap,
                    'target_email' => $targetEmail,
                    'attachment_count' => count($attachments),
                    'assessment_attachment_count' => count($attachmentBundle['assessment_labels'] ?? []),
                    'candidate_document_attachment_count' => count($attachmentBundle['candidate_document_labels'] ?? []),
                    'assessment_labels' => $attachmentBundle['assessment_labels'] ?? [],
                    'candidate_document_labels' => $attachmentBundle['candidate_document_labels'] ?? [],
                    'sent_at' => Carbon::now()->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('reEmailOfferingSalaryApproval failed', [
                'recruitment_id' => $applicant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal memproses pengiriman email: ' . $e->getMessage(),
            ], 500);
        } finally {
            $assessmentService->cleanupDocuments($documents);
        }
    }

    public function searchDisiplinerDocument(Request $request, DisiplinerPdfFixingService $service)
    {
        $this->validate($request, [
            'q' => 'required|string|min:1|max:120',
            'jenis' => 'nullable|in:sp,bak,st',
        ]);

        try {
            $items = $service->search(
                (string) $request->input('q'),
                $request->filled('jenis') ? (string) $request->jenis : null
            );

            return response()->json([
                'success' => true,
                'data' => $items,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function rerenderPdfDisipliner(Request $request, DisiplinerPdfFixingService $service)
    {
        $this->validate($request, [
            'jenis' => 'required|in:sp,bak,st',
            'id' => 'required|integer|min:1',
        ]);

        try {
            $data = $service->regenerate((string) $request->jenis, (int) $request->id);

            return response()->json([
                'success' => true,
                'message' => 'PDF dokumen disipliner berhasil di-render ulang.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function updateDisiplinerDocumentStatus(Request $request, DisiplinerPdfFixingService $service)
    {
        $this->validate($request, [
            'jenis' => 'required|in:sp,bak,st',
            'id' => 'required|integer|min:1',
            'status' => 'required|in:draft,issued,cancelled',
        ]);

        try {
            $data = $service->updateStatus(
                (string) $request->jenis,
                (int) $request->id,
                (string) $request->status,
                (string) ($this->karyawan ?: 'Fixing Tools')
            );

            Log::info('Fixing: update status dokumen disipliner', [
                'jenis' => $request->jenis,
                'id' => (int) $request->id,
                'status' => $request->status,
                'actor' => $this->karyawan,
                'actor_id' => (int) $this->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status dokumen disipliner berhasil diperbarui.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }
}
