<?php

namespace App\Http\Controllers\api;
use App\Models\KatalogReward;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
        ]);

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

        $reward->is_active = false;
        $reward->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reward berhasil dihapus',
        ]);
    }

    protected function rules(Request $request, bool $isUpdate = false): array
    {
        $idRule = $isUpdate ? ['required', 'integer'] : ['nullable', 'integer'];

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
            'variants' => ['nullable'],
            'notes' => ['nullable'],
            'existing_gallery' => ['nullable'],
            'new_gallery' => ['nullable'],
            'gallery_files' => ['nullable', 'array'],
            'gallery_files.*' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    protected function fillReward(KatalogReward $reward, Request $request): void
    {
        $reward->title = trim((string) $request->title);
        $reward->category = trim((string) $request->category);
        $reward->purchase_price = (int) $request->purchase_price;
        $reward->price = $this->calculateRewardPointFromPurchasePrice((int) $request->purchase_price);
        $reward->sold = $request->has('sold') ? (int) $request->sold : (int) ($reward->sold ?? 0);
        $reward->condition = trim((string) ($request->condition ?? 'Baru'));
        $reward->weight = trim((string) ($request->weight ?? '-'));
        $reward->min_claim = trim((string) ($request->min_claim ?? '1 pcs'));
        $reward->code = $this->generateUniqueRewardCode((string) $request->title, $reward->id);
        $reward->variants = $this->normalizeStringArray($request->variants, ['Default']);
        $reward->notes = $this->normalizeStringArray($request->notes, ['Produk internal untuk kebutuhan katalog reward.']);
        $reward->gallery = $this->storeGallery($request, $reward->gallery ?? []);
        $reward->is_active = true;
    }

    protected function generateUniqueRewardCode(string $title, ?int $ignoreId = null): string
    {
        $baseCode = $this->generateRewardCode($title) ?: 'brg';

        for ($index = 0; $index < 100; $index++) {
            $suffix = $index === 0 ? '' : (string) $index;
            $code = substr($baseCode, 0, 10 - strlen($suffix)) . $suffix;

            $exists = KatalogReward::where('code', $code)
                ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
                ->exists();

            if (!$exists) {
                return $code;
            }
        }

        return substr($baseCode, 0, 6) . substr((string) time(), -4);
    }

    protected function generateRewardCode(string $title): string
    {
        $normalizedTitle = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $title));
        $words = preg_split('/\s+/', trim($normalizedTitle)) ?: [];
        $code = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $digitPart = preg_replace('/\D/', '', $word);
            $code .= $digitPart !== '' ? $digitPart : (preg_replace('/[aiueo]/', '', $word) ?: substr($word, 0, 1));
        }

        return substr($code, 0, 10);
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

    protected function storeGallery(Request $request, array $currentGallery = []): array
    {
        $existingGallery = $this->normalizeGallery($request->existing_gallery);
        $newGalleryMetadata = $this->normalizeGallery($request->new_gallery);
        $uploadedFiles = $request->file('gallery_files', []);
        $savedGallery = $existingGallery;

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        foreach ($uploadedFiles as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $metadata = $newGalleryMetadata[$index] ?? [];
            $relativePath = $this->saveImageAsWebp($file);

            $savedGallery[] = [
                'label' => trim((string) ($metadata['label'] ?? ('Gambar ' . ($index + 1)))),
                'imageUrl' => $relativePath,
                'preview' => isset($metadata['preview']) && is_array($metadata['preview']) ? array_values($metadata['preview']) : ['#f5f5f5', '#cfd6df'],
                'accent' => (string) ($metadata['accent'] ?? '#111827'),
            ];
        }

        $this->deleteRemovedGalleryFiles($currentGallery, $savedGallery);

        return array_values($savedGallery);
    }

    protected function saveImageAsWebp(UploadedFile $file): string
    {
        $directory = public_path('katalog-reward');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $binary = file_get_contents($file->getRealPath());
        $image = imagecreatefromstring($binary);

        if ($image === false) {
            abort(422, 'File foto tidak valid');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $fileName = uniqid('reward_', true) . '.webp';
        $targetPath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $isSaved = imagewebp($image, $targetPath, 85);
        imagedestroy($image);

        if (!$isSaved) {
            abort(500, 'Gagal menyimpan foto reward');
        }

        return $fileName;
    }

    protected function deleteRemovedGalleryFiles(array $currentGallery, array $savedGallery): void
    {
        $currentPaths = collect($currentGallery)
            ->pluck('imageUrl')
            ->filter()
            ->map(fn ($url) => public_path($this->normalizePublicRelativePath((string) $url)))
            ->all();

        $savedPaths = collect($savedGallery)
            ->pluck('imageUrl')
            ->filter()
            ->map(fn ($url) => public_path($this->normalizePublicRelativePath((string) $url)))
            ->all();

        foreach (array_diff($currentPaths, $savedPaths) as $removedPath) {
            if (strpos($removedPath, public_path('katalog-reward')) === 0 && File::exists($removedPath)) {
                File::delete($removedPath);
            }
        }
    }

    protected function normalizePublicRelativePath(string $url): string
    {
        $parsedPath = parse_url($url, PHP_URL_PATH) ?: $url;
        return ltrim(str_replace('\\', '/', $parsedPath), '/');
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
                'notes' => $reward->notes ?? [],
            ],
        ];
    }

}
