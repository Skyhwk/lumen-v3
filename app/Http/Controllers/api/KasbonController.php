<?php

namespace App\Http\Controllers\api;

use App\Models\Kasbon;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\PayrollRecordSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;

class KasbonController extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            Kasbon::query()->where('kasbon.is_active', true),
            'kasbon.id_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $ongoingKasbonMap = Kasbon::where('is_active', true)
            ->whereNotNull('id_karyawan')
            ->where(function ($query) {
                $query->where('status', 'ONGOING')
                    ->orWhereNull('status');
            })
            ->where('sisa_kasbon', '>', 0)
            ->get()
            ->keyBy('id_karyawan');

        $karyawan = MasterKaryawan::where('is_active', true)
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($row) use ($ongoingKasbonMap) {
                $activeLoan = $ongoingKasbonMap->get($row->id);
                return [
                    'id_karyawan' => $row->id,
                    'nama_lengkap' => $row->nama_lengkap,
                    'has_ongoing_kasbon' => $activeLoan !== null,
                    'kode_kasbon' => $activeLoan ? $activeLoan->kode_kasbon : null,
                    'sisa_kasbon' => $activeLoan ? (float) $activeLoan->sisa_kasbon : 0,
                    'sisa_tenor' => $activeLoan ? (int) $activeLoan->sisa_tenor : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $karyawan,
            'message' => 'Available karyawan data retrieved successfully',
        ], 201);
    }

    public function delete(Request $request)
    {
        try {
            $kasbon = Kasbon::findOrFail($request->id);
            $kasbon->is_active = false;
            $kasbon->deleted_at = DATE('Y-m-d H:i:s');
            $kasbon->deleted_by = $this->karyawan;
            $kasbon->save();

            return response()->json([
                'success' => true,
                'message' => 'data kasbon deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $totalKasbonClean = str_replace(['Rp', '.', ',', ' '], '', (string) $request->total_kasbon);
            $modePotongan = $request->input('mode_potongan') === 'custom' ? 'custom' : 'auto';
            $customPotonganList = null;

            if ($modePotongan === 'custom') {
                $rawCustom = $request->input('custom_potongan');
                $decoded = is_string($rawCustom) ? json_decode($rawCustom, true) : $rawCustom;

                if (!is_array($decoded) && is_array($request->input('custom_bulan'))) {
                    $bulans = $request->input('custom_bulan', []);
                    $hargas = $request->input('custom_harga', []);
                    $decoded = [];
                    foreach ($bulans as $idx => $bln) {
                        $nom = (int) preg_replace('/\D/', '', (string) ($hargas[$idx] ?? '0'));
                        if ($bln && $nom > 0) {
                            $decoded[] = [
                                'bulan' => substr(trim((string) $bln), 0, 7),
                                'nominal' => $nom,
                                'nominal_formatted' => 'Rp ' . number_format($nom, 0, ',', '.'),
                            ];
                        }
                    }
                }

                if (!is_array($decoded) || count($decoded) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Daftar potongan custom wajib diisi',
                    ], 422);
                }

                $tenorInt = (int) $request->tenor;
                if ($tenorInt > 0 && count($decoded) > $tenorInt) {
                    return response()->json([
                        'success' => false,
                        'message' => "Jumlah baris potongan custom tidak boleh melebihi Tenor ($tenorInt)",
                    ], 422);
                }

                $sumCustom = 0;
                $normalizedRows = [];
                foreach ($decoded as $item) {
                    $bln = isset($item['bulan']) && preg_match('/^(\d{4}-\d{2})/', (string) $item['bulan'], $m)
                        ? $m[1]
                        : '';
                    $nom = (int) preg_replace('/\D/', '', (string) ($item['nominal'] ?? 0));
                    if ($bln === '' || $nom <= 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Setiap baris potongan custom wajib memiliki bulan dan nominal lebih dari 0',
                        ], 422);
                    }
                    $sumCustom += $nom;
                    $normalizedRows[] = [
                        'bulan' => $bln,
                        'nominal' => $nom,
                        'nominal_formatted' => 'Rp ' . number_format($nom, 0, ',', '.'),
                    ];
                }

                $totalKasbonInt = (int) $totalKasbonClean;
                $customTotalDiff = $sumCustom - $totalKasbonInt;
                if (abs($customTotalDiff) > 2) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total potongan custom harus sama dengan Total Kasbon (toleransi selisih maks Rp 2)',
                    ], 422);
                }
                if ($customTotalDiff !== 0 && count($normalizedRows) > 0) {
                    $lastIdx = count($normalizedRows) - 1;
                    $adjustedNom = $normalizedRows[$lastIdx]['nominal'] - $customTotalDiff;
                    if ($adjustedNom <= 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Total potongan custom tidak valid setelah penyesuaian toleransi',
                        ], 422);
                    }
                    $normalizedRows[$lastIdx]['nominal'] = $adjustedNom;
                    $normalizedRows[$lastIdx]['nominal_formatted'] = 'Rp ' . number_format($adjustedNom, 0, ',', '.');
                }

                $customPotonganList = $normalizedRows;
                $nominalPotonganClean = (string) ($normalizedRows[0]['nominal'] ?? 0);
            } else {
                $nominalPotonganClean = str_replace(['Rp', '.', ',', ' '], '', (string) $request->nominal_potongan);
            }

            if (strlen($totalKasbonClean) > 10 || strlen($nominalPotonganClean) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }

            $bulanMulaiFormatted = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_mulai_pemotongan, $m)
                ? $m[1]
                : $request->bulan_mulai_pemotongan;
            $now = DATE('Y-m-d H:i:s');

            $kasbonId = (int) $request->input('id', 0);
            if ($kasbonId > 0) {
                $kasbon = Kasbon::where('id', $kasbonId)->where('is_active', true)->firstOrFail();
                $history = is_array($kasbon->history_perubahan) ? $kasbon->history_perubahan : [];

                $trackedLabels = [
                    'total_kasbon' => 'Total Kasbon',
                    'tenor' => 'Tenor',
                    'bulan_mulai_pemotongan' => 'Bulan Mulai Potongan',
                    'mode_potongan' => 'Mode Potongan',
                    'nominal_potongan' => 'Nominal Potongan',
                    'tanggal_permintaan' => 'Tanggal Permintaan',
                    'tanggal_pencairan' => 'Tanggal Pencairan',
                    'keterangan' => 'Keterangan',
                ];

                $oldTotal = (float) $kasbon->total_kasbon;
                $newTotal = (float) $totalKasbonClean;
                $alreadyPaid = max(0, $oldTotal - (float) $kasbon->sisa_kasbon);
                $newSisaKasbon = max(0, $newTotal - $alreadyPaid);

                $oldTenor = (int) $kasbon->tenor;
                $newTenor = (int) $request->tenor;
                $usedTenor = max(0, $oldTenor - (int) $kasbon->sisa_tenor);
                $newSisaTenor = $newSisaKasbon > 0 ? max(1, $newTenor - $usedTenor) : 0;

                $incomingValues = [
                    'total_kasbon' => $newTotal,
                    'tenor' => $newTenor,
                    'bulan_mulai_pemotongan' => $bulanMulaiFormatted,
                    'mode_potongan' => $modePotongan,
                    'nominal_potongan' => (float) $nominalPotonganClean,
                    'tanggal_permintaan' => $request->tanggal_permintaan,
                    'tanggal_pencairan' => $request->tanggal_pencairan,
                    'keterangan' => $request->keterangan,
                ];

                foreach ($trackedLabels as $field => $label) {
                    $oldVal = $kasbon->$field;
                    $newVal = $incomingValues[$field];
                    $isNumeric = in_array($field, ['total_kasbon', 'tenor', 'nominal_potongan'], true);
                    $changed = $isNumeric
                        ? ((float) $oldVal !== (float) $newVal)
                        : (trim((string) $oldVal) !== trim((string) $newVal));

                    if ($changed) {
                        $history[] = [
                            'field' => $field,
                            'label' => $label,
                            'old_value' => $oldVal,
                            'new_value' => $newVal,
                            'updated_by' => $this->karyawan,
                            'updated_at' => $now,
                        ];
                    }
                }

                $kasbon->total_kasbon = $totalKasbonClean;
                $kasbon->mode_potongan = $modePotongan;
                $kasbon->nominal_potongan = $nominalPotonganClean;
                $kasbon->custom_potongan = $customPotonganList;
                $kasbon->tenor = $newTenor;
                $kasbon->bulan_mulai_pemotongan = $bulanMulaiFormatted;
                $kasbon->tanggal_permintaan = $request->tanggal_permintaan;
                $kasbon->tanggal_pencairan = $request->tanggal_pencairan;
                $kasbon->keterangan = $request->keterangan;
                $kasbon->sisa_kasbon = $newSisaKasbon;
                $kasbon->sisa_tenor = $newSisaTenor;
                $kasbon->status = $newSisaKasbon > 0 ? 'ONGOING' : 'END';
                $kasbon->history_perubahan = $history;
                $kasbon->updated_by = $this->karyawan;
                $kasbon->updated_at = $now;
                $kasbon->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Kasbon data updated successfully',
                ], 201);
            }

            // CREATE — karyawan tidak boleh punya kasbon ONGOING lain (update lewat id di atas)
            $ongoingKasbon = Kasbon::where('id_karyawan', $request->id_karyawan)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('status', 'ONGOING')
                        ->orWhereNull('status');
                })
                ->where('sisa_kasbon', '>', 0)
                ->first();

            if ($ongoingKasbon) {
                $sisaFormatted = 'Rp ' . number_format((float) $ongoingKasbon->sisa_kasbon, 0, ',', '.');
                return response()->json([
                    'success' => false,
                    'message' => "Karyawan ini masih memiliki kasbon yang sedang berjalan ({$ongoingKasbon->kode_kasbon} - Sisa: {$sisaFormatted})",
                ], 422);
            }

            $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);

            $kasbon = new Kasbon();
            $kasbon->id_karyawan = $karyawan->id;
            $kasbon->nik_karyawan = $karyawan->nik_karyawan;
            $kasbon->karyawan = $karyawan->nama_lengkap;
            $kasbon->kode_kasbon = $this->generateNoDoc();
            $kasbon->total_kasbon = $totalKasbonClean;
            $kasbon->mode_potongan = $modePotongan;
            $kasbon->nominal_potongan = $nominalPotonganClean;
            $kasbon->custom_potongan = $customPotonganList;
            $kasbon->tenor = $request->tenor;
            $kasbon->bulan_mulai_pemotongan = $bulanMulaiFormatted;
            $kasbon->tanggal_permintaan = $request->tanggal_permintaan;
            $kasbon->tanggal_pencairan = $request->tanggal_pencairan;
            $kasbon->keterangan = $request->keterangan;
            $kasbon->sisa_tenor = $request->tenor;
            $kasbon->sisa_kasbon = $kasbon->total_kasbon;
            $kasbon->status = $request->filled('status') ? $request->status : 'ONGOING';
            $kasbon->history_perubahan = [];
            $kasbon->created_by = $this->karyawan;
            $kasbon->created_at = $now;
            $kasbon->save();

            return response()->json([
                'success' => true,
                'message' => 'Kasbon data inserted successfully',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function updateInline(Request $request)
    {
        try {
            $kasbon = Kasbon::findOrFail($request->id);
            $column = $request->input('column');
            $rawValue = $request->input('value');

            if (!in_array($column, ['sisa_tenor', 'sisa_kasbon'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kolom tidak diizinkan untuk diubah secara langsung',
                ], 422);
            }

            $cleanDigits = preg_replace('/\D/', '', (string) $rawValue);
            if ($cleanDigits === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Nilai tidak boleh kosong',
                ], 422);
            }

            $newValue = $column === 'sisa_tenor' ? (int) $cleanDigits : (float) $cleanDigits;
            $oldValue = $column === 'sisa_tenor' ? (int) $kasbon->sisa_tenor : (float) $kasbon->sisa_kasbon;

            if ($column === 'sisa_kasbon' && $newValue > (float) $kasbon->total_kasbon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sisa Kasbon tidak boleh melebihi Total Kasbon',
                ], 422);
            }

            if ($column === 'sisa_tenor' && $newValue > (int) $kasbon->tenor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sisa Tenor tidak boleh melebihi Tenor awal',
                ], 422);
            }

            if ((float) $oldValue === (float) $newValue) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tidak ada perubahan nilai',
                    'data' => $kasbon,
                ], 200);
            }

            $now = DATE('Y-m-d H:i:s');
            $history = is_array($kasbon->history_perubahan) ? $kasbon->history_perubahan : [];
            $history[] = [
                'field' => $column,
                'label' => $column === 'sisa_tenor' ? 'Sisa Tenor' : 'Sisa Kasbon',
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ];

            $kasbon->$column = $newValue;
            if ($column === 'sisa_kasbon' && $newValue <= 0) {
                $kasbon->sisa_tenor = 0;
                $kasbon->status = 'END';
            } elseif ($column === 'sisa_kasbon' && $newValue > 0 && $kasbon->status === 'END') {
                $kasbon->status = 'ONGOING';
            }

            $kasbon->history_perubahan = $history;
            $kasbon->updated_by = $this->karyawan;
            $kasbon->updated_at = $now;
            $kasbon->save();

            return response()->json([
                'success' => true,
                'message' => ($column === 'sisa_tenor' ? 'Sisa Tenor' : 'Sisa Kasbon') . ' berhasil diperbarui',
                'data' => $kasbon,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function generateNoDoc()
    {
        $latestDocument = Kasbon::orderBy('kode_kasbon', 'desc')->first();

        $currentYear = DATE('y');
        $currentMonth = DATE('m');

        $romanMonth = [
            '01' => 'I',
            '02' => 'II',
            '03' => 'III',
            '04' => 'IV',
            '05' => 'V',
            '06' => 'VI',
            '07' => 'VII',
            '08' => 'VIII',
            '09' => 'IX',
            '10' => 'X',
            '11' => 'XI',
            '12' => 'XII',
        ];

        if ($latestDocument) {
            $lastNumber = 0;
            if (preg_match('/(\d{6})$/', $latestDocument->kode_kasbon, $matches)) {
                $lastNumber = intval($matches[1]);
            }
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $formattedNumber = str_pad($newNumber, 6, '0', STR_PAD_LEFT);

        $no_document = sprintf(
            "ISL/CAS/%s-%s/%s",
            $currentYear,
            $romanMonth[$currentMonth],
            $formattedNumber
        );

        return $no_document;
    }

    public function getHistory(Request $request)
    {
        $query = Kasbon::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'asc');

        return Datatables::of($data)->make(true);
    }
}
