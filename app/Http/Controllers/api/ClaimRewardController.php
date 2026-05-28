<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ClaimRewardDetail;
use App\Models\ClaimRewardHeader;
use App\Models\KatalogReward;
use App\Services\Notification;
use App\Services\PortalNotificationService;
use App\Services\PpiNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class ClaimRewardController extends Controller
{
    protected const STATUS_PENDING = 'pending';
    protected const STATUS_PROCESSED = 'shipping';
    protected const STATUS_APPROVED = 'approved';
    protected const STATUS_REJECTED = 'rejected';
    protected const STATUS_COMPLETED = 'completed';
    protected const STATUS_CANCELLED = 'cancelled';

    public function index(Request $request)
    {
        $data = ClaimRewardHeader::query()
            ->with(['details' => function ($query) {
                $query->with('reward')->where('is_active', true)->orderBy('id');
            }])
            ->when($this->hasHeaderColumn('is_active'), fn ($query) => $query->where('is_active', true))
            ->orderByDesc('id');

        if ($request->has('draw')) {
            return Datatables::of($data)
                ->addColumn('item_count', fn ($claim) => $claim->details->count())
                ->addColumn('reward_title', fn ($claim) => $this->buildRewardTitleSummary($claim))
                ->addColumn('reward_category', fn ($claim) => $this->buildRewardCategorySummary($claim))
                ->addColumn('variant_name', fn ($claim) => $this->buildVariantSummary($claim))
                ->addColumn('qty', fn ($claim) => (int) ($claim->total_qty ?? $claim->details->sum('qty')))
                ->addColumn('total_points', fn ($claim) => (int) ($claim->total_points ?? $claim->details->sum('total_points')))
                ->addColumn('status_label', fn ($claim) => ucfirst((string) $claim->status))
                ->with('summary', $this->buildSummary(clone $data))
                ->make(true);
        }

        return response()->json([
            'data' => $data->get()->map(fn ($claim) => $this->transformClaim($claim)),
            'status' => 200,
            'message' => 'Berhasil mendapatkan data claim reward',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->storeRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $claim = DB::connection('portal_customer')->transaction(function () use ($request) {
            $items = collect($request->items ?? [])
                ->map(function ($item) {
                    return is_array($item) ? $item : (array) $item;
                })
                ->values();

            $header = ClaimRewardHeader::create([
                'claim_code' => $request->claim_code ?: $this->generateClaimCode(),
                'customer_id' => $request->customer_id,
                'customer_code' => $request->customer_code,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'address' => $request->address,
                'customer_note' => $request->customer_note,
                'internal_note' => $request->internal_note,
                'status' => self::STATUS_PENDING,
                'meta' => [
                    'source' => $request->source ?: 'portal_customer',
                ],
                'created_by' => $request->created_by ?: ($this->karyawan ?? null),
                'updated_by' => $request->created_by ?: ($this->karyawan ?? null),
                'is_active' => true,
            ]);

            $totalQty = 0;
            $totalPoints = 0;

            foreach ($items as $item) {
                $reward = KatalogReward::findOrFail($item['reward_id']);
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $unitPoints = (int) $reward->price;
                $detailTotalPoints = $unitPoints * $qty;

                ClaimRewardDetail::create([
                    'header_id' => $header->id,
                    'reward_id' => $reward->id,
                    'reward_title' => $reward->title,
                    'reward_category' => $reward->category,
                    'variant_name' => $item['variant_name'] ?? null,
                    'qty' => $qty,
                    'unit_points' => $unitPoints,
                    'total_points' => $detailTotalPoints,
                    'reward_snapshot' => [
                        'title' => $reward->title,
                        'category' => $reward->category,
                        'price' => (int) $reward->price,
                        'purchase_price' => (int) ($reward->purchase_price ?? 0),
                        'gallery' => $reward->gallery ?? [],
                        'variants' => $reward->variants ?? [],
                    ],
                    'created_by' => $request->created_by ?: ($this->karyawan ?? null),
                    'updated_by' => $request->created_by ?: ($this->karyawan ?? null),
                    'is_active' => true,
                ]);

                $totalQty += $qty;
                $totalPoints += $detailTotalPoints;
            }

            $header->total_qty = $totalQty;
            $header->total_points = $totalPoints;
            $header->save();

            return $header->fresh(['details.reward']);
        });

        $this->notifyInternalPendingClaim($claim);

        return response()->json([
            'data' => $this->transformClaim($claim),
            'status' => 200,
            'message' => 'Claim reward berhasil dibuat',
        ]);
    }

    public function process(Request $request)
    {
        return $this->handleStatusUpdate($request, [self::STATUS_APPROVED], self::STATUS_PROCESSED, function ($claim, $request) {
            $claim->processed_by = $this->karyawan ?? $request->processed_by ?? null;
            $claim->processed_at = Carbon::now();
            $this->applyShippingData($claim, $request);
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
        }, 'Claim reward sedang diproses.', 'claim_reward_process');
    }

    public function approve(Request $request)
    {
        return $this->handleStatusUpdate($request, [self::STATUS_PENDING], self::STATUS_APPROVED, function ($claim, $request) {
            $claim->approved_by = $this->karyawan ?? $request->approved_by ?? null;
            $claim->approved_at = Carbon::now();
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
            $this->incrementRewardSold($claim);
        }, 'Claim reward disetujui dan siap ditindaklanjuti.', 'claim_reward_approved');
    }

    public function reject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'note' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        return $this->handleStatusUpdate($request, [self::STATUS_PENDING, self::STATUS_PROCESSED], self::STATUS_REJECTED, function ($claim, $request) {
            $claim->rejected_by = $this->karyawan ?? $request->rejected_by ?? null;
            $claim->rejected_at = Carbon::now();
            $claim->reject_reason = $request->note;
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
        }, 'Claim reward ditolak.');
    }

    public function complete(Request $request)
    {
        return $this->handleStatusUpdate($request, [self::STATUS_PROCESSED], self::STATUS_COMPLETED, function ($claim, $request) {
            $claim->completed_by = $this->karyawan ?? $request->completed_by ?? null;
            $claim->completed_at = Carbon::now();
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
        }, 'Claim reward telah selesai diproses.');
    }

    public function getShippingCouriers()
    {
        $data = ClaimRewardHeader::query()
            ->select('shipping_courier')
            ->whereNotNull('shipping_courier')
            ->where('shipping_courier', '<>', '')
            ->when($this->hasHeaderColumn('shipping_method'), function ($query) {
                $query->where('shipping_method', 'expedition');
            })
            ->distinct()
            ->orderBy('shipping_courier')
            ->pluck('shipping_courier')
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'status' => 200,
            'message' => 'Berhasil mendapatkan data kurir',
        ]);
    }

    public function cancel(Request $request)
    {
        return $this->handleStatusUpdate($request, [self::STATUS_PENDING, self::STATUS_PROCESSED, self::STATUS_APPROVED], self::STATUS_CANCELLED, function ($claim, $request) {
            $claim->cancelled_by = $this->karyawan ?? $request->cancelled_by ?? null;
            $claim->cancelled_at = Carbon::now();
            $claim->cancel_reason = $request->note;
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
        }, 'Claim reward dibatalkan.');
    }

    public function getPendingNotifications()
    {
        $data = ClaimRewardHeader::query()
            ->with(['details' => function ($query) {
                $query->with('reward')->where('is_active', true)->orderBy('id');
            }])
            ->where('status', self::STATUS_PENDING)
            ->when($this->hasHeaderColumn('is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $data->map(fn ($claim) => $this->transformClaim($claim)),
        ]);
    }

    protected function storeRules(): array
    {
        return [
            'claim_code' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['required'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'customer_note' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:50'],
            'created_by' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.reward_id' => ['required', 'integer'],
            'items.*.variant_name' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:1'],
        ];
    }

    protected function handleStatusUpdate(Request $request, array $allowedStatuses, string $nextStatus, callable $callback, string $message, ?string $notificationFor = null)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'note' => ['nullable', 'string'],
            'shipping_reference' => ['nullable', 'string', 'max:255'],
            'shipping_courier' => ['nullable', 'string', 'max:255'],
            'shipping_method' => ['nullable', 'in:internal,expedition'],
            'estimated_received_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $claim = DB::connection('portal_customer')->transaction(function () use ($request, $allowedStatuses, $nextStatus, $callback) {
            $claim = ClaimRewardHeader::with(['details' => function ($query) {
                $query->with('reward')->where('is_active', true)->orderBy('id');
            }])->lockForUpdate()->find($request->id);

            if (!$claim) {
                return response()->json([
                    'message' => 'Claim reward tidak ditemukan',
                    'status' => 404,
                ], 404);
            }

            if (!in_array($claim->status, $allowedStatuses, true)) {
                return response()->json([
                    'message' => 'Status claim reward tidak bisa diproses dengan aksi ini',
                    'status' => 422,
                ], 422);
            }

            $callback($claim, $request);
            $claim->status = $nextStatus;
            $claim->updated_by = $this->karyawan ?? $request->updated_by ?? null;
            $claim->save();

            return $claim->fresh(['details.reward']);
        });

        if ($claim instanceof \Illuminate\Http\JsonResponse) {
            return $claim;
        }

        if ($notificationFor) {
            $this->sendClaimRewardNotification($claim, $notificationFor);
        }

        return response()->json([
            'data' => $this->transformClaim($claim),
            'status' => 200,
            'message' => $message,
        ]);
    }

    protected function notifyInternalPendingClaim(ClaimRewardHeader $claim): void
    {
        $targets = collect(explode(',', (string) env('CLAIM_REWARD_NOTIFY_USER_IDS', '')))
            ->map(fn ($item) => (int) trim($item))
            ->filter()
            ->values()
            ->all();

        if (count($targets) === 0) {
            return;
        }

        try {
            Notification::whereIn('id', $targets)
                ->title('Claim Reward Baru')
                ->message("Ada claim reward baru {$claim->claim_code} dari {$claim->customer_name}.")
                ->url('/portal-intilab/poin-member/claim-reward')
                ->send();
        } catch (\Throwable $exception) {
        }
    }

    protected function sendClaimRewardNotification(ClaimRewardHeader $claim, string $for): void
    {
        if (empty($claim->user_id)) {
            return;
        }

        app(PortalNotificationService::class)->send($claim->user_id, $for, [
            'order_no' => $claim->no_pesanan,
            'no_pesanan' => $claim->no_pesanan,
            'customer_name' => $claim->name,
            'name' => $claim->name,
            'total_points' => (int) ($claim->total_points ?? 0),
            'data' => [
                'claim_id' => $claim->id,
                'status' => $claim->status,
                'shipping_method' => $claim->shipping_method,
                'shipping_courier' => $claim->shipping_courier,
                'shipping_reference' => $claim->shipping_reference,
            ],
        ]);
    }

    protected function incrementRewardSold(ClaimRewardHeader $claim): void
    {
        $details = $claim->relationLoaded('details')
            ? $claim->details
            : $claim->details()->where('is_active', true)->get();

        $details
            ->groupBy('reward_id')
            ->each(function ($items, $rewardId) {
                $qty = (int) $items->sum('qty');

                if ($rewardId && $qty > 0) {
                    KatalogReward::where('id', $rewardId)->increment('sold', $qty);
                }
            });
    }

    protected function buildSummary($query): array
    {
        $rows = $query
            ->reorder()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $rows->sum(),
            'pending' => (int) ($rows[self::STATUS_PENDING] ?? 0),
            'approved' => (int) ($rows[self::STATUS_APPROVED] ?? 0),
            'shipping' => (int) ($rows[self::STATUS_PROCESSED] ?? 0),
            'completed' => (int) ($rows[self::STATUS_COMPLETED] ?? 0),
        ];
    }

    protected function transformClaim(ClaimRewardHeader $claim): array
    {
        $details = $claim->relationLoaded('details')
            ? $claim->details
            : $claim->details()->with('reward')->where('is_active', true)->orderBy('id')->get();

        $firstDetail = $details->first();

        return [
            'id' => $claim->id,
            'noPesanan' => $claim->no_pesanan ?? $claim->claim_code,
            'no_pesanan' => $claim->no_pesanan ?? $claim->claim_code,
            'claimCode' => $claim->claim_code,
            'claim_code' => $claim->claim_code,
            'customerId' => $claim->customer_id,
            'customer_id' => $claim->customer_id,
            'customerCode' => $claim->customer_code,
            'customer_code' => $claim->customer_code,
            'name' => $claim->name ?? $claim->customer_name,
            'customerName' => $claim->customer_name ?? $claim->name,
            'customer_name' => $claim->customer_name ?? $claim->name,
            'email' => $claim->email ?? $claim->customer_email,
            'customerEmail' => $claim->customer_email ?? $claim->email,
            'customer_email' => $claim->customer_email ?? $claim->email,
            'phone' => $claim->phone ?? $claim->customer_phone,
            'customer_phone' => $claim->customer_phone ?? $claim->phone,
            'itemCount' => $details->count(),
            'item_count' => $details->count(),
            'rewardTitle' => $this->buildRewardTitleSummary($claim, $details),
            'reward_title' => $this->buildRewardTitleSummary($claim, $details),
            'rewardCategory' => $this->buildRewardCategorySummary($claim, $details),
            'reward_category' => $this->buildRewardCategorySummary($claim, $details),
            'variantName' => $this->buildVariantSummary($claim, $details),
            'variant_name' => $this->buildVariantSummary($claim, $details),
            'qty' => (int) ($claim->total_qty ?? $details->sum('qty')),
            'totalQty' => (int) ($claim->total_qty ?? $details->sum('qty')),
            'total_qty' => (int) ($claim->total_qty ?? $details->sum('qty')),
            'unitPoints' => (int) ($firstDetail->unit_points ?? 0),
            'unit_points' => (int) ($firstDetail->unit_points ?? 0),
            'totalPoints' => (int) ($claim->total_points ?? $details->sum('total_points')),
            'total_points' => (int) ($claim->total_points ?? $details->sum('total_points')),
            'status' => $claim->status,
            'statusLabel' => ucfirst((string) $claim->status),
            'status_label' => ucfirst((string) $claim->status),
            'address' => $claim->address,
            'user_note' => $claim->user_note ?? $claim->customer_note,
            'customerNote' => $claim->customer_note ?? $claim->user_note,
            'customer_note' => $claim->customer_note ?? $claim->user_note,
            'internalNote' => $claim->internal_note,
            'internal_note' => $claim->internal_note,
            'shippingCourier' => $claim->shipping_courier,
            'shipping_courier' => $claim->shipping_courier,
            'shippingReference' => $claim->shipping_reference,
            'shipping_reference' => $claim->shipping_reference,
            'shippingMethod' => $claim->shipping_method,
            'shipping_method' => $claim->shipping_method,
            'estimatedReceivedDate' => $claim->meta['estimated_received_date'] ?? null,
            'estimated_received_date' => $claim->meta['estimated_received_date'] ?? null,
            'createdAt' => optional($claim->created_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($claim->created_at)->format('Y-m-d H:i:s'),
            'processedAt' => optional($claim->processed_at)->format('Y-m-d H:i:s'),
            'processed_at' => optional($claim->processed_at)->format('Y-m-d H:i:s'),
            'approvedAt' => optional($claim->approved_at)->format('Y-m-d H:i:s'),
            'approved_at' => optional($claim->approved_at)->format('Y-m-d H:i:s'),
            'rejectedAt' => optional($claim->rejected_at)->format('Y-m-d H:i:s'),
            'rejected_at' => optional($claim->rejected_at)->format('Y-m-d H:i:s'),
            'completedAt' => optional($claim->completed_at)->format('Y-m-d H:i:s'),
            'completed_at' => optional($claim->completed_at)->format('Y-m-d H:i:s'),
            'cancelledAt' => optional($claim->cancelled_at)->format('Y-m-d H:i:s'),
            'cancelled_at' => optional($claim->cancelled_at)->format('Y-m-d H:i:s'),
            'meta' => $claim->meta ?? [],
            'details' => $details->map(fn ($detail) => $this->transformDetail($detail))->values()->all(),
        ];
    }

    protected function transformDetail(ClaimRewardDetail $detail): array
    {
        $snapshot = $detail->reward_snapshot ?? [];
        $gallery = $snapshot['gallery'] ?? $detail->reward->gallery ?? [];
        if (is_string($gallery)) {
            $decodedGallery = json_decode($gallery, true);
            $gallery = is_array($decodedGallery) ? $decodedGallery : [];
        }
        $firstImage = is_array($gallery) && count($gallery) > 0 ? $gallery[0] : [];
        $imageUrl = is_array($firstImage)
            ? ($firstImage['imageUrl'] ?? $firstImage['image_url'] ?? $firstImage['image'] ?? null)
            : null;

        return [
            'id' => $detail->id,
            'header_id' => $detail->header_id,
            'claim_id' => $detail->header_id,
            'rewardId' => $detail->reward_id,
            'reward_id' => $detail->reward_id,
            'rewardTitle' => $detail->reward_title,
            'reward_title' => $detail->reward_title,
            'rewardCategory' => $detail->reward_category,
            'reward_category' => $detail->reward_category,
            'variantName' => $detail->variant_name,
            'variant_name' => $detail->variant_name,
            'qty' => (int) ($detail->qty ?? 0),
            'unitPoints' => (int) ($detail->unit_points ?? 0),
            'unit_points' => (int) ($detail->unit_points ?? 0),
            'totalPoints' => (int) ($detail->total_points ?? 0),
            'total_points' => (int) ($detail->total_points ?? 0),
            'gallery' => $gallery,
            'reward_gallery' => $gallery,
            'imageUrl' => $imageUrl,
            'image_url' => $imageUrl,
            'rewardImage' => $imageUrl,
            'reward_image' => $imageUrl,
            'rewardSnapshot' => $snapshot,
            'reward_snapshot' => $snapshot,
        ];
    }

    protected function buildRewardTitleSummary(ClaimRewardHeader $claim, $details = null): string
    {
        $details = $details ?: $claim->details;
        $titles = $details->pluck('reward_title')->filter()->unique()->values();

        if ($titles->count() === 0) {
            return '-';
        }

        if ($titles->count() === 1) {
            return (string) $titles->first();
        }

        return $titles->first() . ' +' . ($titles->count() - 1) . ' item';
    }

    protected function buildRewardCategorySummary(ClaimRewardHeader $claim, $details = null): string
    {
        $details = $details ?: $claim->details;
        $categories = $details->pluck('reward_category')->filter()->unique()->values();

        return $categories->count() > 0 ? $categories->implode(', ') : '-';
    }

    protected function buildVariantSummary(ClaimRewardHeader $claim, $details = null): string
    {
        $details = $details ?: $claim->details;
        $variants = $details->pluck('variant_name')->filter()->unique()->values();

        if ($variants->count() === 0) {
            return '-';
        }

        if ($variants->count() === 1) {
            return (string) $variants->first();
        }

        return $variants->first() . ' +' . ($variants->count() - 1) . ' varian';
    }

    protected function mergeNotes(?string $current, ?string $incoming): ?string
    {
        $incoming = trim((string) $incoming);

        if ($incoming === '') {
            return $current;
        }

        $current = trim((string) $current);

        return $current === '' ? $incoming : $current . "\n" . $incoming;
    }

    protected function applyShippingData(ClaimRewardHeader $claim, Request $request): void
    {
        $method = $request->shipping_method ?: $claim->shipping_method ?: null;

        if ($this->hasHeaderColumn('shipping_method')) {
            $claim->shipping_method = $method;
        }

        if ($method === 'internal') {
            $claim->shipping_courier = 'Kurir Internal';
            $claim->shipping_reference = null;
        } else {
            $claim->shipping_courier = $request->shipping_courier ?: $claim->shipping_courier;
            $claim->shipping_reference = $request->shipping_reference ?: $claim->shipping_reference;
        }

        if ($request->estimated_received_date) {
            $meta = $claim->meta ?: [];
            $meta['estimated_received_date'] = $request->estimated_received_date;
            $claim->meta = $meta;
        }
    }

    protected function generateClaimCode(): string
    {
        return 'CR-' . Carbon::now()->format('YmdHis');
    }

    protected function hasHeaderColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            try {
                $columns = DB::connection('portal_customer')->getSchemaBuilder()->getColumnListing((new ClaimRewardHeader())->getTable());
            } catch (\Throwable $exception) {
                $columns = [];
            }
        }

        return in_array($column, $columns, true);
    }

    public function sendNotification($userId, $for)
    {
        $requestData = app('request')->all();
        $extraData = $requestData['extra_data'] ?? $requestData;

        return app(PortalNotificationService::class)->send($userId, $for, $extraData);
    }
}
