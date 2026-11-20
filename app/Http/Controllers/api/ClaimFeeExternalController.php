<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClaimFeeExternal;
use App\Models\DailyQsd;
use App\Models\MasterBankTransfer;
use Yajra\DataTables\Facades\DataTables;
use App\Services\GetBawahan;
use App\Services\ClaimFeeExternalSalesWorkflowService;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\SalesTeamHierarchyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Http\Controllers\api\Concerns\ScopesClaimFeeExternalRekap;

class ClaimFeeExternalController extends Controller
{
    use ScopesClaimFeeExternalRekap;
    public function outstandingIndex(Request $request)
    {
        $status = $request->status;
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->whereIn('status_pembayaran', ['WAITING PROCESS', 'REJECTED']);

        if (in_array($jabatan, [24, 86, 148])) {
            $query->where('sales_id', $this->user_id);
        }else if (in_array($jabatan, [21, 15, 154, 157])) {
            $query->whereIn('sales_id', $this->salesIdsBawahan());
        }
        return DataTables::of($query)->make(true);
    }


    
    public function settlementIndex(Request $request)
    {
        $status = $request->status;
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        
        $query = ClaimFeeExternal::query()
            ->where('is_active', true)->whereIn('status_pembayaran', ['PROCESSED', 'READY TO TRANSFER', 'TRANSFER']);

        if (in_array($jabatan, [24, 86, 148])) {
            $query->where('sales_id', $this->user_id);
        }else if (in_array($jabatan, [21, 15, 154, 157])) {
            $query->whereIn('sales_id', $this->salesIdsBawahan());
        }

        return DataTables::of($query)->make(true);
    }


    public function listBanks(Request $request)
    {
        if (!Schema::hasTable('master_bank_transfer')) {
            return response()->json(['data' => []]);
        }

        $term = trim((string) $request->input('term', ''));
        $include = trim((string) $request->input('include', ''));

        $query = MasterBankTransfer::query()
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama_bank');

        if ($term !== '') {
            $query->where('nama_bank', 'like', '%' . $term . '%');
        }

        $names = $query->limit(100)->pluck('nama_bank');

        $data = $names->map(static fn ($name) => [
            'id' => $name,
            'text' => $name,
        ])->values();

        if (
            $include !== ''
            && !$names->contains(static fn ($n) => strcasecmp($n, $include) === 0)
        ) {
            $data->prepend([
                'id' => $include,
                'text' => $include . ' (tersimpan)',
            ]);
        }

        return response()->json(['data' => $data]);
    }

    public function getOrders(Request $request)
    {
        $term = trim($request->term);

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        // Guard: term minimal 3 karakter
        if (!$term || strlen($term) < 3) {
            return response()->json([
                'data' => []
            ]);
        }

        $query = DailyQsd::where('is_lunas', true)
            ->where(function ($q) use ($term) {
                $q->where('no_order', 'like', "%{$term}%");
            });

        if ($this->mustScopeOrdersToOwningSales()) {
            $this->applyDailyQsdSalesOwnershipScope($query);
        } elseif (in_array($jabatan, [21, 15, 154, 157])) {
            $query->whereIn('sales_id', $this->salesIdsBawahan());
        }

        $data = $query
            ->selectRaw('
                no_order,
                COUNT(DISTINCT periode) as total_kontrak,
                GROUP_CONCAT(DISTINCT periode SEPARATOR ", ") as daftar_periode,
                MAX(kontrak) as flag_kontrak,
                MAX(nama_perusahaan) as nama_perusahaan,
                MAX(no_quotation) as no_quotation,
                MAX(no_invoice) as no_invoice,
                MAX(biaya_akhir) as biaya_akhir
            ')
            ->groupBy('no_order')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function getOrderByKontrak(Request $request)
    {
        $noOrder = trim((string) $request->input('no_order', ''));
        $kontrak = trim((string) $request->input('kontrak', ''));

        try {
            $this->assertOrderAccessibleByCurrentSales($noOrder, $kontrak !== '' ? $kontrak : null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $order = DailyQsd::where('no_order', $noOrder)
            ->where('periode', $kontrak)
            ->where('is_lunas', true)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Data order/periode tidak ditemukan.'], 404);
        }

        $workflow = app(ClaimFeeExternalSalesWorkflowService::class);
        $excludeId = $request->input('exclude_id') ? (int) $request->exclude_id : null;
        $blocking = $workflow->findBlockingClaim(
            (string) $request->no_order,
            (string) $request->kontrak,
            $excludeId
        );

        if ($blocking) {
            return response()->json([
                'message' => $workflow->duplicateBlockMessage($blocking),
                'blocking_claim_id' => $blocking->id,
                'available' => false,
            ], 422);
        }

        return response()->json($order);
    }

    public function claimedPeriods(Request $request)
    {
        $noOrder = trim((string) $request->input('no_order', ''));
        if ($noOrder === '') {
            return response()->json(['message' => 'No order wajib.'], 422);
        }

        try {
            $this->assertOrderAccessibleByCurrentSales($noOrder, null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $excludeId = $request->input('exclude_id') ? (int) $request->exclude_id : null;
        $workflow = app(ClaimFeeExternalSalesWorkflowService::class);

        return response()->json([
            'blocked' => $workflow->blockedPeriodsForOrder($noOrder, $excludeId),
        ]);
    }

    public function checkClaimDuplicate(Request $request)
    {
        $noOrder = trim((string) $request->input('no_order', ''));
        if ($noOrder === '') {
            return response()->json(['message' => 'No order wajib.'], 422);
        }

        $periode = $request->input('periode');
        if ($periode === '' || $periode === null) {
            $periode = null;
        }

        try {
            $this->assertOrderAccessibleByCurrentSales($noOrder, $periode);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $excludeId = $request->input('exclude_id') ? (int) $request->exclude_id : null;
        $workflow = app(ClaimFeeExternalSalesWorkflowService::class);
        $blocking = $workflow->findBlockingClaim($noOrder, $periode, $excludeId);

        if ($blocking) {
            return response()->json([
                'available' => false,
                'message' => $workflow->duplicateBlockMessage($blocking),
                'blocking_claim_id' => $blocking->id,
            ]);
        }

        return response()->json(['available' => true]);
    }

    public function getOrderDetail(Request $request)
    {
        $noOrder = trim((string) $request->input('no_order', ''));
        if ($noOrder === '') {
            return response()->json(['message' => 'Nomor order wajib diisi.'], 422);
        }

        $orderQuery = DailyQsd::where('is_lunas', true)
            ->where('no_order', $noOrder)
            ->where('kontrak', 'N');
        $this->applyDailyQsdSalesOwnershipScope($orderQuery);
        $order = $orderQuery->first();

        if ($order) {
            return response()->json($order);
        }

        if ($this->mustScopeOrdersToOwningSales()) {
            $existsScopedOut = DailyQsd::where('is_lunas', true)
                ->where('no_order', $noOrder)
                ->where('kontrak', 'N')
                ->exists();
            if ($existsScopedOut) {
                return response()->json([
                    'message' => 'Order tidak tersedia untuk portofolio sales Anda (bukan penanggung jawab pelanggan).',
                ], 403);
            }
        }

        $exists = DailyQsd::where('no_order', $noOrder)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        if (!DailyQsd::where('no_order', $noOrder)->where('is_lunas', true)->exists()) {
            return response()->json(['message' => 'Order belum lunas. Claim fee hanya untuk order yang sudah lunas.'], 422);
        }

        return response()->json([
            'message' => 'Order ini bertipe kontrak. Pilih periode kontrak terlebih dahulu.',
        ], 422);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $workflow = app(ClaimFeeExternalSalesWorkflowService::class);

            if ($workflow->denySupervisorSubmit($this->grade ?? '')) {
                return response()->json(['message' => 'SPV tidak dapat mengajukan Claim Fee External.'], 403);
            }

            $id = $request->id;
            $noInvoice = preg_replace(
                '/\s*\(Lunas\)/i',
                '',
                $this->scalarRequest($request, 'no_invoice')
            );
            $isNew = !$id;

            if ($id) {
                $claim = ClaimFeeExternal::findOrFail($id);
                $workflow->assertFinanceEditAllowed($claim);
                if ($workflow->workflowEnabled() && !$workflow->canEdit($claim)) {
                    return response()->json(['message' => 'Claim tidak dapat diubah setelah disetujui atasan tingkat 1.'], 403);
                }
                $claim->updated_by = $this->karyawan;
                $claim->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $message = 'Claim Fee External Berhasil Diperbarui';
            } else {
                $claim = new ClaimFeeExternal();
                $claim->created_by = $this->karyawan;
                $claim->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $claim->is_active = true;
                $message = 'Claim Fee External Berhasil Tersimpan';
            }

            $wasRejected = !$isNew
                && ($claim->sales_workflow_status ?? '') === ClaimFeeExternalWorkflow::SALES_REJECTED;

            $periode = $this->scalarRequest($request, 'periode', true);
            if ($periode === '' || $periode === null) {
                $periode = null;
            }

            try {
                $this->assertOrderAccessibleByCurrentSales(
                    $this->scalarRequest($request, 'no_order'),
                    $periode
                );
            } catch (\RuntimeException $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 403);
            }

            if ($isNew || $wasRejected) {
                $blocking = $workflow->findBlockingClaim(
                    (string) $request->no_order,
                    $periode,
                    $isNew ? null : (int) $id
                );
                if ($blocking) {
                    DB::rollBack();

                    return response()->json([
                        'message' => $workflow->duplicateBlockMessage($blocking),
                        'blocking_claim_id' => $blocking->id,
                    ], 422);
                }
            }

            $claim->no_order = $this->scalarRequest($request, 'no_order');
            $claim->nama_penerima = $this->scalarRequest($request, 'nama_penerima');
            $bank = $this->scalarRequest($request, 'bank', true);
            $claim->nama_bank = $bank !== '' ? $bank : null;
            $noRek = $this->scalarRequest($request, 'no_rekening', true);
            $claim->no_rekening = $noRek !== '' ? $noRek : null;
            $claim->nama_perusahaan = $this->scalarRequest($request, 'nama_perusahaan');
            $claim->nominal = $request->input('nominal');
            $claim->due_date = $this->scalarRequest($request, 'tanggal_claim');
            $claim->no_quotation = $this->scalarRequest($request, 'no_quotation', true);
            $claim->sales_id = $this->user_id;
            $claim->no_invoice = $noInvoice !== '' ? $noInvoice : null;
            $claim->biaya_akhir = $request->input('biaya_akhir');
            $claim->periode = $periode;
            $claim->metode_pembayaran = $this->scalarRequest($request, 'metode_bayar');
            if (Schema::hasColumn('claim_fee_external', 'keterangan_tambahan')) {
                $ket = trim((string) $request->input('keterangan_tambahan', ''));
                $claim->keterangan_tambahan = $ket !== '' ? mb_substr($ket, 0, 1000) : null;
            }

            if ($isNew || $wasRejected) {
                if ($workflow->workflowEnabled()) {
                    $workflow->initializeWorkflow($claim);
                } else {
                    $claim->status_pembayaran = 'WAITING PROCESS';
                }
            } elseif (!$workflow->workflowEnabled()) {
                $claim->status_pembayaran = 'WAITING PROCESS';
            }

            $claim->save();

            if ($isNew || $wasRejected) {
                // Create baru: cukup toast di UI — tanpa MQTT ke pengaju.
                $workflow->afterSubmit($claim, $isNew);
            } elseif ($workflow->workflowEnabled()) {
                $workflow->syncPendingApprover($claim);
                $claim->save();
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'data' => [
                    'id' => $claim->id,
                    'sales_workflow_status' => $claim->sales_workflow_status ?? null,
                    'can_edit' => $workflow->canEdit($claim),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan pada server',
                'details' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        $workflow = app(ClaimFeeExternalSalesWorkflowService::class);
        if ((int) $claim->sales_id !== (int) $this->user_id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }
        if ($workflow->workflowEnabled() && !$workflow->canEdit($claim)) {
            return response()->json(['message' => 'Claim tidak dapat dihapus setelah disetujui atasan tingkat 1.'], 403);
        }

        $claim->deleted_by = $this->karyawan;
        $claim->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $claim->is_active = false;
        $claim->save();

        return response()->json(['message' => 'Claim Fee External Berhasil Dihapus']);
    }

    public function indexManager(Request $request)
    {
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $query = ClaimFeeExternal::query()->where('status_pembayaran', 'WAITING PROCESS')
            ->where('is_active', true)->where('is_approved_manajer', false);

        if (in_array($jabatan, [15, 157])) {
            $query->whereIn('sales_id', $this->salesIdsBawahan());
        }

        return DataTables::of($query)->make(true);
    }

    public function pengajuanIndex(Request $request)
    {
        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->where('sales_id', $this->user_id);

        if (Schema::hasColumn('claim_fee_external', 'sales_workflow_status')) {
            $query->whereIn('sales_workflow_status', [
                ClaimFeeExternalWorkflow::SALES_SUBMITTED,
                ClaimFeeExternalWorkflow::SALES_PENDING_SPV,
                ClaimFeeExternalWorkflow::SALES_PENDING_MANAGER,
                ClaimFeeExternalWorkflow::SALES_PENDING_SENIOR_MANAGER,
                ClaimFeeExternalWorkflow::SALES_REJECTED,
                ClaimFeeExternalWorkflow::SALES_APPROVED,
            ]);
        } else {
            $query->whereIn('status_pembayaran', ['WAITING PROCESS', 'REJECTED']);
        }

        return $this->makeClaimFeeExternalDataTable($query, $request);
    }

    public function persetujuanIndex(Request $request)
    {
        if (!Schema::hasColumn('claim_fee_external', 'pending_approver_karyawan_id')) {
            return response()->json(['message' => 'Workflow belum dimigrasi.'], 503);
        }

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->where('pending_approver_karyawan_id', $this->user_id);

        return $this->makeClaimFeeExternalDataTable($query, $request);
    }

    public function menungguFinanceIndex(Request $request)
    {
        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->where('sales_workflow_status', ClaimFeeExternalWorkflow::SALES_APPROVED);

        $grade = $this->normalizedGrade();
        if ($grade === 'SENIOR MANAGER') {
            // all data
        } elseif (($employee = $this->karyawanModel()) && AtasanApprovalScope::isAtasanGrade($employee)) {
            $query->whereIn('sales_id', $this->salesIdsBawahan());
        } else {
            $query->where('sales_id', $this->user_id);
        }

        return $this->makeClaimFeeExternalDataTable($query, $request);
    }

    public function show(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim || !$claim->is_active) {
            return response()->json(['message' => 'Claim tidak ditemukan'], 404);
        }

        if (!$this->canViewClaim($claim)) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $workflow = app(ClaimFeeExternalSalesWorkflowService::class);

        return response()->json([
            'claim' => $claim,
            'pipeline' => $workflow->workflowEnabled() ? $workflow->buildPipeline($claim) : [],
            'permissions' => $workflow->permissionsFor($claim, (int) $this->user_id),
        ]);
    }

    public function approveSales(Request $request)
    {
        DB::beginTransaction();
        try {
            $claim = ClaimFeeExternal::findOrFail($request->id);
            $approver = $this->karyawanModel();
            if (!$approver) {
                return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 403);
            }

            app(ClaimFeeExternalSalesWorkflowService::class)->approveSales($claim, $approver);

            DB::commit();

            return response()->json([
                'message' => 'Claim Fee External berhasil disetujui.',
                'data' => [
                    'sales_workflow_status' => $claim->sales_workflow_status,
                    'finance_status' => $claim->finance_status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyetujui claim.'], 500);
        }
    }

    public function rejectSales(Request $request)
    {
        DB::beginTransaction();
        try {
            $claim = ClaimFeeExternal::findOrFail($request->id);
            $approver = $this->karyawanModel();
            if (!$approver) {
                return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 403);
            }

            app(ClaimFeeExternalSalesWorkflowService::class)->rejectSales(
                $claim,
                $approver,
                (string) $request->input('remarks', '')
            );

            DB::commit();

            return response()->json(['message' => 'Claim Fee External ditolak.']);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menolak claim.'], 500);
        }
    }

    public function getTabAccess(Request $request)
    {
        $grade = $this->normalizedGrade();
        $tabs = [];

        if ($grade !== 'SUPERVISOR') {
            $tabs[] = 'pengajuan';
        }
        if (in_array($grade, ['SUPERVISOR', 'MANAGER', 'SENIOR MANAGER'], true)) {
            $tabs[] = 'persetujuan';
        }
        $tabs[] = 'menunggu-finance';
        if ($this->canAccessSalesRekapTab()) {
            $tabs[] = 'rekap';
        }

        // Staff: buka di Pengajuan; SPV ke atas (dan selain Staff): buka di Persetujuan.
        if ($grade === 'STAFF') {
            $default = in_array('pengajuan', $tabs, true) ? 'pengajuan' : ($tabs[0] ?? 'pengajuan');
        } elseif (in_array('persetujuan', $tabs, true)) {
            $default = 'persetujuan';
        } else {
            $default = $tabs[0] ?? 'pengajuan';
        }

        return response()->json([
            'tabs' => $tabs,
            'default_tab' => $default,
            'grade' => $grade,
        ]);
    }

    public function rekapIndex(Request $request)
    {
        if (!$this->canAccessSalesRekapTab()) {
            return response()->json(['message' => 'Tab rekap tidak tersedia.'], 403);
        }

        $query = ClaimFeeExternal::query()->where('is_active', true);
        $this->applyClaimFeeExternalRekapScope($query);
        $this->applySalesRekapHierarchyScope($query);

        return $this->makeClaimFeeExternalDataTable($query, $request);
    }

    /**
     * Staff sales: order/claim hanya untuk portofolio sendiri (via master_pelanggan penanggung jawab).
     */
    private function mustScopeOrdersToOwningSales(): bool
    {
        if ($this->normalizedGrade() === 'STAFF') {
            return true;
        }

        $jabatan = (int) (request()->attributes->get('user')->karyawan->id_jabatan ?? 0);

        return in_array($jabatan, [24, 86, 148], true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    private function applyDailyQsdSalesOwnershipScope($query): void
    {
        if (!$this->mustScopeOrdersToOwningSales()) {
            return;
        }

        $userId = (int) $this->user_id;
        $salesName = trim((string) ($this->karyawan ?? ''));

        $pelangganCol = $this->dailyQsdPelangganIdColumn();

        $query->where(function ($q) use ($userId, $salesName, $pelangganCol) {
            $q->where('sales_id', $userId);

            if ($pelangganCol === null) {
                return;
            }

            $q->orWhere(function ($q2) use ($userId, $salesName, $pelangganCol) {
                $q2->whereNotNull($pelangganCol)
                    ->where($pelangganCol, '!=', '')
                    ->whereExists(function ($sub) use ($userId, $salesName, $pelangganCol) {
                        $sub->select(DB::raw('1'))
                            ->from('master_pelanggan as mp')
                            ->whereRaw(
                                'mp.id_pelanggan COLLATE utf8mb4_unicode_ci = daily_qsd.'
                                . $pelangganCol
                                . ' COLLATE utf8mb4_unicode_ci'
                            );
                        if (Schema::hasColumn('master_pelanggan', 'is_active')) {
                            $sub->where('mp.is_active', true);
                        }
                        $sub->where(function ($w) use ($userId, $salesName) {
                            $w->where('mp.sales_id', $userId);
                            if ($salesName !== '') {
                                $w->orWhere('mp.sales_penanggung_jawab', $salesName);
                            }
                        });
                    });
            });
        });
    }

    /** Kolom pelanggan di daily_qsd (produksi: pelanggan_ID). */
    private function dailyQsdPelangganIdColumn(): ?string
    {
        if (Schema::hasColumn('daily_qsd', 'pelanggan_ID')) {
            return 'pelanggan_ID';
        }
        if (Schema::hasColumn('daily_qsd', 'id_pelanggan')) {
            return 'id_pelanggan';
        }

        return null;
    }

    private function assertOrderAccessibleByCurrentSales(string $noOrder, ?string $periode = null): void
    {
        if (!$this->mustScopeOrdersToOwningSales()) {
            return;
        }

        $noOrder = trim($noOrder);
        if ($noOrder === '') {
            throw new \InvalidArgumentException('Nomor order wajib.');
        }

        $q = DailyQsd::query()
            ->where('no_order', $noOrder)
            ->where('is_lunas', true);

        if ($periode !== null && $periode !== '') {
            $q->where('periode', $periode);
        }

        $this->applyDailyQsdSalesOwnershipScope($q);

        if (!$q->exists()) {
            throw new \RuntimeException(
                'Order ini bukan milik portofolio sales Anda (penanggung jawab di master pelanggan).'
            );
        }
    }

    /** @return list<int> */
    /**
     * FormData kadang mengirim nilai ganda sebagai array → hindari "Array to string conversion" saat save.
     */
    private function scalarRequest(Request $request, string $key, bool $nullable = false): ?string
    {
        $value = $request->input($key);
        if (is_array($value)) {
            $flat = [];
            array_walk_recursive($value, static function ($item) use (&$flat) {
                if ($item !== null && $item !== '') {
                    $flat[] = $item;
                }
            });
            $value = $flat[0] ?? '';
        }
        if ($value === null || $value === '') {
            return $nullable ? '' : '';
        }

        return trim((string) $value);
    }

    private function salesIdsBawahan(): array
    {
        return GetBawahan::where('id', $this->user_id)
            ->get()
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->toArray();
    }

    private function salesHierarchyService(): SalesTeamHierarchyService
    {
        return app(SalesTeamHierarchyService::class);
    }

    /**
     * Tab rekap di menu Sales: SPV/Manager/SM divisi sales, atau siapa pun di luar divisi sales (view all).
     */
    private function canAccessSalesRekapTab(): bool
    {
        $employee = $this->karyawanModel();
        if (!$employee) {
            return false;
        }

        if ($this->salesHierarchyService()->viewerHasOrgWideAccess($employee)) {
            return true;
        }

        return in_array($this->normalizedGrade(), ['SUPERVISOR', 'MANAGER', 'SENIOR MANAGER'], true);
    }

    /**
     * Scope data rekap (menu Sales): luar divisi sales = semua data; dalam sales = hierarki (sama pola menunggu finance).
     */
    private function applySalesRekapHierarchyScope($query): void
    {
        $employee = $this->karyawanModel();
        if (!$employee || $this->salesHierarchyService()->viewerHasOrgWideAccess($employee)) {
            return;
        }

        $grade = $this->normalizedGrade();
        if ($grade === 'SENIOR MANAGER') {
            return;
        }

        if (in_array($grade, ['SUPERVISOR', 'MANAGER'], true) && AtasanApprovalScope::isAtasanGrade($employee)) {
            $query->whereIn('sales_id', $this->salesIdsBawahan());

            return;
        }

        $query->where('sales_id', $this->user_id);
    }

    private function normalizedGrade(): string
    {
        $g = strtoupper(str_replace('_', ' ', trim((string) ($this->grade ?? ''))));
        if ($g === 'SPV') {
            return 'SUPERVISOR';
        }

        return $g;
    }

    private function karyawanModel()
    {
        return \App\Models\MasterKaryawan::query()
            ->where('id', $this->user_id)
            ->where('is_active', true)
            ->first();
    }

    private function canViewClaim(ClaimFeeExternal $claim): bool
    {
        if ((int) $claim->sales_id === (int) $this->user_id) {
            return true;
        }

        $employee = $this->karyawanModel();
        if ($employee && app(SalesTeamHierarchyService::class)->viewerHasOrgWideAccess($employee)) {
            return true;
        }

        $grade = $this->normalizedGrade();
        if ($grade === 'SENIOR MANAGER') {
            return true;
        }

        if ($grade === 'SUPERVISOR' || $grade === 'MANAGER') {
            if (Schema::hasColumn('claim_fee_external', 'pending_approver_karyawan_id')
                && (int) $claim->pending_approver_karyawan_id === (int) $this->user_id) {
                return true;
            }

            return in_array((int) $claim->sales_id, $this->salesIdsBawahan(), true);
        }

        return false;
    }

    private function makeClaimFeeExternalDataTable($query, Request $request)
    {
        return DataTables::of($query)
            ->filter(function ($q) use ($request) {
                $this->applyDataTablesColumnFilters($q, $request);
            })
            ->withQuery('sum_nominal', static function ($q) {
                return (float) $q->sum('nominal');
            })
            ->withQuery('sum_potongan', static function ($q) {
                return (float) $q->sum(DB::raw('COALESCE(potongan, 0)'));
            })
            ->withQuery('sum_nominal_bayar', static function ($q) {
                return (float) $q->sum(DB::raw('COALESCE(nominal_bayar, 0)'));
            })
            ->make(true);
    }

    private function applyDataTablesColumnFilters($query, Request $request): void
    {
        foreach ($request->columns ?? [] as $column) {
            $value = trim((string) ($column['search']['value'] ?? ''));
            $field = $column['data'] ?? null;

            if ($value === '' || $field === null || $field === '') {
                continue;
            }

            switch ($field) {
                case 'no_order':
                case 'nama_penerima':
                case 'keterangan_tambahan':
                case 'nama_bank':
                case 'metode_pembayaran':
                case 'nama_perusahaan':
                case 'created_by':
                case 'sales_workflow_status':
                case 'finance_status':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
                case 'nominal':
                case 'potongan':
                case 'nominal_bayar':
                    if (is_numeric(str_replace([',', '.'], '', $value))) {
                        $digits = preg_replace('/\D/', '', $value);
                        if ($digits !== '') {
                            $query->where($field, 'like', '%' . $digits . '%');
                        }
                    } else {
                        $query->where($field, 'like', '%' . $value . '%');
                    }
                    break;
                case 'due_date':
                case 'tanggal_pembayaran':
                    $this->applyDateFilter($query, $field, $value);
                    break;
                case 'no_quotation':
                case 'no_invoice':
                case 'periode':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
            }
        }
    }

    private function applyDateFilter($query, string $column, string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $query->whereDate($column, $value);
        } elseif (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $query->whereYear($column, substr($value, 0, 4))
                ->whereMonth($column, substr($value, 5, 2));
        } elseif (preg_match('/^\d{4}$/', $value)) {
            $query->whereYear($column, $value);
        }
    }
}