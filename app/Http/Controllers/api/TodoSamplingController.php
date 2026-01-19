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

        $result = [];

        foreach ($samples as $sample) {
            $orderHeader = $sample->orderHeader;
            
            if (!$orderHeader) {
                continue;
            }

            // Cari jadwal yang cocok berdasarkan no_order
            $matchedJadwal = $getJadwal->firstWhere('orderHeader.no_order', $orderHeader->no_order);
            
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
        
                    $targetKategori = $sampleKategoriNama . ' - ' . $sampleNoSampelCode;
                    
                    // Cocokkan kategori dan no sampel
                    // if($orderHeader->no_order == 'KDKI012502' && $sampleNoSampelCode == '012'){
                    //     dd($jadwalNoSampel);
                    // }
                    if ($jadwalKategoriNama === $sampleKategoriNama && 
                        $jadwalNoSampel === $sampleNoSampelCode) {
                        $isJadwalExist = true;
                        break;
                    }
                }
            }

            // Buat unique key untuk grouping (jika diperlukan)
            $key = $orderHeader->no_order . '_' . $sample->id;

            $result[] = [
                'id' => $sample->id,
                'id_cabang' => $matchedJadwal->id_cabang ?? '-',
                'no_document' => $orderHeader->no_document ?? '-',
                'nama_perusahaan' => $orderHeader->nama_perusahaan ?? '-',
                'no_order' => $orderHeader->no_order,
                'no_sampel' => $sample->no_sampel,
                'kategori' => $matchedJadwal->kategori ?? $sample->kategori_3 ?? '-',
                'is_jadwal_exist' => $isJadwalExist,
                'tanggal' => $sample->tanggal_sampling,
            ];
        }

        $data = collect($result)->unique('no_order')->values()->all();

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
            ->rawColumns(['status_jadwal'])
            ->make(true);
    }
}