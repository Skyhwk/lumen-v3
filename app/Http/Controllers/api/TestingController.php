<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{
    QuotationKontrakH,
    QuotationKontrakD,
    SamplingPlan,
    QuotationNonKontrak,
    Jadwal,
    AnalystFormula,
    Colorimetri,
    OrderHeader,
    OrderDetail,
    Invoice,
    PersiapanSampelHeader,
    PersiapanSampelDetail,
    LhpsAirHeader,
    LhpsAirDetail,
    LhpsAirCustom,
    MasterBakumutu,
    HargaParameter,
    KelengkapanKonfirmasiQs,
    Parameter,
    DataLapanganAir,
    LhpUdaraPsikologiHeader,
    SampelTidakSelesai,
    MasterKaryawan,
    QrDocument,
    DataLapanganPartikulatMeter,
    DetailSenyawaVolatile,
    DetailLingkunganHidup,
    DataLapanganDirectLain,
    DetailLingkunganKerja,
    DetailMicrobiologi,
    DataLapanganKebisinganPersonal,
    DataLapanganKebisingan,
    DataLapanganCahaya,
    DataLapanganGetaran,
    DataLapanganGetaranPersonal,
    DataLapanganIklimPanas,
    DataLapanganIklimDingin,
    DataLapanganSwab,
    DataLapanganErgonomi,
    DataLapanganDebuPersonal,
    DataLapanganMedanLM,
    DataLapanganSinarUV,
    DataLapanganPsikologi,
    DataLapanganEmisiKendaraan,
    DataLapanganEmisiCerobong,
    DataLapanganIsokinetikHasil,
    Gravimetri,
    MasterPelanggan,
    Titrimetri,
    WsValueAir,//batas
    DataLapanganEmisiOrder,
    DataLapanganIsokinetikBeratMolekul,
    DataLapanganIsokinetikKadarAir,
    DataLapanganIsokinetikPenentuanKecepatanLinier,
    DataLapanganIsokinetikSurveiLapangan,
    DataLapanganKebisinganBySoundMeter,
    DataLapanganKecerahan,
    DataLapanganLapisanMinyak,
    DataLapanganMicrobiologi,
    DataLapanganSampah,
    DataLapanganSenyawaVolatile,
    DataLapanganUnion,
    DataLimbah,
    DataPsikologi,
    DetailFlowMeter,
    DetailSoundMeter,
    DailyQsd,
    SertifikatWebinarHeader,
    SertifikatWebinarDetail,
    LayoutCertificate,
    JenisFont,
    TemplateBackground,
    MasterTargetSales
};
use App\Services\{
    GetAtasan,
    SamplingPlanServices,
    RenderSamplingPlan,
    JadwalServices,
    RenderInvoice,
    RenderInvoiceTitik,
    GeneratePraSampling,
    GenerateQrDocumentLhp,
    GenerateWebinarSertificate,
    LhpTemplate,
    RandomSalesAssign,
    SendEmail,
    GetBawahan,
    SnapshotPersiapanService
};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;
use Yajra\DataTables\Facades\DataTables;
use App\Services\SalesDailyQSD;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Mpdf;

Carbon::setLocale('id');



class TestingController extends Controller
{
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
                                'id_jabatan' => $staff['id_jabatan']
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
                            'id_jabatan' => $staff['id_jabatan']
                        ];
                    }
                }
            }
        }
    
        return array_values($roots);
    }
    private $indoMonths = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maret',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'agustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'desember'
    ];
    private $categoryStr;
    
    public function __construct()
    {
        Carbon::setLocale('id');

        $this->categoryStr = config('kategori.id');
    }

    public function show(Request $request)
    { 
        
        try {
            switch ($request->menu) {
                case'sales-perview' :
                    // 1. Persiapan Variabel Waktu & Parameter
                    $startOfMonth = Carbon::now()->startOfMonth();
                    $now = Carbon::now();
                    $currentYear = $now->year;
                    $currentMonth = $now->month;
                    $currentPeriode = $now->format('Y-m'); // Format tahun-bulan (contoh: 2026-02)

                    $statusNewExcluded = ['ordered', 'rejected', 'void'];
                    $statusExistIncluded = 'ordered';
                    $karyawanId=$request->idSales;

                    // =========================================================================
                    // === METRIC 1 & 2: COUNT (All QT New & All QT Exist) ===
                    // Logic waktu: menggunakan tanggal_penawaran (Header/Non-Kontrak)
                    // =========================================================================

                    // 1. All QT New (Count)
                    $countKontrakNew = QuotationKontrakH::where('sales_id', $karyawanId)
                        ->whereYear('tanggal_penawaran', $currentYear)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->whereNotIn('flag_status', $statusNewExcluded)
                        ->count();

                    $countNonKontrakNew = QuotationNonKontrak::where('sales_id', $karyawanId)
                        ->whereYear('tanggal_penawaran', $currentYear)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->whereNotIn('flag_status', $statusNewExcluded)
                        ->count();
                    $totalCountQtNew = $countKontrakNew + $countNonKontrakNew;
                
                    // 2. All QT Exist (Count)
                    $countKontrakExist = QuotationKontrakH::where('sales_id', $karyawanId)
                        ->whereYear('tanggal_penawaran', $currentYear)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->where('flag_status', $statusExistIncluded)
                        ->count();
                    $countNonKontrakExist = QuotationNonKontrak::where('sales_id', $karyawanId)
                        ->where('tanggal_penawaran', '>=', $startOfMonth)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->where('flag_status', $statusExistIncluded)
                        ->count();
                    $totalCountQtExist = $countKontrakExist + $countNonKontrakExist;
                    // =========================================================================
                    // === METRIC 3 & 4: REVENUE (Amount QT New & Amount QT Exist) ===
                    // Logic waktu Kontrak: menggunakan periode_kontrak di tabel Detail
                    // Logic waktu Non-Kontrak: menggunakan tanggal_penawaran
                    // =========================================================================

                    // 3. Amount QT New (Revenue)
                    // KONTRAK: Filter header sales_id & status, lalu filter relasi detail berdasarkan periode_kontrak
                    $amountKontrakNew = QuotationKontrakH::where('sales_id', $karyawanId)
                        ->whereYear('tanggal_penawaran', $currentYear)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->whereNotIn('flag_status', $statusNewExcluded)
                        ->with(['detail' => function($query) use ($currentPeriode) {
                            $query->where('periode_kontrak', $currentPeriode);
                        }])
                        ->get()
                        // Menggabungkan semua detail yang lolos filter dan menjumlahkan rumusnya
                        ->flatMap->details
                        ->sum(function ($detail) {
                            return $detail->biaya_akhir - $detail->total_ppn;
                        });
                    
                    // NON-KONTRAK: Filter langsung di tabel yang sama
                    $amountNonKontrakNew = QuotationNonKontrak::where('sales_id', $karyawanId)
                        ->whereYear('tanggal_penawaran', $currentYear)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->whereNotIn('flag_status', $statusNewExcluded)
                        ->sum(DB::raw('biaya_akhir - total_ppn'));

                    $totalAmountQtNew = $amountKontrakNew + $amountNonKontrakNew;
                    // 4. Amount QT Exist (Revenue)
                    // KONTRAK
                    $amountKontrakExist = QuotationKontrakH::where('sales_id', $karyawanId)
                        ->where('flag_status', $statusExistIncluded)
                        ->with(['detail' => function($query) use ($currentPeriode) {
                            $query->where('periode_kontrak', $currentPeriode);
                        }])
                        ->get()
                        ->flatMap->details
                        ->sum(function ($detail) {
                            return $detail->biaya_akhir - $detail->total_ppn;
                        });

                    // NON-KONTRAK
                    $amountNonKontrakExist = QuotationNonKontrak::where('sales_id', $karyawanId)
                        ->whereYear('tanggal_penawaran', $currentYear)
                        ->whereMonth('tanggal_penawaran', $currentMonth)
                        ->where('flag_status', $statusExistIncluded)
                        ->sum(DB::raw('biaya_akhir - total_ppn'));

                    $totalAmountQtExist = $amountKontrakExist + $amountNonKontrakExist;
                    //================================================================
                    //======================= ORDER SECTION ==========================
                    //================================================================
                    $newOrders = OrderHeader::where('sales_id', $karyawanId)
                        ->where('tanggal_order', '>=', $startOfMonth)
                        ->whereNotIn('id_pelanggan', function($query) use ($startOfMonth) {
                            $query->select('id_pelanggan')
                                ->from('order_header')
                                ->where('tanggal_order', '<', $startOfMonth)
                                ->where('flag_status', 'ordered'); // Pastikan hanya menghitung yang sukses
                        })
                        ->count();
                    $existingOrders = OrderHeader::where('sales_id', $karyawanId)
                        ->where('tanggal_order', '>=', $startOfMonth)
                        ->whereIn('id_pelanggan', function($query) use ($startOfMonth) {
                            $query->select('id_pelanggan')
                                ->from('order_header')
                                ->where('tanggal_order', '<', $startOfMonth)
                                ->where('flag_status', 'ordered');
                        })
                        ->count();
                    // 1. Ambil semua data sekaligus (hanya ambil kolom yang dibutuhkan agar ringan)
                    $dailyQsd = DailyQsd::with('orderHeader.orderDetail')
                        ->where('sales_id', $karyawanId)
                        ->whereYear('tanggal_kelompok', $currentYear)
                        ->whereMonth('tanggal_kelompok', $currentMonth)
                        ->get()
                        ->map(function ($qsd) {
                            if ($qsd->periode) {
                                $orderDetail = optional($qsd->orderHeader)->orderDetail ? $qsd->orderHeader->orderDetail->filter(fn($od) => $od->periode === $qsd->periode)->values() : collect();
                                if ($orderDetail->isNotEmpty()) {
                                    $qsd->orderHeader->setRelation('orderDetail', $orderDetail);
                                }
                            }
                            return $qsd;
                        });
                    $currQsd = $dailyQsd->filter(fn($qsd) => Carbon::parse($qsd->tanggal_kelompok)->month == $currentMonth);
                    $currRevenue = $currQsd->sum('total_revenue');
                    $targetSales = MasterTargetSales::where([
                        'karyawan_id' => $karyawanId,
                        'is_active'   => true,
                        'tahun'       => $currentYear
                    ])->latest()->first();
                    
                    $currTarget = 0;
                    $currAchieved = 0;
                    
                    if ($targetSales) {
                        $currTargetCategory = collect($targetSales->{$this->indoMonths[$currentMonth]})->filter(fn($value) => $value > 0);

                        $currAchievedCategory = $currTargetCategory->map(
                            function ($_, $category) use ($currQsd, $currTargetCategory) {
                                $target = $currTargetCategory[$category];
                                $achieved = $currQsd->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)->filter(fn($orderDetail) => collect($this->categoryStr[$category])->contains($orderDetail->kategori_3))->count();

                                return $target && $achieved ? floor($achieved / $target) : 0;
                            }
                        );
                        $currAchieved = $currAchievedCategory->sum() == 0 ? 1 : $currAchievedCategory->sum();
                        $currTarget = $currTargetCategory->count();
                    }
                    //target
                    
                    $currTargetKategori = $currAchieved . '/' . $currTarget;
                    $currNewCustomer = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->count();
                    $currExistCustomer = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->count();
                    $period = Carbon::create($currentYear, $currentMonth, 1)->format('Y-m');
                    $target = json_decode(optional($targetSales)->target ?: '[]', true);
                    $targetAmount = isset($target[$period]) ? $target[$period] : 0;

                    $newCustomerRevenue = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->sum('total_revenue');
                    $existCustomerRevenue = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->sum('total_revenue');

                    $kontrakRevenue = $currQsd->filter(fn($qsd) => $qsd->kontrak == 'C')->sum('total_revenue');
                    $nonKontrakRevenue = $currQsd->filter(fn($qsd) => $qsd->kontrak == 'N')->sum('total_revenue');
                    $dataJson = [
                        'new_customers'               => $currNewCustomer,
                        'exist_customers'            => $currExistCustomer,
                        'all_qt_new'  => $totalCountQtNew,
                        'all_qt_exist'  => $totalCountQtExist,
                        'amoun_qt_new'  => $totalAmountQtNew,
                        'amoun_qt_exist'  => $totalAmountQtExist,
                        'total_order'               => $currRevenue,
                        'target_kategori'             => $currTargetKategori,
                        'revenue'                     => $currRevenue,
                        //'target'                      => $targetAmount,
                        'order_new'                         => $newCustomerRevenue,
                        'order_existing'                    => $existCustomerRevenue,
                        'order_kontrak'                     => $kontrakRevenue,
                        'order_non_kontrak'                 => $nonKontrakRevenue,
                        
                    ];
                    return response()->json(["data"=>$dataJson],200);
                    break;
                default:
                    return response()->json("Menu tidak ditemukanXw", 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(["message" =>$th->getMessage(),"line"=>$th->getLine()],500);
        }
    }
    }
