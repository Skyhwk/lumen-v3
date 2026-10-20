<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAssetUsefulLifeRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class GaAssetUsefulLifeRuleController extends Controller
{
    public function index()
    {
        $query = GaAssetUsefulLifeRule::query()->orderBy('source')->orderBy('category_name')->orderBy('sub_category_name');

        return DataTables::of($query)->make(true);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source' => 'required|in:category,kemenkeu',
            'category_name' => 'required|string|max:191',
            'sub_category_name' => 'nullable|string|max:191',
            'useful_life_years' => 'required|integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.'], 422);
        }

        $sub = trim((string) ($request->sub_category_name ?? ''));

        $rule = GaAssetUsefulLifeRule::updateOrCreate(
            [
                'source' => $request->source,
                'category_name' => strtoupper(trim($request->category_name)),
                'sub_category_name' => strtoupper($sub),
            ],
            ['useful_life_years' => (int) $request->useful_life_years]
        );

        return response()->json([
            'message' => 'Aturan umur manfaat disimpan.',
            'data' => $rule,
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'useful_life_years' => 'required|integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.'], 422);
        }

        $rule = GaAssetUsefulLifeRule::query()->find((int) $request->id);
        if (!$rule) {
            return response()->json(['message' => 'Aturan tidak ditemukan.'], 404);
        }

        $rule->update(['useful_life_years' => (int) $request->useful_life_years]);

        return response()->json([
            'message' => 'Aturan umur manfaat diperbarui.',
            'data' => $rule,
        ]);
    }

    public function delete(Request $request)
    {
        $rule = GaAssetUsefulLifeRule::query()->find((int) $request->id);
        if (!$rule) {
            return response()->json(['message' => 'Aturan tidak ditemukan.'], 404);
        }

        $rule->delete();

        return response()->json(['message' => 'Aturan umur manfaat dihapus.']);
    }
}
