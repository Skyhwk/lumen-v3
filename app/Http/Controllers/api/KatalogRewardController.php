<?php

namespace App\Http\Controllers\api;
use App\Models\KatalogReward;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class KatalogRewardController extends Controller
{
    public function index(Request $request)
    {
        $data = KatalogReward::where('is_active', true)
            ->orderByDesc('id');

        if ($request->has('draw')) {
            return Datatables::of($data)->make(true);
        }

        return response()->json([
            'data' => $data->get()->map(fn ($reward) => $this->transformReward($reward)),
            'status' => 200,
            'message' => 'Berhasil mendapatkan data katalog reward',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules($request));

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $reward = new KatalogReward();
        $this->fillReward($reward, $request);
        $reward->save();

        return response()->json([
            'data' => $this->transformReward($reward->fresh()),
            'status' => 200,
            'message' => 'Reward berhasil disimpan',
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules($request, true));

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $reward = KatalogReward::where('is_active', true)->find($request->id);

        if (!$reward) {
            return response()->json([
                'message' => 'Reward tidak ditemukan',
                'status' => 404,
            ], 404);
        }

        $this->fillReward($reward, $request);
        $reward->save();

        return response()->json([
            'data' => $this->transformReward($reward->fresh()),
            'status' => 200,
            'message' => 'Reward berhasil diperbarui',
        ]);
    }

    protected function rules(Request $request, bool $isUpdate = false): array
    {
        $idRule = $isUpdate ? ['required', 'integer'] : ['nullable', 'integer'];
        $codeUnique = 'unique:portal_customer.katalog_reward,code';

        if ($isUpdate && $request->id) {
            $codeUnique .= ',' . $request->id;
        }

        return [
            'id' => $idRule,
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sold' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'string', 'max:100'],
            'min_claim' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', $codeUnique],
            'showcase' => ['nullable', 'string', 'max:150'],
            'variants' => ['nullable'],
            'notes' => ['nullable'],
            'gallery' => ['nullable'],
        ];
    }

    protected function fillReward(KatalogReward $reward, Request $request): void
    {
        $reward->title = trim((string) $request->title);
        $reward->category = trim((string) $request->category);
        $reward->purchase_price = (int) $request->purchase_price;
        $reward->price = $this->calculateRewardPointFromPurchasePrice((int) $request->purchase_price);
        $reward->sold = (int) ($request->sold ?? 0);
        $reward->condition = trim((string) ($request->condition ?? 'Baru'));
        $reward->weight = trim((string) ($request->weight ?? '-'));
        $reward->min_claim = trim((string) ($request->min_claim ?? '1 pcs'));
        $reward->code = trim((string) $request->code);
        $reward->showcase = trim((string) ($request->showcase ?? ('Katalog ' . $reward->category)));
        $reward->variants = $this->normalizeStringArray($request->variants, ['Default']);
        $reward->notes = $this->normalizeStringArray($request->notes, ['Produk internal untuk kebutuhan katalog reward.']);
        $reward->gallery = $this->normalizeGallery($request->gallery);
        $reward->is_active = true;
    }

    protected function normalizeStringArray($value, array $fallback = []): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        $items = collect(is_array($value) ? $value : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        return count($items) > 0 ? $items : $fallback;
    }

    protected function normalizeGallery($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        return collect(is_array($value) ? $value : [])
            ->map(function ($item, $index) {
                $preview = isset($item['preview']) && is_array($item['preview']) ? array_values($item['preview']) : [];

                return [
                    'label' => trim((string) ($item['label'] ?? ('Gambar ' . ($index + 1)))),
                    'imageUrl' => (string) ($item['imageUrl'] ?? ''),
                    'preview' => $preview,
                    'accent' => (string) ($item['accent'] ?? ''),
                ];
            })
            ->filter(fn ($item) => $item['label'] !== '' || $item['imageUrl'] !== '')
            ->values()
            ->all();
    }

    protected function calculateRewardPointFromPurchasePrice(int $purchasePrice): int
    {
        if ($purchasePrice <= 0) {
            return 0;
        }

        $multipliedPrice = $purchasePrice * 3.5;
        $roundedPrice = $this->roundUpToSecondLeadingDigit($multipliedPrice);

        return (int) ceil($roundedPrice / 100);
    }

    protected function roundUpToSecondLeadingDigit(float $value): float
    {
        if ($value <= 0) {
            return 0;
        }

        $digitCount = strlen((string) floor($value));
        $placeValue = pow(10, max($digitCount - 2, 0));

        return ceil($value / $placeValue) * $placeValue;
    }

    protected function transformReward(KatalogReward $reward): array
    {
        $createdAt = $reward->created_at;

        if ($createdAt instanceof \Carbon\CarbonInterface) {
            $createdAt = $createdAt->timestamp;
        } elseif (!empty($createdAt)) {
            $createdAt = strtotime((string) $createdAt) ?: $reward->id;
        } else {
            $createdAt = $reward->id;
        }

        return [
            'id' => $reward->id,
            'title' => $reward->title,
            'category' => $reward->category,
            'purchasePrice' => (int) ($reward->purchase_price ?? 0),
            'price' => (int) $reward->price,
            'sold' => (int) $reward->sold,
            'createdAt' => $createdAt,
            'gallery' => $reward->gallery ?? [],
            'variants' => $reward->variants ?? [],
            'details' => [
                'condition' => $reward->condition ?: 'Baru',
                'weight' => $reward->weight ?: '-',
                'minClaim' => $reward->min_claim ?: '1 pcs',
                'code' => $reward->code,
                'showcase' => $reward->showcase ?: ('Katalog ' . $reward->category),
                'notes' => $reward->notes ?? [],
            ],
        ];
    }

}
