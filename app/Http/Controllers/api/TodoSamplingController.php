<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\Jadwal;
use DataTables;
use Illuminate\Http\Request;

class TodoSamplingController extends Controller
{
    public function index(Request $request)
    {
        $getJadwal = Jadwal::with('orderHeader')->whereNotNull('tanggal')
            ->where('is_active', true)
            ->whereDate('tanggal', $request->tanggal_sampling)->get();
        
        $samples = OrderDetail::with('orderHeader')
            ->whereNotNull('tanggal_sampling')
            ->where('is_active', true)
            ->whereDate('tanggal_sampling', $request->tanggal_sampling)
            ->get();

        $groupedResults = [];

        foreach ($samples as $sample) {
            $orderHeader = $sample->orderHeader;
            
            if (!$orderHeader) {
                continue;
            }

            $noOrder = $orderHeader->no_order;

            // Cari jadwal yang cocok berdasarkan no_order
            $matchedJadwal = $getJadwal->firstWhere('orderHeader.no_order', $noOrder);
            
            $isJadwalExist = false;
            
            if ($matchedJadwal) {
                // Parse kategori dari jadwal (format JSON array)
                $kategoriJadwal = json_decode($matchedJadwal->kategori, true) ?? [];
                
                // Cek apakah ada kategori yang cocok di jadwal
                foreach ($kategoriJadwal as $kategori) {
                    // Format kategori di jadwal: "Udara Ambient - 001"
                    $kategoriParts = explode(' - ', $kategori);
                    $jadwalKategoriNama = trim($kategoriParts[0] ?? '');
                    $jadwalNoSampel = trim($kategoriParts[1] ?? '');
                    
                    // Ekstrak kategori dari sample (format: "31-Udara Ambient")
                    $sampleKategoriParts = explode('-', $sample->kategori_3 ?? '');
                    $sampleKategoriNama = trim($sampleKategoriParts[1] ?? '');
                    
                    // Ekstrak no_sampel dari sample (format: "ORDER123/001")
                    $sampleNoSampelParts = explode('/', $sample->no_sampel ?? '');
                    $sampleNoSampelCode = trim($sampleNoSampelParts[1] ?? '');

                    // Cocokkan kategori dan no sampel
                    if ($jadwalKategoriNama === $sampleKategoriNama && 
                        $jadwalNoSampel === $sampleNoSampelCode) {
                        $isJadwalExist = true;
                        break;
                    }
                }
            }

            // Group by no_order
            if (!isset($groupedResults[$noOrder])) {
                $groupedResults[$noOrder] = [
                    'id' => $sample->id,
                    'id_cabang' => $matchedJadwal->id_cabang ?? '-',
                    'no_document' => $orderHeader->no_document ?? '-',
                    'nama_perusahaan' => $orderHeader->nama_perusahaan ?? '-',
                    'no_order' => $noOrder,
                    'kategori' => $matchedJadwal->kategori ?? $sample->kategori_3 ?? '-',
                    'is_jadwal_exist' => $isJadwalExist,
                    'tanggal' => $sample->tanggal_sampling,
                    'samples' => [] // untuk tracking
                ];
            }

            // Update is_jadwal_exist menjadi true jika ada salah satu sample yang memiliki jadwal
            if ($isJadwalExist) {
                $groupedResults[$noOrder]['is_jadwal_exist'] = true;
            }

            // Simpan info sample (opsional, untuk debugging)
            $groupedResults[$noOrder]['samples'][] = [
                'no_sampel' => $sample->no_sampel,
                'has_jadwal' => $isJadwalExist
            ];
        }

        $data = array_values($groupedResults);

        // Jika menggunakan DataTables server-side
        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->addColumn('status_jadwal', function($row) {
                if ($row['is_jadwal_exist']) {
                    return '<span class="badge badge-success">Ada Jadwal</span>';
                }
                return '<span class="badge badge-warning">Belum Ada Jadwal</span>';
            })
            ->addColumn('jumlah_sampel', function($row) {
                return count($row['samples']);
            })
            ->addColumn('sampel_terjadwal', function($row) {
                $terjadwal = collect($row['samples'])->where('has_jadwal', true)->count();
                return $terjadwal . '/' . count($row['samples']);
            })
            ->rawColumns(['status_jadwal'])
            ->make(true);
    }
}