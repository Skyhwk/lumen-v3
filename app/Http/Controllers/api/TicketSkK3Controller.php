<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\MasterKaryawan;
use App\Models\MasterWilayahSampling;
use App\Models\MasterWilayahSurat;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Models\TicketSkK3;
use App\Models\TicketSkK3Conversation;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use App\Services\TicketSkK3ConversationService;
use App\Services\TicketSkK3SuratService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class TicketSkK3Controller extends Controller
{
    private const SALES_DEPARTMENT_ID = 9;
    private const K3_DEPARTMENT_ID = 19;
    private const IT_DEPARTMENT_ID = 7;
    private const SUPERADMIN_IDS = [1, 127, 152];
    private const MENU_URL = '/request/ticket-sk-k3';
    private const VOIDABLE_STATUSES = ['WAITING APPROVE', 'WAITING PROCESS', 'PROCESS'];
    private const EDITABLE_STATUSES = ['WAITING APPROVE', 'WAITING PROCESS'];

    private function isSuperAdmin(): bool
    {
        return in_array((int) $this->user_id, self::SUPERADMIN_IDS, true);
    }

    private function isK3Team(): bool
    {
        return (int) $this->department === self::K3_DEPARTMENT_ID || $this->isSuperAdmin();
    }

    private function isItProgrammingTeam(): bool
    {
        return (int) $this->department === self::IT_DEPARTMENT_ID;
    }

    private function canViewAllTickets(): bool
    {
        return $this->isK3Team() || $this->isItProgrammingTeam();
    }

    private function isSalesTeam(): bool
    {
        return (int) $this->department === self::SALES_DEPARTMENT_ID || $this->isSuperAdmin();
    }

    private function canSubmitTicket(): bool
    {
        return $this->isSalesTeam() || $this->isItProgrammingTeam();
    }

    private function notifyK3(string $title, string $message)
    {
        $k3Ids = MasterKaryawan::where('id_department', self::K3_DEPARTMENT_ID)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        if (empty($k3Ids)) {
            return;
        }

        Notification::whereIn('id', $k3Ids)
            ->title($title)
            ->message($message)
            ->url(self::MENU_URL)
            ->send();
    }

    private function notifyCreator($ticket, string $title, string $message)
    {
        if (!$ticket || !$ticket->created_by) {
            return;
        }

        Notification::where('nama_lengkap', $ticket->created_by)
            ->title($title)
            ->message($message)
            ->url(self::MENU_URL)
            ->send();
    }

    private function notifyAtasan(string $title, string $message)
    {
        $atasanIds = GetAtasan::where('nama_lengkap', $this->karyawan)->get()->pluck('id');
        if ($atasanIds->isEmpty()) {
            return;
        }

        Notification::whereIn('id', $atasanIds)
            ->title($title)
            ->message($message)
            ->url(self::MENU_URL)
            ->send();
    }

    private function findTicket($id)
    {
        return TicketSkK3::where('id', $id)->where('is_active', true)->first();
    }

    private function getActiveJadwal($samplingPlanId)
    {
        if (!$samplingPlanId) {
            return collect();
        }

        return Jadwal::where('id_sampling', $samplingPlanId)
            ->where('is_active', true)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    private function getCertifiedK3Samplers()
    {
        return MasterKaryawan::where('is_active', true)
            ->where('has_k3_sampler_certification', 1)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
    }

    private function jadwalDaySpan($durasi): int
    {
        return ((int) $durasi > 1) ? (int) $durasi : 1;
    }

    private function formatJamJadwal($jam): string
    {
        $value = trim((string) $jam);
        if ($value === '') {
            return '';
        }

        $parts = explode(':', $value);
        return count($parts) >= 2 ? $parts[0] . ':' . $parts[1] : $value;
    }

    private function durasiLabel($durasi): string
    {
        $labels = ['Sesaat', '8 Jam', '1x24 Jam', '2x24 Jam', '3x24 Jam', '4x24 Jam', '5x24 Jam', '6x24 Jam', '7x24 Jam', '8x24 Jam'];
        $index = (int) $durasi;
        return $labels[$index] ?? (string) $durasi;
    }

    private function formatKategoriJadwal($raw): string
    {
        $value = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode(', ', array_filter($decoded, function ($item) {
                return $item !== null && $item !== '';
            }));
        }

        return $value;
    }

    private function buildScheduleDates($jadwal, $k3Samplers)
    {
        $dates = [];
        $samplerByName = $k3Samplers->keyBy(function ($item) {
            return strtoupper(trim((string) $item->nama_lengkap));
        });

        foreach ($jadwal as $row) {
            if (!$row->tanggal) {
                continue;
            }

            $start = Carbon::parse($row->tanggal)->startOfDay();
            $span = $this->jadwalDaySpan($row->durasi);
            $existingMap = $this->decodeSamplerK3Map($row);

            for ($i = 0; $i < $span; $i++) {
                $tanggal = $start->copy()->addDays($i)->format('Y-m-d');
                if (!isset($dates[$tanggal])) {
                    $dates[$tanggal] = [
                        'tanggal' => $tanggal,
                        'samplers' => [],
                        'slots' => [],
                        'selected_karyawan_id' => null,
                    ];
                }

                if ($row->sampler && !in_array($row->sampler, $dates[$tanggal]['samplers'], true)) {
                    $dates[$tanggal]['samplers'][] = $row->sampler;
                }

                $dates[$tanggal]['slots'][] = [
                    'nama_perusahaan' => $row->nama_perusahaan,
                    'wilayah' => $row->wilayah,
                    'alamat' => $row->alamat,
                    'jam_mulai' => $this->formatJamJadwal($row->jam_mulai ?: $row->jam),
                    'jam_selesai' => $this->formatJamJadwal($row->jam_selesai),
                    'durasi' => (int) $row->durasi,
                    'durasi_label' => $this->durasiLabel($row->durasi),
                    'kategori' => $this->formatKategoriJadwal($row->kategori),
                    'sampler' => $row->sampler,
                    'driver' => $row->driver,
                    'kendaraan' => $row->kendaraan,
                ];

                $assignedName = $existingMap[$tanggal] ?? null;
                if ($assignedName && !$dates[$tanggal]['selected_karyawan_id']) {
                    $match = $samplerByName->get(strtoupper(trim($assignedName)));
                    if ($match) {
                        $dates[$tanggal]['selected_karyawan_id'] = (int) $match->id;
                    }
                }
            }
        }

        ksort($dates);

        return array_values($dates);
    }

    private function decodeSamplerK3Map($row): array
    {
        $value = trim((string) ($row->sampler_k3 ?? ''));
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = $row->tanggal ? Carbon::parse($row->tanggal)->format('Y-m-d') : '';
        return $start !== '' ? [$start => $value] : [];
    }

    private function assignSamplerK3ToJadwal($row, array $assignmentMap)
    {
        if (!$row->tanggal) {
            return;
        }

        $start = Carbon::parse($row->tanggal)->startOfDay();
        $span = $this->jadwalDaySpan($row->durasi);
        $map = [];

        for ($i = 0; $i < $span; $i++) {
            $tanggal = $start->copy()->addDays($i)->format('Y-m-d');
            if (isset($assignmentMap[$tanggal])) {
                $map[$tanggal] = $assignmentMap[$tanggal];
            }
        }

        if (count($map) === 0) {
            return;
        }

        $uniqueNames = array_values(array_unique(array_values($map)));
        $encoded = count($uniqueNames) === 1
            ? $uniqueNames[0]
            : json_encode($map, JSON_UNESCAPED_UNICODE);
        if (strlen((string) $encoded) > 255) {
            $encoded = implode(', ', $uniqueNames);
        }
        $row->sampler_k3 = $encoded;
        $row->pendampingan_k3 = 1;
        $row->save();
    }

    private function baseTicketListQuery()
    {
        if ($this->canViewAllTickets()) {
            return TicketSkK3::with('samplingPlan.jadwal')->where('is_active', true)->orderBy('id', 'desc');
        }

        $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();

        return TicketSkK3::with('samplingPlan.jadwal')
            ->whereIn('request_by', $getBawahan)
            ->where('is_active', true)
            ->orderBy('id', 'desc');
    }

    private function applyStatusTabFilter($query, ?string $statusTab)
    {
        if (!$statusTab) {
            return $query;
        }

        switch ($statusTab) {
            case 'waiting_approve':
                return $query->where('status', 'WAITING APPROVE');
            case 'waiting_process':
                return $query->where('status', 'WAITING PROCESS');
            case 'process':
                return $query->where('status', 'PROCESS');
            case 'scheduled':
                return $query->where('status', 'SCHEDULED');
            case 'release_dokumen':
                return $query->where('status', 'RELEASE DOKUMEN');
            case 'reject':
                return $query->where('status', 'REJECT');
            case 'void':
                return $query->where('status', 'VOID');
            default:
                return $query;
        }
    }

    private function makeDatatable($data, bool $includeCanApprove = false, array $getBawahan = [])
    {
        $datatable = Datatables::of($data)
            ->addColumn('unread_count', function ($row) {
                return TicketSkK3ConversationService::getUnreadCount($row->id, $this->user_id);
            })
            ->addColumn('sampling_plan', function ($row) {
                return $row->samplingPlan;
            })
            ->addColumn('surat_folder', function ($row) {
                return TicketSkK3SuratService::folderFor($row);
            });

        if ($includeCanApprove) {
            $datatable->addColumn('can_approve', function ($row) use ($getBawahan) {
                return in_array($row->created_by, $getBawahan, true)
                    && $this->karyawan !== $row->created_by
                    && $row->status === 'WAITING APPROVE';
            });
        }

        return $datatable->make(true);
    }

    public function tabCounts(Request $request)
    {
        try {
            $tabs = [
                'waiting_approve',
                'waiting_process',
                'process',
                'scheduled',
                'release_dokumen',
                'reject',
                'void',
            ];
            $counts = [];

            foreach ($tabs as $tab) {
                $query = $this->baseTicketListQuery();
                $counts[$tab] = $this->applyStatusTabFilter($query, $tab)->count();
            }

            return response()->json([
                'success' => true,
                'data' => $counts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $data = $this->applyStatusTabFilter(
                $this->baseTicketListQuery(),
                $request->status_tab
            );

            if ($this->canViewAllTickets()) {
                return $this->makeDatatable($data);
            }

            $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();

            return $this->makeDatatable($data, true, $getBawahan);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function searchQuotations(Request $request)
    {
        try {
            $term = trim((string) ($request->q ?? $request->term ?? ''));
            if ($term === '' || strlen($term) < 2) {
                return response()->json(['success' => true, 'data' => []], 200);
            }

            $like = '%' . $term . '%';

            $non = QuotationNonKontrak::where('is_active', true)
                ->where('flag_status', 'ordered')
                ->where(function ($q) use ($like) {
                    $q->where('no_document', 'like', $like)
                        ->orWhere('nama_perusahaan', 'like', $like);
                })
                ->orderBy('id', 'desc')
                ->limit(15)
                ->get(['id', 'no_document', 'nama_perusahaan', 'wilayah']);

            $kontrak = QuotationKontrakH::where('is_active', true)
                ->where('flag_status', 'ordered')
                ->where(function ($q) use ($like) {
                    $q->where('no_document', 'like', $like)
                        ->orWhere('nama_perusahaan', 'like', $like);
                })
                ->orderBy('id', 'desc')
                ->limit(15)
                ->get(['id', 'no_document', 'nama_perusahaan', 'wilayah']);

            $results = [];
            foreach ($non as $row) {
                $surat = $this->resolveWilayahSuratFromQuotationWilayah($row->wilayah);
                $results[] = array_merge([
                    'id' => $row->no_document,
                    'text' => $row->no_document . ' - ' . $row->nama_perusahaan . ' (Non Kontrak)',
                    'tipe' => 'non_kontrak',
                    'quotation_id' => $row->id,
                    'nama_perusahaan' => $row->nama_perusahaan,
                    'no_document' => $row->no_document,
                    'wilayah_quotation' => $row->wilayah,
                ], $surat);
            }
            foreach ($kontrak as $row) {
                $surat = $this->resolveWilayahSuratFromQuotationWilayah($row->wilayah);
                $results[] = array_merge([
                    'id' => $row->no_document,
                    'text' => $row->no_document . ' - ' . $row->nama_perusahaan . ' (Kontrak)',
                    'tipe' => 'kontrak',
                    'quotation_id' => $row->id,
                    'nama_perusahaan' => $row->nama_perusahaan,
                    'no_document' => $row->no_document,
                    'wilayah_quotation' => $row->wilayah,
                ], $surat);
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function getPeriodes(Request $request)
    {
        try {
            $noQuotation = trim((string) $request->no_quotation);
            if ($noQuotation === '') {
                return response()->json(['success' => true, 'data' => []], 200);
            }

            $header = QuotationKontrakH::where('no_document', $noQuotation)
                ->where('is_active', true)
                ->first(['id']);

            if (!$header) {
                return response()->json(['success' => true, 'data' => []], 200);
            }

            $periodes = QuotationKontrakD::where('id_request_quotation_kontrak_h', $header->id)
                ->whereNotNull('periode_kontrak')
                ->where('periode_kontrak', '!=', '')
                ->orderBy('periode_kontrak', 'asc')
                ->pluck('periode_kontrak')
                ->unique()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $periodes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function getSamplingPlans(Request $request)
    {
        try {
            $noQuotation = trim((string) $request->no_quotation);
            $periode = trim((string) ($request->periode ?? ''));

            if ($noQuotation === '') {
                return response()->json(['success' => true, 'data' => []], 200);
            }

            $query = SamplingPlan::with('jadwal')
                ->where('no_quotation', $noQuotation)
                ->where('is_approved', 1)
                ->where('is_active', true);

            if ($periode !== '') {
                $query->where('periode_kontrak', $periode);
            }

            $plans = $query->orderBy('id', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $plans,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function getSamplingPlanDetail(Request $request)
    {
        try {
            $plan = SamplingPlan::with('jadwal')->where('id', $request->id)->first();
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sampling plan tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $plan,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function parseQuotationWilayahName($raw)
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }

        if (strpos($value, '-') !== false) {
            $value = trim((string) substr($value, strpos($value, '-') + 1));
        }

        $value = preg_replace('/\s*\(K\)\s*$/i', '', $value);

        return strtoupper(trim((string) $value));
    }

    private function resolveWilayahSuratFromQuotationWilayah($rawWilayah)
    {
        $empty = [
            'default_wilayah_surat' => '',
            'default_wilayah_surat_id' => null,
            'wilayah_sampling' => '',
        ];

        $name = $this->parseQuotationWilayahName($rawWilayah);
        if ($name === '') {
            return $empty;
        }

        $sampling = MasterWilayahSampling::where('is_active', 1)
            ->whereRaw('UPPER(wilayah) = ?', [$name])
            ->first();

        if (!$sampling) {
            $sampling = MasterWilayahSampling::where('is_active', 1)
                ->whereRaw('UPPER(wilayah) LIKE ?', ['%' . $name . '%'])
                ->first();
        }

        $surat = null;
        if ($sampling) {
            $suratId = DB::table('master_wilayah_surat_sampling')
                ->where('id_master_wilayah_sampling', $sampling->id)
                ->value('master_wilayah_surat_id');

            if ($suratId) {
                $surat = MasterWilayahSurat::where('id', $suratId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first();
            }
        }

        if (!$surat) {
            $surat = MasterWilayahSurat::where('is_active', 1)
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(wilayah_pelanggan) LIKE ?', ['%' . $name . '%'])
                ->orderBy('id')
                ->first();
        }

        if (!$surat) {
            return array_merge($empty, [
                'wilayah_sampling' => $sampling->wilayah ?? $name,
            ]);
        }

        return [
            'default_wilayah_surat' => $surat->wilayah_pelanggan,
            'default_wilayah_surat_id' => $surat->id,
            'wilayah_sampling' => $sampling->wilayah ?? $name,
        ];
    }

    public function getWilayahSurat()
    {
        try {
            $data = MasterWilayahSurat::where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('wilayah_pelanggan', 'asc')
                ->get(['id', 'wilayah_pelanggan'])
                ->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'nama_wilayah' => $row->wilayah_pelanggan,
                        'wilayah_pelanggan' => $row->wilayah_pelanggan,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if (empty($request->id) && !$this->canSubmitTicket()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi Sales atau IT Programming yang dapat mengajukan Ticket SK K3',
                ], 403);
            }

            $noQuotation = trim((string) $request->no_quotation);
            $tipe = $request->tipe_quotation;
            $periode = trim((string) ($request->periode ?? ''));
            $samplingPlanId = $request->sampling_plan_id;
            $wilayahSurat = trim((string) $request->wilayah_surat);
            $keterangan = $request->keterangan ?? $request->details ?? '';

            if ($noQuotation === '' || !$samplingPlanId || $wilayahSurat === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'No Quotation, No Dokumen SP, dan Wilayah Surat wajib diisi',
                ], 422);
            }

            $quotation = $this->resolveOrderedQuotation($noQuotation, $tipe);
            if (!$quotation) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Quotation tidak ditemukan atau belum ordered',
                ], 422);
            }

            if ($quotation['tipe'] === 'kontrak' && $periode === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode wajib diisi untuk quotation kontrak',
                ], 422);
            }

            $samplingPlan = SamplingPlan::where('id', $samplingPlanId)
                ->where('no_quotation', $noQuotation)
                ->where('is_approved', 1)
                ->where('is_active', true)
                ->first();

            if (!$samplingPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen SP tidak valid atau belum di-approve',
                ], 422);
            }

            if ($quotation['tipe'] === 'kontrak' && $periode !== '' && (string) $samplingPlan->periode_kontrak !== $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen SP tidak sesuai dengan periode quotation',
                ], 422);
            }

            if (empty($request->id)) {
                $data = new TicketSkK3();
                $data->nomor_ticket = 'SKK3' . str_replace('.', '', (string) microtime(true));
                $data->request_by = $this->karyawan;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->request_time = Carbon::now();
                $data->is_active = true;
                $message = 'Ticket SK K3 telah ditambahkan';
            } else {
                $data = $this->findTicket($request->id);
                if (!$data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket SK K3 tidak ditemukan',
                    ], 404);
                }

                if ($data->created_by !== $this->karyawan) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya pembuat ticket yang dapat mengubah ticket ini',
                    ], 403);
                }

                if (!in_array($data->status, self::EDITABLE_STATUSES, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket tidak dapat diubah karena sudah diproses',
                    ], 422);
                }

                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
                $message = 'Ticket SK K3 telah diperbarui';
            }

            if ($request->hasFile('dokumentasi')) {
                $dir = 'ticket_sk_k3';
                if (!file_exists(public_path($dir))) {
                    mkdir(public_path($dir), 0777, true);
                }

                $file = $request->file('dokumentasi');
                $filenameDok = 'SKK3_' . $data->nomor_ticket . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($dir), $filenameDok);
                $data->dokumentasi = $filenameDok;
            }

            $data->no_quotation = $quotation['no_document'];
            $data->tipe_quotation = $quotation['tipe'];
            $data->quotation_id = $quotation['id'];
            $data->nama_perusahaan = $quotation['nama_perusahaan'];
            $data->periode = $quotation['tipe'] === 'kontrak' ? $periode : null;
            $data->sampling_plan_id = $samplingPlan->id;
            $data->no_document_sp = $samplingPlan->no_document;
            $data->wilayah_surat = $wilayahSurat;
            $data->keterangan = $keterangan;

            $autoApprove = strtoupper((string) $this->grade) === 'MANAGER';
            $data->status = $autoApprove ? 'WAITING PROCESS' : 'WAITING APPROVE';
            if ($autoApprove) {
                $data->approved_by = $this->karyawan;
                $data->approved_time = Carbon::now()->format('Y-m-d H:i:s');
            }

            $data->save();

            if ($autoApprove) {
                $this->notifyK3('Ticket SK K3 Siap Diproses', $message . ' oleh ' . $this->karyawan . ' dan sudah auto-approve');
            } else {
                $this->notifyAtasan('Ticket SK K3 Menunggu Approve', $message . ' oleh ' . $this->karyawan . ' dan menunggu persetujuan atasan');
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal proses Ticket SK K3: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveOrderedQuotation($noQuotation, $tipeHint = null)
    {
        if ($tipeHint === 'kontrak' || $tipeHint === null) {
            $kontrak = QuotationKontrakH::where('no_document', $noQuotation)
                ->where('is_active', true)
                ->where('flag_status', 'ordered')
                ->first(['id', 'no_document', 'nama_perusahaan']);
            if ($kontrak) {
                return [
                    'id' => $kontrak->id,
                    'no_document' => $kontrak->no_document,
                    'nama_perusahaan' => $kontrak->nama_perusahaan,
                    'tipe' => 'kontrak',
                ];
            }
        }

        $non = QuotationNonKontrak::where('no_document', $noQuotation)
            ->where('is_active', true)
            ->where('flag_status', 'ordered')
            ->first(['id', 'no_document', 'nama_perusahaan']);

        if ($non) {
            return [
                'id' => $non->id,
                'no_document' => $non->no_document,
                'nama_perusahaan' => $non->nama_perusahaan,
                'tipe' => 'non_kontrak',
            ];
        }

        return null;
    }

    public function approveTicket(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->status !== 'WAITING APPROVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak dalam status waiting approve',
                ], 422);
            }

            if ($data->created_by === $this->karyawan && !$this->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket harus disetujui oleh atasan',
                ], 403);
            }

            $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
            if (!$this->isSuperAdmin() && !in_array($data->created_by, $bawahan, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan atasan dari pembuat ticket ini',
                ], 403);
            }

            $data->approved_by = $this->karyawan;
            $data->approved_time = Carbon::now()->format('Y-m-d H:i:s');
            $data->status = 'WAITING PROCESS';
            $data->save();

            $message = 'Ticket SK K3 telah diapprove oleh ' . $this->karyawan . ' dan siap diproses oleh divisi K3';
            $this->notifyK3('Ticket SK K3 Siap Diproses', $message);
            $this->notifyCreator($data, 'Ticket SK K3 Update', $message);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve Ticket SK K3: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function process(Request $request)
    {
        return $this->transitionByK3($request, 'WAITING PROCESS', 'PROCESS', 'process_by', 'process_time', 'Ticket SK K3 sedang diproses', false);
    }

    public function getScheduleForm(Request $request)
    {
        try {
            if (!$this->isK3Team() && !$this->isItProgrammingTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi K3 yang dapat melihat form schedule',
                ], 403);
            }

            $ticket = $this->findTicket($request->id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($ticket->status !== 'PROCESS') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket harus berstatus PROCESS untuk dijadwalkan',
                ], 422);
            }

            $jadwal = $this->getActiveJadwal($ticket->sampling_plan_id);
            $k3Samplers = $this->getCertifiedK3Samplers();

            return response()->json([
                'success' => true,
                'data' => [
                    'ticket' => [
                        'nomor_ticket' => $ticket->nomor_ticket,
                        'nama_perusahaan' => $ticket->nama_perusahaan,
                        'no_quotation' => $ticket->no_quotation,
                        'no_document_sp' => $ticket->no_document_sp,
                        'wilayah_surat' => $ticket->wilayah_surat,
                        'periode' => $ticket->periode,
                    ],
                    'dates' => $this->buildScheduleDates($jadwal, $k3Samplers),
                    'k3_samplers' => $k3Samplers->map(function ($item) {
                        return [
                            'id' => (int) $item->id,
                            'nama_lengkap' => $item->nama_lengkap,
                        ];
                    })->values(),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal SP: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function schedule(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$this->isK3Team() && !$this->isItProgrammingTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi K3 yang dapat memproses ticket ini',
                ], 403);
            }

            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->status !== 'PROCESS') {
                return response()->json([
                    'success' => false,
                    'message' => 'Status ticket tidak sesuai untuk aksi ini',
                ], 422);
            }

            $jadwal = $this->getActiveJadwal($data->sampling_plan_id);
            if ($jadwal->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sampling plan ini belum punya jadwal',
                ], 422);
            }

            $k3Samplers = $this->getCertifiedK3Samplers();
            if ($k3Samplers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada karyawan aktif dengan sertifikasi sampler K3',
                ], 422);
            }

            $dates = $this->buildScheduleDates($jadwal, $k3Samplers);
            $assignments = json_decode($request->sampler_k3_assignments, true);
            if (!is_array($assignments) || count($assignments) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sampler pendamping K3 wajib dipilih untuk setiap tanggal jadwal',
                ], 422);
            }

            $assignmentMap = [];
            foreach ($assignments as $item) {
                $tanggal = isset($item['tanggal']) ? Carbon::parse($item['tanggal'])->format('Y-m-d') : '';
                $karyawanId = isset($item['karyawan_id']) ? (int) $item['karyawan_id'] : 0;
                if ($tanggal !== '' && $karyawanId > 0) {
                    $assignmentMap[$tanggal] = $karyawanId;
                }
            }

            $samplerById = $k3Samplers->keyBy('id');
            foreach ($dates as $dateRow) {
                $tanggal = $dateRow['tanggal'];
                if (!isset($assignmentMap[$tanggal])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sampler pendamping K3 belum dipilih untuk tanggal ' . $tanggal,
                    ], 422);
                }

                $karyawan = $samplerById->get($assignmentMap[$tanggal]);
                if (!$karyawan) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sampler K3 tidak valid atau tidak memiliki sertifikasi',
                    ], 422);
                }

                $assignmentMap[$tanggal] = $karyawan->nama_lengkap;
            }

            foreach ($jadwal as $row) {
                $this->assignSamplerK3ToJadwal($row, $assignmentMap);
            }

            $data->status = 'SCHEDULED';
            $data->scheduled_by = $this->karyawan;
            $data->scheduled_time = Carbon::now();
            $data->save();

            $message = 'Ticket SK K3 telah dijadwalkan';
            $this->notifyCreator($data, 'Ticket SK K3 Update', $message . ' oleh ' . $this->karyawan);

            $plan = SamplingPlan::with('jadwal')->where('id', $data->sampling_plan_id)->first();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $plan,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal update Ticket SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            $data->load('samplingPlan.jadwal');
            $payload = $data->toArray();
            $payload['sampling_plan'] = $data->samplingPlan;
            $payload['surat_folder'] = TicketSkK3SuratService::folderFor($data);

            return response()->json([
                'success' => true,
                'data' => $payload,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat ticket SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function generateSurat(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$this->isK3Team() && !$this->isItProgrammingTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi K3 yang dapat memproses ticket ini',
                ], 403);
            }

            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->status !== 'SCHEDULED') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket harus berstatus SCHEDULED untuk generate surat',
                ], 422);
            }

            if (trim((string) $data->filename_surat) !== '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat sudah digenerate dan tidak dapat digenerate ulang. Download, TTD, lalu upload.',
                ], 422);
            }

            $surat = (new TicketSkK3SuratService())->generate($data);
            $data->no_document_surat = $surat['no_document_surat'];
            $data->filename_surat = $surat['filename_surat'];
            $data->save();

            DB::commit();
            $surat['surat_folder'] = TicketSkK3SuratService::DIR_GENERATED;
            return response()->json([
                'success' => true,
                'message' => 'Surat pemberitahuan berhasil digenerate. Download, TTD basah, lalu upload. Generate hanya sekali.',
                'data' => $surat,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate surat SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function previewSurat(Request $request)
    {
        try {
            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if (!$data->filename_surat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat pemberitahuan belum digenerate',
                ], 422);
            }

            $path = TicketSkK3SuratService::absolutePath($data);
            if (!$path) {
                return response()->json([
                    'success' => false,
                    'message' => 'File surat tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'no_document_surat' => $data->no_document_surat,
                    'filename_surat' => $data->filename_surat,
                    'surat_folder' => TicketSkK3SuratService::folderFor($data),
                    'file_base64' => base64_encode((string) file_get_contents($path)),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat surat SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function release(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$this->isK3Team() && !$this->isItProgrammingTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi K3 yang dapat memproses ticket ini',
                ], 403);
            }

            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->status !== 'SCHEDULED') {
                return response()->json([
                    'success' => false,
                    'message' => 'Status ticket tidak sesuai untuk aksi ini',
                ], 422);
            }

            if (!$data->no_document_surat || !$data->filename_surat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Generate surat pemberitahuan terlebih dahulu',
                ], 422);
            }

            if (!$request->hasFile('ttd')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload PDF yang sudah TTD basah',
                ], 422);
            }

            $file = $request->file('ttd');
            $extension = strtolower((string) $file->getClientOriginalExtension());
            if ($extension !== 'pdf') {
                return response()->json([
                    'success' => false,
                    'message' => 'File TTD basah harus PDF',
                ], 422);
            }

            $dir = TicketSkK3SuratService::ensureDir(TicketSkK3SuratService::DIR_UPLOADED);
            $filename = $data->filename_surat;
            $file->move($dir, $filename);

            $data->filename_surat = $filename;
            $data->status = 'RELEASE DOKUMEN';
            $data->released_by = $this->karyawan;
            $data->released_time = Carbon::now();
            $data->save();

            $message = 'Dokumen SK K3 telah di-release';
            $this->notifyCreator($data, 'Ticket SK K3 Update', $message . ' oleh ' . $this->karyawan);
            TicketSkK3ConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'no_document_surat' => $data->no_document_surat,
                    'filename_surat' => $data->filename_surat,
                    'surat_folder' => TicketSkK3SuratService::DIR_UPLOADED,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal release dokumen SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    private function transitionByK3(Request $request, $fromStatus, $toStatus, $byField, $timeField, $message, $closeChat)
    {
        DB::beginTransaction();
        try {
            if (!$this->isK3Team() && !$this->isItProgrammingTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi K3 yang dapat memproses ticket ini',
                ], 403);
            }

            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->status !== $fromStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status ticket tidak sesuai untuk aksi ini',
                ], 422);
            }

            $data->status = $toStatus;
            $data->{$byField} = $this->karyawan;
            $data->{$timeField} = Carbon::now();
            $data->save();

            $this->notifyCreator($data, 'Ticket SK K3 Update', $message . ' oleh ' . $this->karyawan);

            if ($closeChat) {
                TicketSkK3ConversationService::notifyConversationClosed($data, $this->karyawan);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal update Ticket SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$this->isK3Team() && !$this->isItProgrammingTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya divisi K3 yang dapat reject ticket ini',
                ], 403);
            }

            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if (!in_array($data->status, ['WAITING PROCESS', 'PROCESS'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak dapat direject pada status ini',
                ], 422);
            }

            $data->status = 'REJECT';
            $data->rejected_by = $this->karyawan;
            $data->rejected_time = Carbon::now();
            $data->rejected_notes = $request->notes;
            $data->save();

            $message = 'Ticket SK K3 telah direject';
            $this->notifyCreator($data, 'Ticket SK K3 Update', $message . ' oleh ' . $this->karyawan);
            TicketSkK3ConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal reject Ticket SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function void(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->created_by !== $this->karyawan && !$this->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya sales pembuat ticket yang dapat void',
                ], 403);
            }

            if (!in_array($data->status, self::VOIDABLE_STATUSES, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak dapat di-void setelah status scheduled',
                ], 422);
            }

            $wasProcessed = $data->status === 'PROCESS' || !empty($data->process_by);
            $data->status = 'VOID';
            $data->void_by = $this->karyawan;
            $data->void_time = Carbon::now();
            $data->void_notes = $request->notes ?? $request->reason;
            $data->save();

            $message = 'Ticket SK K3 telah di-void';
            if ($wasProcessed) {
                $this->notifyK3('Ticket SK K3 Update', $message . ' oleh ' . $this->karyawan);
            }
            TicketSkK3ConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal void Ticket SK K3: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $data = $this->findTicket($request->id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if ($data->created_by !== $this->karyawan && !$this->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya pembuat ticket yang dapat menghapus',
                ], 403);
            }

            if (!in_array($data->status, self::EDITABLE_STATUSES, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak dapat dihapus karena sudah diproses',
                ], 422);
            }

            $data->is_active = false;
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now();
            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'Ticket SK K3 telah dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getConversations(Request $request)
    {
        try {
            $ticket = $this->findTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            $conversations = TicketSkK3Conversation::where('ticket_sk_k3_id', $ticket->id)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($item) {
                    return TicketSkK3ConversationService::formatConversation($item, $this->user_id);
                });

            return response()->json([
                'success' => true,
                'data' => $conversations,
                'is_closed' => TicketSkK3ConversationService::isConversationClosed($ticket->status),
                'is_open' => TicketSkK3ConversationService::isConversationOpen($ticket->status),
                'message' => 'Conversation berhasil diambil',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendConversation(Request $request)
    {
        DB::beginTransaction();
        try {
            $message = trim($request->message ?? '');
            $hasAttachment = $request->hasFile('attachment');

            if (empty($request->ticket_id) || ($message === '' && !$hasAttachment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket ID dan pesan atau lampiran wajib diisi',
                ], 422);
            }

            $ticket = $this->findTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if (TicketSkK3ConversationService::isConversationClosed($ticket->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket sudah ditutup, tidak dapat mengirim pesan',
                ], 422);
            }

            if (!TicketSkK3ConversationService::isConversationOpen($ticket->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation belum dibuka. Ticket harus diproses terlebih dahulu',
                ], 422);
            }

            $attachmentFilename = null;
            if ($hasAttachment) {
                $file = $request->file('attachment');
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower($file->getClientOriginalExtension());

                if (!in_array($ext, $allowedExt, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Format lampiran harus jpg, jpeg, png, gif, atau webp',
                    ], 422);
                }

                if ($file->getSize() > 2097152) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ukuran lampiran maksimal 2MB',
                    ], 422);
                }

                $dir = 'ticket_sk_k3/conversation';
                if (!file_exists(public_path($dir))) {
                    mkdir(public_path($dir), 0777, true);
                }

                $attachmentFilename = 'CONV_' . $ticket->nomor_ticket . '_' . time() . '.' . $ext;
                $file->move(public_path($dir), $attachmentFilename);
            }

            $conversation = TicketSkK3ConversationService::createMessage(
                $ticket,
                $this->user_id,
                $this->karyawan,
                TicketSkK3ConversationService::resolveSenderRole($this->department),
                $message !== '' ? $message : '[Lampiran gambar]',
                $attachmentFilename
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => TicketSkK3ConversationService::formatConversation($conversation, $this->user_id),
                'message' => 'Pesan berhasil dikirim',
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim pesan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markConversationRead(Request $request)
    {
        try {
            if (empty($request->ticket_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket ID wajib diisi',
                ], 422);
            }

            $ticket = $this->findTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            TicketSkK3ConversationService::markAsRead($request->ticket_id, $this->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Conversation ditandai sudah dibaca',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
