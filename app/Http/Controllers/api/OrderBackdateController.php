<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderBackdatePackage;
use App\Models\OrderBackdateStep;
use App\Models\OrderHeader;
use App\Services\OrderBackdate\OrderBackdateDocumentRegenerator;
use App\Services\OrderBackdate\OrderBackdateDomainProfile;
use App\Services\OrderBackdate\OrderBackdateEligibilityService;
use App\Services\OrderBackdate\OrderBackdateFieldResolver;
use App\Services\OrderBackdate\OrderBackdateNotEligibleException;
use App\Services\OrderBackdate\OrderBackdateStepApplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class OrderBackdateController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderBackdatePackage::query()
            ->where('is_active', true)
            ->orderByDesc('id');

        return DataTables::of($query)
            ->addColumn('step_summary', function (OrderBackdatePackage $row) {
                $saved = OrderBackdateStep::where('package_id', $row->id)
                    ->where('step_status', 'saved')
                    ->count();

                return $saved . ' / ' . count(OrderBackdatePackage::STEP_KEYS);
            })
            ->make(true);
    }

    public function lookupOrder(Request $request)
    {
        $noOrder = trim((string) $request->no_order);
        if ($noOrder === '') {
            return response()->json(['message' => 'No order wajib diisi'], 422);
        }

        $order = OrderHeader::with(['quotationNonKontrak', 'quotationKontrakH'])
            ->where('no_order', $noOrder)
            ->where('is_active', true)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        $activePackage = OrderBackdatePackage::where('no_order', $noOrder)
            ->where('is_active', true)
            ->whereIn('package_status', ['draft', 'partial', 'completed'])
            ->orderByDesc('id')
            ->first();

        /** @var OrderBackdateEligibilityService $eligibility */
        $eligibility = app(OrderBackdateEligibilityService::class);
        $gate = $eligibility->assess($noOrder);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'no_order' => $order->no_order,
                'no_document' => $order->no_document,
                'nama_perusahaan' => $order->nama_perusahaan,
                'status_sampling' => $order->status_sampling,
                'sub_kategori' => $order->sub_kategori,
                'tanggal_penawaran' => $order->tanggal_penawaran,
                'tanggal_order' => $order->tanggal_order,
            ],
            'manifest' => [
                'air' => OrderBackdateDomainProfile::isAir($order),
                'emisi' => OrderBackdateDomainProfile::isEmisi($order),
            ],
            'active_package_id' => $activePackage ? $activePackage->id : null,
            'eligibility' => $gate,
        ]);
    }

    public function createPackage(Request $request)
    {
        $noOrder = trim((string) $request->no_order);
        $reason = trim((string) ($request->reason ?? ''));

        if ($noOrder === '') {
            return response()->json(['message' => 'No order wajib diisi'], 422);
        }

        $order = OrderHeader::where('no_order', $noOrder)->where('is_active', true)->first();
        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        $existing = OrderBackdatePackage::where('no_order', $noOrder)
            ->where('is_active', true)
            ->whereIn('package_status', ['draft', 'partial'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Paket backdate masih aktif untuk order ini',
                'package_id' => $existing->id,
            ], 409);
        }

        try {
            /** @var OrderBackdateEligibilityService $eligibility */
            $eligibility = app(OrderBackdateEligibilityService::class);
            $eligibility->assertEligible($noOrder);
        } catch (OrderBackdateNotEligibleException $e) {
            return $this->notEligibleResponse($e);
        }

        return DB::transaction(function () use ($order, $reason) {
            $now = Carbon::now();
            $package = OrderBackdatePackage::create([
                'id_order_header' => $order->id,
                'no_order' => $order->no_order,
                'no_document' => $order->no_document,
                'status_sampling' => $order->status_sampling,
                'nama_perusahaan' => $order->nama_perusahaan,
                'package_status' => 'draft',
                'is_active' => true,
                'view_mode' => OrderBackdatePackage::VIEW_DISPLAY,
                'reason' => $reason ?: null,
                'created_by' => $this->user_id,
                'created_by_name' => $this->nama_lengkap,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (OrderBackdatePackage::STEP_KEYS as $stepKey) {
                OrderBackdateStep::create([
                    'package_id' => $package->id,
                    'step_key' => $stepKey,
                    'step_status' => 'pending',
                    'document_status' => 'not_required',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /** @var OrderBackdateStepApplier $applier */
            $applier = app(OrderBackdateStepApplier::class);
            $applier->log($package, 'create_package', null, ['no_order' => $order->no_order], $this->user_id, $this->nama_lengkap);

            return response()->json([
                'message' => 'Paket backdate dibuat',
                'package_id' => $package->id,
            ]);
        });
    }

    public function showPackage(Request $request)
    {
        $package = $this->findPackage((int) $request->package_id);
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $package->load('steps');

        $stepsMeta = [];
        foreach (OrderBackdatePackage::STEP_KEYS as $key) {
            $step = $package->steps->firstWhere('step_key', $key);
            $stepsMeta[$key] = [
                'step_status' => $step ? $step->step_status : 'pending',
                'document_status' => $step ? $step->document_status : 'not_required',
                'saved_at' => $step ? $step->saved_at : null,
            ];
        }

        return response()->json([
            'package' => $package,
            'steps' => $stepsMeta,
            'step_labels' => $this->stepLabels(),
        ]);
    }

    public function previewStep(Request $request)
    {
        $package = $this->findPackage((int) $request->package_id);
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $stepKey = (string) $request->step_key;
        if (!in_array($stepKey, OrderBackdatePackage::STEP_KEYS, true)) {
            return response()->json(['message' => 'Step tidak valid'], 422);
        }

        $order = OrderHeader::find($package->id_order_header);
        if (!$order) {
            return response()->json(['message' => 'Order header tidak ditemukan'], 404);
        }

        /** @var OrderBackdateFieldResolver $resolver */
        $resolver = app(OrderBackdateFieldResolver::class);
        $fields = $resolver->resolveStep($stepKey, $order);

        $step = OrderBackdateStep::where('package_id', $package->id)
            ->where('step_key', $stepKey)
            ->first();

        return response()->json([
            'step_key' => $stepKey,
            'fields' => $fields,
            'step_status' => $step ? $step->step_status : 'pending',
            'applied_values' => $step ? $step->applied_values : null,
        ]);
    }

    public function saveStep(Request $request)
    {
        $package = $this->findPackage((int) $request->package_id);
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $stepKey = (string) $request->step_key;
        if (!in_array($stepKey, OrderBackdatePackage::STEP_KEYS, true)) {
            return response()->json(['message' => 'Step tidak valid'], 422);
        }

        $values = $request->values;
        if (!is_array($values) || $values === []) {
            return response()->json(['message' => 'Nilai tanggal baru wajib diisi'], 422);
        }

        try {
            /** @var OrderBackdateEligibilityService $eligibility */
            $eligibility = app(OrderBackdateEligibilityService::class);
            $eligibility->assertEligible($package->no_order);
        } catch (OrderBackdateNotEligibleException $e) {
            return $this->notEligibleResponse($e);
        }

        $order = OrderHeader::find($package->id_order_header);
        if (!$order) {
            return response()->json(['message' => 'Order header tidak ditemukan'], 404);
        }

        /** @var OrderBackdateFieldResolver $resolver */
        $resolver = app(OrderBackdateFieldResolver::class);
        /** @var OrderBackdateStepApplier $applier */
        $applier = app(OrderBackdateStepApplier::class);

        $fields = $resolver->resolveStep($stepKey, $order);
        if ($fields === []) {
            return response()->json(['message' => 'Tidak ada field yang bisa diubah untuk step ini (cek tipe order)'], 422);
        }

        $step = OrderBackdateStep::where('package_id', $package->id)
            ->where('step_key', $stepKey)
            ->first();

        return DB::transaction(function () use ($package, $step, $stepKey, $fields, $values, $applier, $resolver) {
            $now = Carbon::now();

            if ($step->step_status !== 'saved' || empty($step->snapshot_original)) {
                $snapshot = [];
                foreach ($fields as $field) {
                    $snapshot[] = $field;
                }
                $step->snapshot_original = $snapshot;
            }

            if ($package->view_mode === OrderBackdatePackage::VIEW_AUDIT) {
                return response()->json([
                    'message' => 'Sedang mode audit (tampilan asli). Matikan toggle audit dulu sebelum menyimpan backdate.',
                ], 422);
            }

            try {
                $applied = $applier->applyValues($fields, $values);
            } catch (\Throwable $e) {
                $step->step_status = 'failed';
                $step->last_error = $e->getMessage();
                $step->updated_at = $now;
                $step->save();

                return response()->json(['message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
            }

            $step->applied_values = $applied;
            $step->last_payload = $values;
            $step->step_status = 'saved';
            $step->document_status = $applier->documentStatusForStep($stepKey);
            $step->last_error = null;
            $step->saved_by = $this->user_id;
            $step->saved_at = $now;
            $step->updated_at = $now;
            $step->save();

            $package->updated_by = $this->user_id;
            $applier->syncPackageStatus($package);
            $applier->log($package, 'save_step', $stepKey, ['applied' => $applied], $this->user_id, $this->nama_lengkap);

            /** @var OrderBackdateDocumentRegenerator $regenerator */
            $regenerator = app(OrderBackdateDocumentRegenerator::class);
            $regenerator->queueAfterSave($package, $stepKey);
            $step->refresh();

            return response()->json([
                'message' => 'Step ' . ($this->stepLabels()[$stepKey] ?? $stepKey) . ' tersimpan',
                'package_status' => $package->fresh()->package_status,
                'document_status' => $step->document_status,
            ]);
        });
    }

    public function toggleViewMode(Request $request)
    {
        $package = $this->findPackage((int) $request->package_id);
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $target = (string) ($request->view_mode ?? '');
        if (!in_array($target, [OrderBackdatePackage::VIEW_DISPLAY, OrderBackdatePackage::VIEW_AUDIT], true)) {
            $target = $package->view_mode === OrderBackdatePackage::VIEW_DISPLAY
                ? OrderBackdatePackage::VIEW_AUDIT
                : OrderBackdatePackage::VIEW_DISPLAY;
        }

        /** @var OrderBackdateStepApplier $applier */
        $applier = app(OrderBackdateStepApplier::class);
        $order = OrderHeader::find($package->id_order_header);
        if (!$order) {
            return response()->json(['message' => 'Order header tidak ditemukan'], 404);
        }

        /** @var OrderBackdateFieldResolver $resolver */
        $resolver = app(OrderBackdateFieldResolver::class);

        $steps = OrderBackdateStep::where('package_id', $package->id)
            ->where('step_status', 'saved')
            ->get();

        return DB::transaction(function () use ($package, $target, $steps, $applier, $resolver, $order) {
            foreach (OrderBackdatePackage::STEP_KEYS as $stepKey) {
                $step = $steps->firstWhere('step_key', $stepKey);
                if (!$step || empty($step->snapshot_original)) {
                    continue;
                }

                $liveFields = $resolver->resolveStep($stepKey, $order->fresh());

                if ($target === OrderBackdatePackage::VIEW_AUDIT) {
                    $applier->restoreFromSnapshot($step->snapshot_original);
                } else {
                    $applied = is_array($step->applied_values) ? $step->applied_values : [];
                    if ($applied !== []) {
                        $applier->applyValues($liveFields, $applied);
                    }
                }
            }

            $package->view_mode = $target;
            $package->updated_at = Carbon::now();
            $package->save();

            $applier->log($package, 'toggle_view_mode', null, ['view_mode' => $target], $this->user_id, $this->nama_lengkap);

            return response()->json([
                'message' => $target === OrderBackdatePackage::VIEW_AUDIT
                    ? 'Data ditampilkan ke versi asli (audit)'
                    : 'Data ditampilkan ke versi backdate',
                'view_mode' => $target,
            ]);
        });
    }

    public function markDocumentRegenerated(Request $request)
    {
        $package = $this->findPackage((int) $request->package_id);
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $stepKey = (string) $request->step_key;
        $step = OrderBackdateStep::where('package_id', $package->id)
            ->where('step_key', $stepKey)
            ->first();

        if (!$step) {
            return response()->json(['message' => 'Step tidak ditemukan'], 404);
        }

        $step->document_status = 'done';
        $step->updated_at = Carbon::now();
        $step->save();

        /** @var OrderBackdateStepApplier $applier */
        $applier = app(OrderBackdateStepApplier::class);
        $applier->log($package, 'document_regenerated', $stepKey, [], $this->user_id, $this->nama_lengkap);

        return response()->json(['message' => 'Status dokumen diperbarui']);
    }

    private function findPackage(int $id): ?OrderBackdatePackage
    {
        if ($id <= 0) {
            return null;
        }

        return OrderBackdatePackage::where('id', $id)->where('is_active', true)->first();
    }

    private function stepLabels(): array
    {
        return [
            'quotation' => 'Penawaran',
            'order' => 'Order',
            'schedule' => 'Jadwal',
            'field_data' => 'Data lapangan',
            'sampling_doc' => 'Dokumen sampling',
            'lhp' => 'LHP',
            'qr' => 'QR dokumen',
        ];
    }

    private function notEligibleResponse(OrderBackdateNotEligibleException $e)
    {
        return response()->json([
            'message' => $e->getMessage(),
            'eligibility' => $e->eligibility,
        ], 422);
    }
}
