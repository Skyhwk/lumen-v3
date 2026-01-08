<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

use App\Models\OrderHeader;
use App\Models\DailyQsd;
use App\Models\Invoice;
use App\Models\MasterJabatan;

use App\Services\GetBawahan;

class DailyTotalOrderController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $request->tanggal;
        $qt_non_kontrak = OrderHeader::where('no_document', 'like', '%QT/%')
            ->where('is_active', 1)
            ->where('tanggal_order', $tanggal)
            ->pluck('no_document')->toArray();

        $dataNonKontrak = DailyQsd::whereIn('no_quotation', $qt_non_kontrak)
            ->select('sales_id', 'no_quotation', 'total_revenue', 'biaya_akhir')
            ->get()->toArray();

        $dataKontrak = DailyQsd::select('sales_id', 'no_quotation', 'total_revenue', 'biaya_akhir')
            ->where('tanggal_sampling_min', $tanggal)
            ->where('no_quotation', 'LIKE', '%QTC/%')
            ->get()->toArray();
        
        $allData = array_merge($dataNonKontrak, $dataKontrak);

        $result = [];
        foreach ($allData as $row) {
            $sales_id = $row['sales_id'];
            if (!isset($result[$sales_id])) {
                $result[$sales_id] = [
                    'sales_id' => $sales_id,
                    'jumlah_order' => 0,
                    'total_revenue' => 0,
                    'biaya_akhir' => 0,
                ];
            }
            $result[$sales_id]['jumlah_order'] += 1;
            $result[$sales_id]['total_revenue'] += floatval($row['total_revenue']);
            $result[$sales_id]['biaya_akhir'] += floatval($row['biaya_akhir']);
        }

        $result = array_values($result);

        $dataBawahan = Getbawahan::where('id', 890)
            ->get()
            ->filter(function($q){
                return $q->id != 890;
            })
            ->map(function($q){
                $cekJabatan = MasterJabatan::where('id', $q->id_jabatan)->select('nama_jabatan')->first();
                return[
                    'id' => $q->id,
                    'nama_lengkap' => $q->nama_lengkap,
                    'grade' => $q->grade,
                    'id_jabatan' => $q->id_jabatan,
                    'atasan_langsung' => $q->atasan_langsung,
                    'jabatan' => $cekJabatan->nama_jabatan ?? null
                ];
            })->values()->toArray();

        $strukturSales = $this->buildStrukturSales($dataBawahan);

        return response()->json([
            'strukturSales' => $strukturSales,
            'penjualan' => $result
        ], 200);
    }

    private function buildStrukturSales(array $data)
    {
        // mapping by id biar cepat
        $byId = [];
        foreach ($data as $row) {
            $row['atasan_langsung'] = json_decode($row['atasan_langsung'], true) ?? [];
            $byId[$row['id']] = $row;
        }
    
        // cari ROOT (manager / spv yg tidak punya manager di data)
        $roots = [];
        foreach ($byId as $row) {
            if($row['id_jabatan'] == 22) continue;
            if (!in_array($row['grade'], ['MANAGER', 'SUPERVISOR'])) {
                continue;
            }
    
            $punyaAtasanDiData = false;
            foreach ($row['atasan_langsung'] as $atasanId) {
                if (isset($byId[$atasanId]) && $byId[$atasanId]['grade'] === 'MANAGER') {
                    $punyaAtasanDiData = true;
                    break;
                }
            }
    
            if (!$punyaAtasanDiData) {
                $roots[$row['id']] = [
                    'id'   => $row['id'],
                    'nama' => $row['nama_lengkap'],
                    'grade'=> $row['grade'],
                    'jabatan' => $row['jabatan'],
                    'child'=> []
                ];
            }
        }
    
        // helper cari bawahan langsung
        $getBawahan = function ($atasanId) use ($byId) {
            $out = [];
            foreach ($byId as $row) {
                if (in_array($atasanId, $row['atasan_langsung'])) {
                    $out[] = $row;
                }
            }
            return $out;
        };
    
        // bangun struktur
        foreach ($roots as $rootId => &$root) {
    
            // ROOT MANAGER → MANAGER > SUPERVISOR > STAFF
            if ($root['grade'] === 'MANAGER') {
    
                $supervisors = $getBawahan($rootId);
                foreach ($supervisors as $spv) {
                    if ($spv['grade'] !== 'SUPERVISOR') continue;
    
                    $spvNode = [
                        'id'   => $spv['id'],
                        'nama' => $spv['nama_lengkap'],
                        'grade'=> 'SUPERVISOR',
                        'jabatan' => $spv['jabatan'],
                        'child'=> []
                    ];
    
                    $staffs = $getBawahan($spv['id']);
                    foreach ($staffs as $staff) {
                        if (
                            $staff['grade'] === 'STAFF' &&
                            in_array($staff['id_jabatan'], [24, 148])
                        ) {
                            $spvNode['child'][] = [
                                'id'   => $staff['id'],
                                'nama' => $staff['nama_lengkap'],
                                'grade'=> 'STAFF',
                                'id_jabatan' => $staff['id_jabatan'],
                                'jabatan' => $staff['jabatan']
                            ];
                        }
                    }
    
                    if (!empty($spvNode['child'])) {
                        $root['child'][] = $spvNode;
                    }
                }
    
            // ROOT SUPERVISOR → SUPERVISOR > STAFF
            } else {
    
                $staffs = $getBawahan($rootId);
                foreach ($staffs as $staff) {
                    if (
                        $staff['grade'] === 'STAFF' &&
                        in_array($staff['id_jabatan'], [24, 148])
                    ) {
                        $root['child'][] = [
                            'id'   => $staff['id'],
                            'nama' => $staff['nama_lengkap'],
                            'grade'=> 'STAFF',
                            'id_jabatan' => $staff['id_jabatan'],
                            'jabatan' => $staff['jabatan']
                        ];
                    }
                }
            }
        }
    
        return array_values($roots);
    }
}
