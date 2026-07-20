<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\FormulaInput;
use App\Models\FormulaVerification;
use App\Models\MasterKategori;
use App\Models\Parameter;
use App\Models\TemplateSatuan;
use App\Models\TemplateStp;
use App\Services\FormulaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class VerifikasiRumusController extends Controller
{
    private FormulaService $formulaService;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->formulaService = new FormulaService();
    }

    public function index(Request $request)
    {
        $query = Formula::query()
            ->withCount(['inputs as jumlah_input'])
            ->with(['latestVerification'])
            ->where('is_active', true)
            ->whereNull('deleted_at');

        return DataTables::of($query)->make(true);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        if (!$id) {
            return response()->json(['message' => 'ID rumus wajib diisi.'], 400);
        }

        $formula = Formula::with('inputs')->where('is_active', true)->whereNull('deleted_at')->find($id);
        if (!$formula) {
            return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => $formula,
        ], 200);
    }

    public function getMasterOptions(Request $request)
    {
        $parameters = Parameter::query()
            ->select('id', 'nama_lab', 'id_kategori', 'nama_kategori', 'satuan')
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->orderBy('nama_kategori')
            ->orderBy('nama_lab')
            ->get();

        $parameterFormulaMap = $this->buildParameterFormulaMap();

        $grouped = [];
        foreach ($parameters as $parameter) {
            $key = (string) $parameter->id_kategori;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id_kategori' => $parameter->id_kategori,
                    'kategori' => $parameter->nama_kategori,
                    'is_multi_satuan' => $this->isMultiSatuanCategory($parameter->nama_kategori),
                    'parameter' => [],
                ];
            }

            $formulas = $parameterFormulaMap[$parameter->id] ?? [];
            $hasFormulaAll = collect($formulas)->contains(fn ($item) => $item['id_template_stp'] === null);

            $grouped[$key]['parameter'][] = [
                'id' => $parameter->id,
                'nama_lab' => $parameter->nama_lab,
                'satuan' => $parameter->satuan,
                'formulas' => $formulas,
                'has_formula_all' => $hasFormulaAll,
                'has_any_formula' => !empty($formulas),
                'has_formula' => !empty($formulas),
                'formula_id' => $formulas[0]['id'] ?? null,
            ];
        }

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => array_values($grouped),
        ], 201);
    }

    public function getTemplatesByKategori(Request $request)
    {
        if (!$request->id_kategori) {
            return response()->json(['message' => 'Kategori wajib dipilih.'], 400);
        }

        $kategori = MasterKategori::where('id', $request->id_kategori)->where('is_active', true)->first();
        if (!$kategori) {
            return response()->json(['message' => 'Kategori tidak valid.'], 422);
        }

        $templates = TemplateStp::query()
            ->where('category_id', $request->id_kategori)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'param']);

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => $templates,
        ], 201);
    }

    public function getSatuanByKategori(Request $request)
    {
        if (!$request->id_kategori) {
            return response()->json(['message' => 'Kategori wajib dipilih.'], 400);
        }

        $kategori = MasterKategori::where('id', $request->id_kategori)->where('is_active', true)->first();
        if (!$kategori) {
            return response()->json(['message' => 'Kategori tidak valid.'], 422);
        }

        if (!$this->isMultiSatuanCategory($kategori->nama_kategori)) {
            return response()->json([
                'message' => 'Data hasbeen show',
                'data' => [
                    'is_multi_satuan' => false,
                    'satuan' => [],
                ],
            ], 201);
        }

        $satuan = TemplateSatuan::query()
            ->where('kategori', strtolower($kategori->nama_kategori))
            ->where('is_active', true)
            ->orderBy('satuan')
            ->pluck('satuan')
            ->values()
            ->all();

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => [
                'is_multi_satuan' => true,
                'satuan' => $satuan,
            ],
        ], 201);
    }

    public function getParametersByKategori(Request $request)
    {
        if (!$request->id_kategori) {
            return response()->json(['message' => 'Kategori wajib dipilih.'], 400);
        }

        $data = Parameter::query()
            ->select('id', 'nama_lab', 'id_kategori', 'nama_kategori', 'satuan')
            ->where('id_kategori', $request->id_kategori)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->orderBy('nama_lab')
            ->get();

        $parameterFormulaMap = $this->buildParameterFormulaMap();

        $data = $data->map(function ($parameter) use ($parameterFormulaMap) {
            $formulas = $parameterFormulaMap[$parameter->id] ?? [];
            $hasFormulaAll = collect($formulas)->contains(fn ($item) => $item['id_template_stp'] === null);

            return [
                'id' => $parameter->id,
                'nama_lab' => $parameter->nama_lab,
                'satuan' => $parameter->satuan,
                'id_kategori' => $parameter->id_kategori,
                'nama_kategori' => $parameter->nama_kategori,
                'formulas' => $formulas,
                'has_formula_all' => $hasFormulaAll,
                'has_any_formula' => !empty($formulas),
                'has_formula' => !empty($formulas),
                'formula_id' => $formulas[0]['id'] ?? null,
            ];
        });

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => $data,
        ], 201);
    }

    public function getFormulasForCopy(Request $request)
    {
        $formulas = Formula::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('kategori')
            ->orderBy('template_stp')
            ->orderBy('satuan')
            ->orderBy('parameter')
            ->get([
                'id',
                'id_kategori',
                'id_parameter',
                'id_template_stp',
                'kategori',
                'template_stp',
                'satuan',
                'parameter',
                'status',
            ]);

        $data = $formulas->map(function ($formula) {
            $templateLabel = $formula->template_stp ?: 'Semua Template';
            $satuanLabel = $this->formatSatuanLabel($formula->satuan, $formula->kategori);

            return [
                'id' => $formula->id,
                'id_kategori' => $formula->id_kategori,
                'id_parameter' => $formula->id_parameter,
                'id_template_stp' => $formula->id_template_stp,
                'kategori' => $formula->kategori,
                'template_stp' => $templateLabel,
                'satuan' => $satuanLabel,
                'parameter' => $formula->parameter,
                'status' => $formula->status,
                'label' => $formula->kategori . ' — ' . $templateLabel . ' — ' . $satuanLabel . ' — ' . $formula->parameter,
            ];
        });

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => $data,
        ], 201);
    }

    public function getCopyTargetParameters(Request $request)
    {
        if (!$request->source_formula_id) {
            return response()->json(['message' => 'Rumus sumber wajib dipilih.'], 400);
        }

        $source = Formula::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->find($request->source_formula_id);

        if (!$source) {
            return response()->json(['message' => 'Rumus sumber tidak ditemukan.'], 404);
        }

        $parameterFormulaMap = $this->buildParameterFormulaMap();
        $parameters = Parameter::query()
            ->select('id', 'nama_lab')
            ->where('id_kategori', $source->id_kategori)
            ->where('id', '!=', $source->id_parameter)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->orderBy('nama_lab')
            ->get();

        $isMultiSatuan = $this->isMultiSatuanCategory($source->kategori);
        $targets = [];
        foreach ($parameters as $parameter) {
            $formulas = $parameterFormulaMap[$parameter->id] ?? [];
            if (!$this->isParameterAvailableForScope(
                $formulas,
                $source->id_template_stp !== null ? (int) $source->id_template_stp : null,
                $source->satuan,
                $isMultiSatuan
            )) {
                continue;
            }

            $targets[] = [
                'id' => $parameter->id,
                'nama_lab' => $parameter->nama_lab,
            ];
        }

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => [
                'source' => [
                    'id' => $source->id,
                    'id_kategori' => $source->id_kategori,
                    'id_parameter' => $source->id_parameter,
                    'id_template_stp' => $source->id_template_stp,
                    'kategori' => $source->kategori,
                    'template_stp' => $source->template_stp ?: 'Semua Template',
                    'satuan' => $this->formatSatuanLabel($source->satuan, $source->kategori),
                    'parameter' => $source->parameter,
                ],
                'parameters' => $targets,
            ],
        ], 201);
    }

    public function copyFormula(Request $request)
    {
        DB::beginTransaction();

        try {
            if (!$request->source_formula_id) {
                return response()->json(['message' => 'Rumus sumber wajib dipilih.'], 422);
            }

            $targetParameterIds = $this->normalizeIdList($request->id_parameters);
            if (empty($targetParameterIds)) {
                return response()->json(['message' => 'Minimal satu parameter tujuan wajib dipilih.'], 422);
            }

            $source = Formula::with('inputs')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->find($request->source_formula_id);

            if (!$source) {
                return response()->json(['message' => 'Rumus sumber tidak ditemukan.'], 404);
            }

            $sourceInputs = $this->buildInputsFromFormulaModel($source);
            $inputErrors = $this->validateInputDefinitions($sourceInputs);
            if (!empty($inputErrors)) {
                return response()->json([
                    'message' => 'Data input rumus sumber tidak valid.',
                    'errors' => $inputErrors,
                ], 422);
            }

            $allowedVariables = array_column($sourceInputs, 'variable');
            $validation = $this->formulaService->validate($source->formula, $allowedVariables);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Formula sumber tidak valid.',
                    'errors' => $validation['errors'],
                ], 422);
            }

            $isMultiSatuan = $this->isMultiSatuanCategory($source->kategori);
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $created = [];
            $skipped = [];

            foreach ($targetParameterIds as $parameterId) {
                if ((int) $parameterId === (int) $source->id_parameter) {
                    $skipped[] = [
                        'id_parameter' => $parameterId,
                        'parameter' => null,
                        'reason' => 'Parameter sumber tidak bisa menjadi tujuan copy.',
                    ];
                    continue;
                }

                $parameter = Parameter::query()
                    ->where('id', $parameterId)
                    ->where('id_kategori', $source->id_kategori)
                    ->where('is_active', true)
                    ->where('is_blocked', false)
                    ->first();

                if (!$parameter) {
                    $skipped[] = [
                        'id_parameter' => $parameterId,
                        'parameter' => null,
                        'reason' => 'Parameter tujuan tidak valid atau beda kategori.',
                    ];
                    continue;
                }

                $targetSatuanScope = $isMultiSatuan
                    ? ['satuan' => $source->satuan]
                    : $this->resolveSatuanFromParameter($parameter);

                if (!empty($targetSatuanScope['error'])) {
                    $skipped[] = [
                        'id_parameter' => $parameter->id,
                        'parameter' => $parameter->nama_lab,
                        'reason' => $targetSatuanScope['error']['message'],
                    ];
                    continue;
                }

                $duplicateError = $this->validateParameterFormulaScope(
                    (int) $parameter->id,
                    $source->id_template_stp !== null ? (int) $source->id_template_stp : null,
                    $targetSatuanScope['satuan'],
                    $isMultiSatuan,
                    null
                );

                if ($duplicateError) {
                    $skipped[] = [
                        'id_parameter' => $parameter->id,
                        'parameter' => $parameter->nama_lab,
                        'reason' => $duplicateError['message'],
                    ];
                    continue;
                }

                if ($source->status === 'active') {
                    Formula::where('id_parameter', $parameter->id)
                        ->where(function ($query) use ($source, $targetSatuanScope) {
                            $this->applyTemplateStpScopeToQuery($query, $source->id_template_stp);
                            $this->applySatuanScopeToQuery($query, $targetSatuanScope['satuan']);
                        })
                        ->where('status', 'active')
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->update([
                            'status' => 'inactive',
                            'updated_by' => $this->karyawan,
                            'updated_at' => $now,
                        ]);
                }

                $formula = Formula::create([
                    'id_kategori' => $source->id_kategori,
                    'id_parameter' => $parameter->id,
                    'id_template_stp' => $source->id_template_stp,
                    'kategori' => $source->kategori,
                    'parameter' => $parameter->nama_lab,
                    'template_stp' => $source->template_stp ?: 'Semua Template',
                    'satuan' => $targetSatuanScope['satuan'],
                    'formula' => $source->formula,
                    'formula_json' => $source->formula_json,
                    'status' => $source->status,
                    'is_active' => true,
                    'created_by' => $this->karyawan,
                    'created_at' => $now,
                    'updated_by' => $this->karyawan,
                    'updated_at' => $now,
                ]);

                $this->syncFormulaInputs((int) $formula->id, $sourceInputs);

                $created[] = [
                    'id' => $formula->id,
                    'id_parameter' => $parameter->id,
                    'parameter' => $parameter->nama_lab,
                ];
            }

            if (empty($created)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Tidak ada rumus yang berhasil disalin.',
                    'data' => ['created' => [], 'skipped' => $skipped],
                ], 422);
            }

            DB::commit();

            $message = count($created) . ' rumus berhasil disalin.';
            if (!empty($skipped)) {
                $message .= ' ' . count($skipped) . ' parameter dilewati.';
            }

            return response()->json([
                'message' => $message,
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped,
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function validateFormula(Request $request)
    {
        $formula = trim((string) $request->formula);
        $allowedVariables = $this->extractAllowedVariables($request);

        $result = $this->formulaService->validate($formula, $allowedVariables);

        if (!$result['valid']) {
            return response()->json($result, 422);
        }

        return response()->json($result, 200);
    }

    public function testFormula(Request $request)
    {
        $formula = trim((string) $request->formula);
        $values = $this->extractInputValues($request);
        $allowedVariables = $this->extractAllowedVariables($request);

        $missing = array_diff($allowedVariables, array_keys($values));
        if (!empty($missing)) {
            return response()->json([
                'valid' => false,
                'message' => 'Semua variable wajib diisi untuk test formula.',
                'errors' => [[
                    'code' => 'MISSING_INPUT',
                    'message' => 'Variable "' . reset($missing) . '" belum diisi.',
                ]],
            ], 422);
        }

        $numericError = $this->validateNumericInputs($values);
        if ($numericError) {
            return response()->json($numericError, 422);
        }

        $result = $this->formulaService->calculate($formula, $values);

        if (!$result['valid']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'message' => 'Calculation success',
            'data' => $result,
        ], 200);
    }

    public function calculate(Request $request)
    {
        if (!$request->id) {
            return response()->json(['message' => 'ID rumus wajib diisi.'], 400);
        }

        $formula = Formula::with('inputs')->where('is_active', true)->whereNull('deleted_at')->find($request->id);
        if (!$formula) {
            return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
        }

        $values = $this->extractInputValues($request);
        $requiredError = $this->validateRequiredInputs($formula->inputs, $values);
        if ($requiredError) {
            return response()->json($requiredError, 422);
        }

        $numericError = $this->validateNumericInputs($values);
        if ($numericError) {
            return response()->json($numericError, 422);
        }

        $allowedVariables = $formula->inputs->pluck('variable')->all();
        $extra = array_diff(array_keys($values), $allowedVariables);
        if (!empty($extra)) {
            return response()->json([
                'valid' => false,
                'message' => 'Variable tidak dikenal.',
            ], 422);
        }

        $result = $this->formulaService->calculate($formula->formula, $values);

        if (!$result['valid']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'message' => 'Calculation success',
            'data' => $result,
        ], 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $inputs = $this->normalizeInputs($request);
            if (empty($inputs)) {
                return response()->json(['message' => 'Minimal satu input variable wajib diisi.'], 422);
            }

            $inputErrors = $this->validateInputDefinitions($inputs);
            if (!empty($inputErrors)) {
                return response()->json([
                    'message' => 'Validasi input gagal.',
                    'errors' => $inputErrors,
                ], 422);
            }

            if (!$request->id_kategori || !$request->id_parameter) {
                return response()->json(['message' => 'Kategori dan parameter wajib dipilih.'], 422);
            }

            $templateScope = $this->resolveTemplateStpFromRequest($request);
            if (!empty($templateScope['error'])) {
                return response()->json($templateScope['error'], 422);
            }

            $kategori = MasterKategori::where('id', $request->id_kategori)->where('is_active', true)->first();
            $parameter = Parameter::where('id', $request->id_parameter)
                ->where('id_kategori', $request->id_kategori)
                ->where('is_active', true)
                ->where('is_blocked', false)
                ->first();

            if (!$kategori || !$parameter) {
                return response()->json(['message' => 'Kategori atau parameter tidak valid.'], 422);
            }

            $isMultiSatuan = $this->isMultiSatuanCategory($kategori->nama_kategori);
            $satuanScope = $this->resolveSatuanFromRequest($request, $parameter, $kategori->nama_kategori);
            if (!empty($satuanScope['error'])) {
                return response()->json($satuanScope['error'], 422);
            }

            $duplicateError = $this->validateParameterFormulaScope(
                (int) $parameter->id,
                $templateScope['id_template_stp'],
                $satuanScope['satuan'],
                $isMultiSatuan,
                $request->id ? (int) $request->id : null
            );
            if ($duplicateError) {
                return response()->json($duplicateError, 422);
            }

            $formulaText = trim((string) $request->formula);
            $allowedVariables = array_column($inputs, 'variable');
            $validation = $this->formulaService->validate($formulaText, $allowedVariables);

            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Formula tidak valid.',
                    'errors' => $validation['errors'],
                ], 422);
            }

            $status = in_array($request->status, ['draft', 'active', 'inactive'], true)
                ? $request->status
                : 'draft';

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $formulaJson = $this->decodeFormulaJson($request->formula_json);

            if ($status === 'active') {
                Formula::where('id_parameter', $parameter->id)
                    ->where(function ($query) use ($templateScope, $satuanScope) {
                        $this->applyTemplateStpScopeToQuery($query, $templateScope['id_template_stp']);
                        $this->applySatuanScopeToQuery($query, $satuanScope['satuan']);
                    })
                    ->where('status', 'active')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->when($request->id, function ($query) use ($request) {
                        $query->where('id', '!=', $request->id);
                    })
                    ->update([
                        'status' => 'inactive',
                        'updated_by' => $this->karyawan,
                        'updated_at' => $now,
                    ]);
            }

            if ($request->id) {
                $formula = Formula::where('is_active', true)->whereNull('deleted_at')->find($request->id);
                if (!$formula) {
                    return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
                }

                $formula->fill([
                    'id_kategori' => $kategori->id,
                    'id_parameter' => $parameter->id,
                    'id_template_stp' => $templateScope['id_template_stp'],
                    'kategori' => $kategori->nama_kategori,
                    'parameter' => $parameter->nama_lab,
                    'template_stp' => $templateScope['template_stp'],
                    'satuan' => $satuanScope['satuan'],
                    'formula' => $formulaText,
                    'formula_json' => $formulaJson,
                    'status' => $status,
                    'updated_by' => $this->karyawan,
                    'updated_at' => $now,
                ]);
                $formula->save();

                $this->syncFormulaInputs((int) $formula->id, $inputs);
            } else {
                $formula = Formula::create([
                    'id_kategori' => $kategori->id,
                    'id_parameter' => $parameter->id,
                    'id_template_stp' => $templateScope['id_template_stp'],
                    'kategori' => $kategori->nama_kategori,
                    'parameter' => $parameter->nama_lab,
                    'template_stp' => $templateScope['template_stp'],
                    'satuan' => $satuanScope['satuan'],
                    'formula' => $formulaText,
                    'formula_json' => $formulaJson,
                    'status' => $status,
                    'is_active' => true,
                    'created_by' => $this->karyawan,
                    'created_at' => $now,
                    'updated_by' => $this->karyawan,
                    'updated_at' => $now,
                ]);

                $this->syncFormulaInputs((int) $formula->id, $inputs);
            }

            DB::commit();

            return response()->json([
                'message' => 'Rumus berhasil disimpan.',
                'data' => $formula->load('inputs'),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function lookupSystemResult(Request $request)
    {
        if (!$request->formula_id || !$request->no_sampel) {
            return response()->json(['message' => 'Formula dan nomor sampel wajib diisi.'], 400);
        }

        $formula = Formula::where('is_active', true)->whereNull('deleted_at')->find($request->formula_id);
        if (!$formula) {
            return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
        }

        $hasil = $this->findSystemResult(trim((string) $request->no_sampel), (int) $formula->id_parameter);
        if ($hasil === null) {
            return response()->json([
                'message' => 'Hasil uji sistem tidak ditemukan untuk nomor sampel ini.',
            ], 404);
        }

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => ['hasil' => $hasil],
        ], 200);
    }

    public function storeVerifikasi(Request $request)
    {
        DB::beginTransaction();

        try {
            if (!$request->formula_id) {
                return response()->json(['message' => 'ID rumus wajib diisi.'], 400);
            }

            $noSampel = trim((string) $request->no_sampel);
            $hasilSistem = trim((string) $request->hasil_sistem);
            $hasilManual = trim((string) $request->hasil_manual);

            if ($noSampel === '') {
                return response()->json(['message' => 'Nomor sampel wajib diisi.'], 422);
            }
            if ($hasilSistem === '') {
                return response()->json(['message' => 'Hasil uji di sistem wajib diisi.'], 422);
            }
            if ($hasilManual === '') {
                return response()->json(['message' => 'Hasil uji hitung manual wajib diisi.'], 422);
            }

            $verifikator = trim((string) ($request->verifikator ?? ''));
            if ($verifikator === '') {
                $verifikator = $this->karyawan;
            }

            try {
                $tanggalVerifikasi = $request->tanggal_verifikasi
                    ? Carbon::parse($request->tanggal_verifikasi)->format('Y-m-d H:i:s')
                    : Carbon::now()->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Format tanggal verifikasi tidak valid.'], 422);
            }

            $formula = Formula::where('is_active', true)->whereNull('deleted_at')->find($request->formula_id);
            if (!$formula) {
                return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
            }

            $isMatch = $this->compareVerificationResults($hasilSistem, $hasilManual);
            $statusVerifikasi = $isMatch ? 'sesuai' : 'tidak_sesuai';
            $statusLabel = $isMatch
                ? 'Terverifikasi Rumus di Sistem LIMS Sesuai'
                : 'Terverifikasi Rumus di Sistem LIMS Tidak Sesuai';

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $uploadPath = 'verifikasi_rumus/';

            if (!is_dir(public_path($uploadPath))) {
                mkdir(public_path($uploadPath), 0755, true);
            }

            $fotoFilename = null;
            if ($request->foto_screenshot) {
                $fotoResult = $this->processAndSaveVerificationFile(
                    $request->foto_screenshot,
                    $uploadPath,
                    'SS',
                    $noSampel
                );
                if (!$fotoResult['success']) {
                    return response()->json(['message' => $fotoResult['message']], 422);
                }
                $fotoFilename = $fotoResult['filename'];
            }

            $dokumenFilename = null;
            if ($request->dokumen_file) {
                $docResult = $this->processAndSaveVerificationFile(
                    $request->dokumen_file,
                    $uploadPath,
                    'DOC',
                    $noSampel
                );
                if (!$docResult['success']) {
                    return response()->json(['message' => $docResult['message']], 422);
                }
                $dokumenFilename = $docResult['filename'];
            }

            $linkDokumen = trim((string) ($request->link_dokumen ?? ''));
            if ($linkDokumen === '') {
                $linkDokumen = null;
            }

            $verification = FormulaVerification::create([
                'formula_id' => $formula->id,
                'tanggal_verifikasi' => $tanggalVerifikasi,
                'no_sampel' => $noSampel,
                'hasil_sistem' => $hasilSistem,
                'hasil_manual' => $hasilManual,
                'rumus_sistem' => $formula->formula,
                'foto_screenshot' => $fotoFilename,
                'link_dokumen' => $linkDokumen,
                'dokumen_filename' => $dokumenFilename,
                'status_verifikasi' => $statusVerifikasi,
                'status_label' => $statusLabel,
                'verifikator' => $verifikator,
                'catatan' => trim((string) ($request->catatan ?? '')) ?: null,
                'is_active' => true,
                'created_by' => $this->karyawan,
                'created_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Verifikasi rumus berhasil disimpan.',
                'data' => $verification,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function showVerifikasi(Request $request)
    {
        if (!$request->formula_id && !$request->id) {
            return response()->json(['message' => 'ID verifikasi atau formula wajib diisi.'], 400);
        }

        if ($request->id) {
            $verification = FormulaVerification::with('formula')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->find($request->id);

            if (!$verification) {
                return response()->json(['message' => 'Data verifikasi tidak ditemukan.'], 404);
            }

            return response()->json([
                'message' => 'Data hasbeen show',
                'data' => $this->formatVerificationResponse($verification),
            ], 200);
        }

        $formula = Formula::with(['verifications' => function ($query) {
            $query->limit(200);
        }])->where('is_active', true)->whereNull('deleted_at')->find($request->formula_id);

        if (!$formula) {
            return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
        }

        $records = $formula->verifications->map(function ($item) {
            return $this->formatVerificationResponse($item);
        });

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => [
                'formula' => [
                    'id' => $formula->id,
                    'kategori' => $formula->kategori,
                    'parameter' => $formula->parameter,
                    'template_stp' => $formula->template_stp ?: 'Semua Template',
                    'satuan' => $this->formatSatuanLabel($formula->satuan, $formula->kategori),
                    'formula' => $formula->formula,
                ],
                'records' => $records,
                'latest' => $records->first(),
            ],
        ], 200);
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();

        try {
            if (!$request->id) {
                return response()->json(['message' => 'ID rumus wajib diisi.'], 400);
            }

            $formula = Formula::where('is_active', true)->whereNull('deleted_at')->find($request->id);
            if (!$formula) {
                return response()->json(['message' => 'Data rumus tidak ditemukan.'], 404);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $formula->is_active = false;
            $formula->status = 'inactive';
            $formula->deleted_by = $this->karyawan;
            $formula->deleted_at = $now;
            $formula->updated_by = $this->karyawan;
            $formula->updated_at = $now;
            $formula->save();

            FormulaInput::where('formula_id', $formula->id)->update(['is_active' => false]);

            DB::commit();

            return response()->json(['message' => 'Rumus berhasil dihapus.'], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function syncFormulaInputs(int $formulaId, array $inputs): void
    {
        $variables = [];

        foreach ($inputs as $index => $input) {
            $variable = $input['variable'];
            $variables[] = $variable;

            FormulaInput::updateOrCreate(
                [
                    'formula_id' => $formulaId,
                    'variable' => $variable,
                ],
                [
                    'label' => $input['label'],
                    'type' => $input['type'],
                    'required' => $input['required'],
                    'default_value' => $input['default_value'] ?? null,
                    'urutan' => $input['urutan'] ?? ($index + 1),
                    'is_active' => true,
                ]
            );
        }

        if (empty($variables)) {
            FormulaInput::where('formula_id', $formulaId)->delete();
            return;
        }

        FormulaInput::where('formula_id', $formulaId)
            ->whereNotIn('variable', $variables)
            ->delete();
    }

    private function normalizeInputs(Request $request): array
    {
        $inputs = $request->input('inputs');

        if (is_string($inputs)) {
            $decoded = json_decode($inputs, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        if (is_array($inputs)) {
            return array_values($inputs);
        }

        return [];
    }

    private function validateInputDefinitions(array $inputs): array
    {
        $errors = [];
        $seen = [];

        foreach ($inputs as $index => $input) {
            $variable = trim((string) ($input['variable'] ?? ''));
            $label = trim((string) ($input['label'] ?? ''));

            if ($variable === '') {
                $errors[] = ['message' => 'Nama variable pada baris ' . ($index + 1) . ' wajib diisi.'];
                continue;
            }

            if (!preg_match('/^[a-z][a-z0-9_]*$/', $variable)) {
                $errors[] = ['message' => 'Variable "' . $variable . '" tidak valid. Gunakan a-z, 0-9, dan underscore.'];
            }

            if (isset($seen[$variable])) {
                $errors[] = ['message' => 'Variable "' . $variable . '" duplikat.'];
            }
            $seen[$variable] = true;

            if ($label === '') {
                $errors[] = ['message' => 'Label untuk variable "' . $variable . '" wajib diisi.'];
            }

            $type = $input['type'] ?? 'number';
            if (!in_array($type, ['number', 'integer', 'decimal'], true)) {
                $errors[] = ['message' => 'Tipe input "' . $variable . '" tidak valid.'];
            }
        }

        return $errors;
    }

    private function extractAllowedVariables(Request $request): array
    {
        $inputs = $this->normalizeInputs($request);
        if (!empty($inputs)) {
            return array_values(array_unique(array_column($inputs, 'variable')));
        }

        if ($request->variables) {
            $variables = is_string($request->variables)
                ? json_decode($request->variables, true)
                : $request->variables;

            return is_array($variables) ? array_values($variables) : [];
        }

        return [];
    }

    private function extractInputValues(Request $request): array
    {
        $values = $request->input('values');

        if (is_string($values)) {
            $decoded = json_decode($values, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (is_array($values)) {
            return $values;
        }

        return [];
    }

    private function validateNumericInputs(array $values): ?array
    {
        foreach ($values as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (!is_numeric($value)) {
                return [
                    'valid' => false,
                    'message' => 'Input "' . $name . '" harus berupa angka.',
                    'errors' => [[
                        'code' => 'INVALID_NUMBER',
                        'message' => 'Input "' . $name . '" harus berupa angka.',
                    ]],
                ];
            }
        }

        return null;
    }

    private function validateRequiredInputs($inputs, array $values): ?array
    {
        foreach ($inputs as $input) {
            if (!$input->required) {
                continue;
            }

            $value = $values[$input->variable] ?? null;
            if ($value === '' || $value === null) {
                return [
                    'valid' => false,
                    'message' => 'Input "' . $input->label . '" wajib diisi.',
                    'errors' => [[
                        'code' => 'MISSING_INPUT',
                        'message' => 'Input "' . $input->label . '" wajib diisi.',
                    ]],
                ];
            }
        }

        return null;
    }

    private function decodeFormulaJson($formulaJson): ?array
    {
        if (is_array($formulaJson)) {
            return $formulaJson;
        }

        if (is_string($formulaJson) && $formulaJson !== '') {
            $decoded = json_decode($formulaJson, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function buildParameterFormulaMap(): array
    {
        $rows = Formula::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['id', 'id_parameter', 'id_template_stp', 'satuan']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->id_parameter][] = [
                'id' => $row->id,
                'id_template_stp' => $row->id_template_stp !== null ? (int) $row->id_template_stp : null,
                'satuan' => $row->satuan,
            ];
        }

        return $map;
    }

    private function isParameterAvailableForScope(
        array $formulas,
        ?int $templateStpId,
        ?string $satuanName,
        bool $isMultiSatuanCategory
    ): bool {
        return $this->validateParameterFormulaScopeFromFormulas(
            $formulas,
            $templateStpId,
            $satuanName,
            $isMultiSatuanCategory
        ) === null;
    }

    private function validateParameterFormulaScopeFromFormulas(
        array $formulas,
        ?int $templateStpId,
        ?string $satuanName,
        bool $isMultiSatuanCategory
    ): ?array {
        if (empty($formulas)) {
            return null;
        }

        $existing = collect($formulas);

        $hasAllTemplate = $existing->contains(fn ($item) => $item['id_template_stp'] === null);
        if ($hasAllTemplate) {
            return ['message' => 'Parameter ini sudah memiliki rumus untuk semua template STP.'];
        }

        if ($templateStpId === null) {
            return ['message' => 'Parameter ini sudah memiliki rumus pada template STP tertentu.'];
        }

        $sameTemplate = $existing->filter(
            fn ($item) => (int) $item['id_template_stp'] === (int) $templateStpId
        );

        if ($sameTemplate->isEmpty()) {
            return null;
        }

        if (!$isMultiSatuanCategory) {
            return ['message' => 'Parameter ini sudah memiliki rumus pada template STP ini.'];
        }

        $hasAllSatuan = $sameTemplate->contains(fn ($item) => $this->isAllSatuanStored($item['satuan'] ?? null));
        if ($hasAllSatuan) {
            return ['message' => 'Parameter ini sudah memiliki rumus untuk semua satuan pada template STP ini.'];
        }

        if ($this->isAllSatuanStored($satuanName)) {
            return ['message' => 'Parameter ini sudah memiliki rumus pada satuan tertentu.'];
        }

        $duplicateSatuan = $sameTemplate->contains(
            fn ($item) => trim((string) ($item['satuan'] ?? '')) === trim((string) $satuanName)
        );
        if ($duplicateSatuan) {
            return ['message' => 'Parameter ini sudah memiliki rumus pada satuan ini.'];
        }

        return null;
    }

    private function buildInputsFromFormulaModel(Formula $formula): array
    {
        return $formula->inputs->map(function ($input) {
            return [
                'variable' => $input->variable,
                'label' => $input->label,
                'type' => $input->type ?: 'number',
                'required' => (bool) $input->required,
                'default_value' => $input->default_value,
                'urutan' => (int) $input->urutan,
            ];
        })->values()->all();
    }

    private function normalizeIdList($rawIds): array
    {
        if (is_string($rawIds)) {
            $decoded = json_decode($rawIds, true);
            $rawIds = is_array($decoded) ? $decoded : explode(',', $rawIds);
        }

        if (!is_array($rawIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function resolveTemplateStpFromRequest(Request $request): array
    {
        $rawTemplate = $request->id_template_stp;
        if ($rawTemplate === null || $rawTemplate === '' || $rawTemplate === 'all') {
            return [
                'id_template_stp' => null,
                'template_stp' => 'Semua Template',
                'error' => null,
            ];
        }

        if (!$request->id_kategori) {
            return [
                'id_template_stp' => null,
                'template_stp' => null,
                'error' => ['message' => 'Kategori wajib dipilih sebelum template STP.'],
            ];
        }

        $template = TemplateStp::query()
            ->where('id', $rawTemplate)
            ->where('category_id', $request->id_kategori)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return [
                'id_template_stp' => null,
                'template_stp' => null,
                'error' => ['message' => 'Template STP tidak valid untuk kategori ini.'],
            ];
        }

        return [
            'id_template_stp' => (int) $template->id,
            'template_stp' => $template->name,
            'error' => null,
        ];
    }

    private function resolveSatuanFromRequest(Request $request, Parameter $parameter, string $kategoriName): array
    {
        if (!$this->isMultiSatuanCategory($kategoriName)) {
            return $this->resolveSatuanFromParameter($parameter);
        }

        $rawSatuan = $request->satuan;
        if ($rawSatuan === null || $rawSatuan === '' || $rawSatuan === 'all') {
            return [
                'satuan' => null,
                'error' => null,
            ];
        }

        $satuanName = trim((string) $rawSatuan);
        $templateSatuan = TemplateSatuan::query()
            ->where('satuan', $satuanName)
            ->where('kategori', strtolower($kategoriName))
            ->where('is_active', true)
            ->first();

        if (!$templateSatuan) {
            return [
                'satuan' => null,
                'error' => ['message' => 'Satuan tidak valid untuk kategori ini.'],
            ];
        }

        return [
            'satuan' => $templateSatuan->satuan,
            'error' => null,
        ];
    }

    private function resolveSatuanFromParameter(Parameter $parameter): array
    {
        $satuan = trim((string) ($parameter->satuan ?? ''));
        if ($satuan === '') {
            return [
                'satuan' => null,
                'error' => ['message' => 'Parameter ini belum memiliki satuan.'],
            ];
        }

        return [
            'satuan' => $satuan,
            'error' => null,
        ];
    }

    private function isMultiSatuanCategory(?string $kategoriName): bool
    {
        $normalized = strtolower(trim((string) $kategoriName));

        return in_array($normalized, ['udara', 'emisi'], true);
    }

    private function isAllSatuanStored(?string $satuan): bool
    {
        return $satuan === null || trim((string) $satuan) === '';
    }

    private function formatSatuanLabel(?string $satuan, ?string $kategoriName): string
    {
        if (!$this->isAllSatuanStored($satuan)) {
            return (string) $satuan;
        }

        return $this->isMultiSatuanCategory($kategoriName) ? 'Semua Satuan' : '-';
    }

    private function applyTemplateStpScopeToQuery($query, ?int $templateStpId): void
    {
        if ($templateStpId === null) {
            $query->whereNull('id_template_stp');
            return;
        }

        $query->where('id_template_stp', $templateStpId);
    }

    private function applySatuanScopeToQuery($query, ?string $satuanName): void
    {
        if ($this->isAllSatuanStored($satuanName)) {
            $query->whereNull('satuan');
            return;
        }

        $query->where('satuan', $satuanName);
    }

    private function validateParameterFormulaScope(
        int $parameterId,
        ?int $templateStpId,
        ?string $satuanName,
        bool $isMultiSatuanCategory,
        ?int $excludeFormulaId = null
    ): ?array {
        $query = Formula::query()
            ->where('id_parameter', $parameterId)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if ($excludeFormulaId) {
            $query->where('id', '!=', $excludeFormulaId);
        }

        $existing = $query->get(['id', 'id_template_stp', 'satuan']);
        if ($existing->isEmpty()) {
            return null;
        }

        $formulas = $existing->map(function ($item) {
            return [
                'id' => $item->id,
                'id_template_stp' => $item->id_template_stp !== null ? (int) $item->id_template_stp : null,
                'satuan' => $item->satuan,
            ];
        })->all();

        $error = $this->validateParameterFormulaScopeFromFormulas(
            $formulas,
            $templateStpId,
            $satuanName,
            $isMultiSatuanCategory
        );

        if (!$error) {
            return null;
        }

        return [
            'message' => $error['message'],
            'errors' => [[
                'code' => 'PARAMETER_FORMULA_SCOPE_CONFLICT',
                'message' => $error['message'],
            ]],
        ];
    }

    private function compareVerificationResults(string $system, string $manual): bool
    {
        if (is_numeric($system) && is_numeric($manual)) {
            return abs((float) $system - (float) $manual) < 0.0001;
        }

        return trim($system) === trim($manual);
    }

    private function findSystemResult(string $noSampel, int $idParameter): ?string
    {
        $tables = [
            'lhps_air_detail',
            'lhps_padatan_detail',
            'lhps_ling_detail',
        ];

        foreach ($tables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $row = DB::table($table)
                ->where('no_sampel', $noSampel)
                ->where('id_parameter', $idParameter)
                ->where('is_active', true)
                ->first();

            if ($row && isset($row->lhps) && $row->lhps !== '' && $row->lhps !== null) {
                return (string) $row->lhps;
            }
        }

        return null;
    }

    private function formatVerificationResponse(FormulaVerification $verification): array
    {
        $baseUrl = rtrim((string) env('APP_PUBLIC_URL', ''), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim((string) env('APP_URL', ''), '/');
        }

        $fileBase = $baseUrl ? $baseUrl . '/verifikasi_rumus/' : 'verifikasi_rumus/';

        return [
            'id' => $verification->id,
            'formula_id' => $verification->formula_id,
            'tanggal_verifikasi' => $verification->tanggal_verifikasi,
            'no_sampel' => $verification->no_sampel,
            'hasil_sistem' => $verification->hasil_sistem,
            'hasil_manual' => $verification->hasil_manual,
            'rumus_sistem' => $verification->rumus_sistem,
            'foto_screenshot' => $verification->foto_screenshot,
            'foto_url' => $verification->foto_screenshot ? $fileBase . $verification->foto_screenshot : null,
            'link_dokumen' => $verification->link_dokumen,
            'dokumen_filename' => $verification->dokumen_filename,
            'dokumen_url' => $verification->dokumen_filename ? $fileBase . $verification->dokumen_filename : null,
            'status_verifikasi' => $verification->status_verifikasi,
            'status_label' => $verification->status_label,
            'verifikator' => $verification->verifikator,
            'catatan' => $verification->catatan,
            'formula' => $verification->relationLoaded('formula') ? [
                'kategori' => $verification->formula->kategori,
                'parameter' => $verification->formula->parameter,
            ] : null,
        ];
    }

    private function processAndSaveVerificationFile($base64File, string $path, string $prefix, string $noSampel): array
    {
        try {
            $fileData = $this->extractBase64FileData($base64File);
            if (!$fileData) {
                return ['success' => false, 'message' => 'Format file tidak valid.'];
            }

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $fileExtension = strtolower($fileData['extension']);
            if (!in_array($fileExtension, $allowedExtensions, true)) {
                return ['success' => false, 'message' => 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.'];
            }

            $safeSample = str_replace(['/', '\\', ' '], '_', $noSampel);
            $fileName = $prefix . '-' . $safeSample . '_' . Carbon::now()->format('YmdHis') . '_' . time() . '.' . $fileExtension;
            $fullPath = public_path($path . $fileName);

            $decodedContent = base64_decode($fileData['content']);
            if ($decodedContent === false || empty($decodedContent)) {
                return ['success' => false, 'message' => 'Gagal memproses file.'];
            }

            if (file_put_contents($fullPath, $decodedContent) === false) {
                return ['success' => false, 'message' => 'Gagal menyimpan file.'];
            }

            return ['success' => true, 'filename' => $fileName];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function extractBase64FileData($base64String): ?array
    {
        if (strpos($base64String, ';base64,') === false) {
            return null;
        }

        [$fileInfo, $fileContent] = explode(';base64,', $base64String);
        [, $fileType] = explode(':', $fileInfo);
        $fileExtension = $this->getExtensionFromMimeType($fileType);

        if (!$fileExtension) {
            return null;
        }

        return [
            'type' => $fileType,
            'extension' => $fileExtension,
            'content' => $fileContent,
        ];
    }

    private function getExtensionFromMimeType(string $mimeType): ?string
    {
        $map = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
        ];

        return $map[$mimeType] ?? null;
    }
}
