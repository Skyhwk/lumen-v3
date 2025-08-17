<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\{Parameter,CompanyPageControl};
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use DB;

class ScriptController extends Controller
{
    public function checkParameter()
    {
        $csvPath = public_path('checkParameter/fixingParameter.csv');

        if (!file_exists($csvPath)) {
            return response()->json(['error' => 'File tidak ditemukan'], 404);
        }

        $file = fopen($csvPath, 'r');
        if (!$file) {
            return response()->json(['error' => 'Gagal membuka file CSV'], 500);
        }

        $found = [];
        $notFound = [];
        $detailedDifferences = [];

        $index = 0;
        $maxRow = 455;

        while (($row = fgetcsv($file)) !== false) {
            $index++;

            if ($index === 1) continue; // skip header
            if ($index > $maxRow) break;

            // Trim dan ambil semua kolom
            $kategori     = trim($row[0] ?? '');
            $nama_lab     = trim($row[1] ?? '');
            $nama_regulasi = trim($row[2] ?? '');
            $nama_lhp     = trim($row[3] ?? '');
            $status       = trim($row[4] ?? '');
            $satuan       = trim($row[5] ?? '');
            $method       = trim($row[6] ?? '');
            $created_by   = trim($row[7] ?? '');

            $conditions = [
                'nama_kategori' => ucfirst(strtolower($kategori)),
                'nama_lab' => $nama_lab,
                'id_kategori' => $this->getIdKategori($kategori),
                'nama_regulasi' => $nama_regulasi,
                'nama_lhp' => $nama_lhp,
                'status' => $status,
                'satuan' => $satuan,
                'method' => $method,
                'created_by' => $created_by
            ];

            $exists = Parameter::where($conditions)->exists();

            if ($exists) {
                $found[] = $index;
            } else {
                // Step by step filter
                $query = Parameter::query();

                $steps = [
                    'nama_kategori' => $kategori,
                    'nama_lab' => $nama_lab,
                    'nama_regulasi' => $nama_regulasi,
                    'nama_lhp' => $nama_lhp,
                    'status' => $status,
                    'satuan' => $satuan,
                    'method' => $method,
                    'created_by' => $created_by
                ];

                $filtered = null;
                $tempQuery = Parameter::query();
                foreach ($steps as $key => $val) {
                    $tempQuery->where($key, $val);
                    $count = $tempQuery->count();
                    if ($count === 1) {
                        $filtered = $tempQuery->first();
                        break;
                    }
                }

                if ($filtered) {
                    // Bandingkan isi dengan row
                    $differences = [];
                    foreach ($steps as $key => $expected) {
                        if ($filtered->$key != $expected) {
                            $differences[$key] = [
                                'expected' => $expected,
                                'actual' => $filtered->$key
                            ];
                        }
                    }

                    $detailedDifferences[] = [
                        'row' => $index,
                        'id' => $filtered->id,
                        'difference' => $differences
                    ];
                    $notFound[] = $index;
                } else {
                    $notFound[] = $index;
                    $detailedDifferences[] = [
                        'row' => $index,
                        'difference' => 'Data tidak ditemukan atau terlalu banyak kemungkinan'
                    ];
                }
            }
        }

        fclose($file);

        return response()->json([
            'total_checked' => count($found) + count($notFound),
            'start_from_row' => 2,
            // 'found_rows' => $found,
            'total_not_found_rows' => count($notFound),
            'differences' => $detailedDifferences
        ]);
    }



    private function getIdKategori($kategori)
    {
        $kategori = strtolower($kategori);
        switch ($kategori) {
            case 'air':
                return 1;
            case 'udara':
                return 4;
            case 'emisi':
                return 5;
            case 'pangan':
                return 9;
            default:
                return 0;
        }
    }

    
}
