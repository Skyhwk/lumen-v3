<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\MasterRegulasi;
use App\Models\TabelRegulasi;

class TabelRegulasiController extends Controller
{
    public function index()
    {
        $tabelRegulasi = TabelRegulasi::where('is_active', 1)
            ->latest()
            ->get();

        return DataTables::of($tabelRegulasi)->make(true);
    }

    public function getAllRegulasi()
    {
        $masterRegulasi = MasterRegulasi::where('is_active', 1)
            ->latest()
            ->get()
            ->map(fn($item) => [
                'id' => $item->id . '-' . $item->peraturan,
                'text' => $item->id . '-' . $item->peraturan,
            ]);

        return response()->json($masterRegulasi, 200);
    }

    public function checkRegulasi(Request $request)
    {
        $ids = $request->ids;
        $matrixPayload = null;

        $query = TabelRegulasi::query();
        foreach ($ids as $id) {
            $query->whereJsonContains('id_regulasi', (string) $id);
        }
        $record = $query->first();

        if ($record) {
            $flatMatrix = json_decode($record->matrix, true);
            $maxRow = 0;
            $maxCol = 0;

            // ... (looping preg_match buat cari maxRow & maxCol) ...
            foreach ($flatMatrix as $key => $value) {
                if (preg_match('/([A-Z]+)(\d+)/i', $key, $matches)) {
                    $colStr = $matches[1];
                    $rowNum = (int)$matches[2];
                    $colIndex = $this->colLetterToIndex($colStr);
                    if ($rowNum > $maxRow) $maxRow = $rowNum;
                    if ($colIndex + 1 > $maxCol) $maxCol = $colIndex + 1;
                }
            }

            $reconstructedMatrix = array_fill(0, $maxRow, array_fill(0, $maxCol, ""));
            // ... (looping kedua buat isi $reconstructedMatrix) ...
            foreach ($flatMatrix as $key => $value) {
                if (preg_match('/([A-Z]+)(\d+)/i', $key, $matches)) {
                    $colStr = $matches[1];
                    $rowNum = (int)$matches[2];
                    $rowIndex = $rowNum - 1;
                    $colIndex = $this->colLetterToIndex($colStr);
                    if ($rowIndex >= 0 && $colIndex >= 0) {
                        $reconstructedMatrix[$rowIndex][$colIndex] = $value;
                    }
                }
            }

            $matrixPayload = [
                'rows' => $maxRow,
                'cols' => $maxCol,
                'matrix_data' => $reconstructedMatrix
            ];
        }

        return response()->json(['matrix_payload' => $matrixPayload], 200);
    }

    private function colLetterToIndex($colStr)
    {
        $colStr = strtoupper($colStr);
        $index = 0;
        $len = strlen($colStr);
        for ($i = 0; $i < $len; $i++) {
            $index = ($index * 26) + (ord($colStr[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    public function saveTabelRegulasi(Request $request)
    {
        $tabelRegulasi = new TabelRegulasi();

        $tabelRegulasi->id_regulasi = json_encode($request->id_regulasi);
        $tabelRegulasi->matrix = json_encode([]);
        $tabelRegulasi->konten = $request->content;
        $tabelRegulasi->created_by = $this->karyawan;
        $tabelRegulasi->created_at = Carbon::now();

        $tabelRegulasi->save();

        return response()->json(['message' => 'Tabel Regulasi berhasil disimpan'], 200);
    }

    public function deleteTabelRegulasi(Request $request)
    {
        $tabelRegulasi = TabelRegulasi::find($request->id);

        $tabelRegulasi->is_active = false;
        $tabelRegulasi->deleted_by = $this->karyawan;
        $tabelRegulasi->deleted_at = Carbon::now();

        $tabelRegulasi->save();

        return response()->json(['message' => 'Tabel Regulasi berhasil dihapus'], 200);
    }
}
