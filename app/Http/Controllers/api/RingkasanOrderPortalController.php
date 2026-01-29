<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\{OrderHeader,QuotationKontrakH,QuotationNonKontrak,LinkLhp,Invoice,GenerateLink};
\Carbon\Carbon::setLocale('id');
class RingkasanOrderPortalController extends Controller
{
    // Di dalam class OrderHeader
    public function getRingkasanOrder(Request $request)
    {
        try {
            $cek =$request->cek;
            $ambilDB=null;
            $chekToken =GenerateLink::where('token',$request->token)
            ->where('quotation_status','ringkasan_order')
            ->first();
            
            if($chekToken !== null){
                $idTokenRingkasan = $chekToken->id_quotation;
                $ambilDB =DB::table('link_ringkasan_order')->where('id',$idTokenRingkasan)->first();
                
            }
            // dd(strlen($request->token));
            if($ambilDB == null)
            {
                return response()->json(["message"=>"data belum dimuat"],500);
            }
            
            // 1. Ambil Header dan Eager Load
            $orderHeader = OrderHeader::with(['orderDetail', 'samplingPlan', 'jadwal'])
                ->where('no_document', $ambilDB->no_quotation)
                ->where('no_order', $ambilDB->no_order)
                ->first();
            if (!$orderHeader) {
                return response()->json(['message' => 'Data Order tidak ditemukan'], 404);
            }

            // let him coock bro!!
            //step 1 ambil samplingplan (extisting)
            $groupByLogic = function ($item) {
                // 1. Cek apakah item punya 'periode_kontrak' (Milik Sampling Plan)
                if (isset($item->periode_kontrak) && !empty($item->periode_kontrak)) {
                    return $item->periode_kontrak;
                }
                
                // 2. Cek apakah item punya 'periode' (Milik Detail/Jadwal)
                if (isset($item->periode) && !empty($item->periode)) {
                    return $item->periode;
                }

                // 3. Jika tidak punya keduanya, atau kosong
                return 'non-contract';
            };
            $plansByPeriod   = $orderHeader->samplingPlan->groupBy($groupByLogic);
            //step 2 ambil  detail NON-SD
            $detailsSamplingByPeriod = $orderHeader->orderDetail
                                ->where('is_active',1)
                                ->where('kategori_1','!=','SD')
                                ->groupBy($groupByLogic);
            //step 3 ambil detail khusus SD
            $detailsSDByPeriod =$orderHeader->orderDetail
                                ->where('is_active',1)
                                ->where('kategori_1','SD')
                                ->groupBy($groupByLogic);
            
            //step 4 ambil jadwal (extisting)
            $jadwalByPeriod  = $orderHeader->jadwal
                        ->where('is_active',1)
                        ->groupBy($groupByLogic);
            

            $allPeriods = $plansByPeriod->keys()
                ->merge($detailsSamplingByPeriod->keys())
                ->merge($detailsSDByPeriod->keys())
                ->unique()
                ->sort()
                ->values();
            
                $getPeriodeAktif=[];
                // 3. Looping & Formatting
                $ringkasanPerPeriode = $allPeriods->map(function ($periode) use ($plansByPeriod, $detailsSamplingByPeriod, $detailsSDByPeriod, $jadwalByPeriod,&$getPeriodeAktif) {
                        
                        if ($periode === 'non-contract') {
                            $periodeLabel = 'Non - Kontrak / Ad-hoc';
                            $cleanPeriode = 'non-contract';
                            $plan = null;
                        } else {
                            $periodeLabel = \Carbon\Carbon::parse($periode)->translatedFormat('F Y');
                            $cleanPeriode = $periode;
                            $plan = $plansByPeriod->get($periode) ? $plansByPeriod->get($periode)->first() : null;
                        }

                    // Ambil Data per Periode
                    $plan           = $plansByPeriod->get($periode) ? $plansByPeriod->get($periode)->first() : null;
                    $itemsSampling  = $detailsSamplingByPeriod->get($periode) ?? collect([]);
                    $itemsSD        = $detailsSDByPeriod->get($periode) ?? collect([]);
                    $jadwals        = $jadwalByPeriod->get($periode) ?? collect([]);

                    // --- LOGIC PENENTUAN STATUS SAMPLING ---
                    // Prioritas: Jika ada 1 saja item Sampling, maka statusnya Sampling (Butuh Jadwal).
                    // Jika tidak ada item Sampling tapi ada item SD, maka Self Delivery.
                    $isSampling     = $itemsSampling->isNotEmpty();
                    $isSelfDelivery = $itemsSD->isNotEmpty();
                    
                    $statusSampling = '-';
                    if ($isSampling) {
                        $statusSampling = 'Sampling';
                    } elseif ($isSelfDelivery) {
                        $statusSampling = 'SD';
                    }
                    
                    // Gabungkan item untuk list no sampel & hitung titik (Sampling + SD bisa jadi satu report jika perlu)
                    // Atau bisa dipisah sesuai kebutuhan. Di sini saya gabung untuk rekap titik.
                    $allItems = $itemsSampling->merge($itemsSD);

                    // --- 1. LOGIC LIST NO SAMPEL & KATEGORI (Berlaku untuk keduanya) ---
                    $listNoSampel = $allItems->pluck('no_sampel')->unique()->filter()->values()->all();
                    $listKategori = $allItems->pluck('kategori_3')->unique()->filter()->values()->all();

                    // --- 2. LOGIC REKAP TITIK ---
                    $rekapTitikPerKategori = $allItems->groupBy('kategori_3')
                        ->map(function ($items, $namaKategori){
                            if (empty($namaKategori)) return null;
                            $firstItem = $items->first();
                            return [
                                'kategori'     => explode('-',$namaKategori)[1],
                                'jumlah_titik' => $items->unique('no_sampel')->count(),
                                'status_sp' =>  $firstItem->kategori_1
                            ];
                        })->filter()->values()->all();

                    // --- 3. LOGIC REKAP JADWAL (Sweet Logic Branching) ---
                    
                    $hasilJadwalSampling = [];
                    $hasilJadwalSD       = [];
                    if ($isSampling) {
                            // === LOGIC A: JIKA SAMPLING (Codingan Lama Anda) ===
                            $hasilJadwalSampling = $jadwals->groupBy(function($item) {
                                return $item->tanggal . '|' . $item->periode; // Grouping key
                            })->map(function ($groupItems) {
                                $first = $groupItems->first();
                                
                                // Logic gabung nama sampler
                                $joinedSampler = $groupItems->pluck('sampler')->filter()->unique()->implode(', ');
                                
                                // Parse Kategori JSON
                                $kategoriJson = json_decode($first->kategori);
    
                                return [
                                    'tanggal'      => $first->tanggal,
                                    'tanggalLabel' => \Carbon\Carbon::parse($first->tanggal)->translatedFormat('d F Y'),
                                    'periode'      => $first->periode,
                                    'kategori'     => $kategoriJson,
                                    'sampler'      => $joinedSampler,
                                    'jam_mulai'    => $first->jam_mulai,
                                    'jam_selesai'  => $first->jam_selesai,
                                    'jumlah_titik' => is_countable($kategoriJson) ? count($kategoriJson) : 0,
                                    'status' => 'sampling'
                                ];
                            })->values()->all();
    
                        }
                        if ($isSelfDelivery) {
                                $hasilJadwalSD = [
                                    [
                                        'keterangan' => 'Sampel diantar oleh Pelanggan',
                                        'tanggalLabel' => '-', // Atau ambil tanggal dari detail jika ada
                                        'sampler' => 'Pelanggan',
                                        'status' => 'SD',
                                    ]
                                ];
                            }
                   
                    
                    $rekapJadwal = array_merge($hasilJadwalSampling, $hasilJadwalSD);
                    
                    $periodeLabel = "-";
                    if($periode !== 'non-contract') {$periodeLabel = \Carbon\Carbon::parse($periode)->translatedFormat('F Y');};
                    // --- RETURN FINAL ---
                    array_push($getPeriodeAktif,$periode);
                    return [
                        'periode'           => $periode,
                        'periodeLabel'      => $periodeLabel,
                        'status_sampling'   => $statusSampling, // <--- Column Baru
                        'status_quotation'  => $plan ? $plan->status_quotation : '-',
                        'status_jadwal_plan'=> $plan ? $plan->status_jadwal : '-',
                        'list_no_sampel'    => $listNoSampel,
                        'jumlah_titik'      => $rekapTitikPerKategori,
                        'list_kategori'     => $listKategori,
                        'rekapJadwal'       => $rekapJadwal
                    ];
            });

            
            // file summary
            $fileName = null;
            $jadwalFile = null;
            $fileLinkLhp=[];
            $noInvoice =[];
            if (explode('/', $ambilDB->no_quotation)[1] == 'QTC'){
                $searchFileName = QuotationKontrakH::where('no_document',$ambilDB->no_quotation)
                ->select('filename','jadwalfile')
                ->where('flag_status','ordered')
                ->where('is_active',true)
                ->first();
                if($searchFileName != null){
                    $fileName =$searchFileName->filename;
                    $jadwalFile = $searchFileName->jadwalfile;
                }
            }else{
                $searchFileName = QuotationNonKontrak::where('no_document',$ambilDB->no_quotation)
                ->select('filename','jadwalfile')
                ->where('flag_status','ordered')
                ->where('is_active',true)
                ->first();
                if($searchFileName != null){
                    $fileName =$searchFileName->filename;
                    $jadwalFile = $searchFileName->jadwalfile;
                }
               
            }
            
            // 1. Inisialisasi Query Builder
            $query = LinkLhp::query(); 
            // 2. Tambahkan Filter
            $query->where('no_quotation', $ambilDB->no_quotation);
            // 3. Cek Tipe QTC
            // Gunakan empty check untuk array explode agar aman
            $parts = explode('/', $ambilDB->no_quotation);
            if (isset($parts[1]) && $parts[1] == 'QTC'){
                // Pastikan $getPeriodeAktif sudah array (hasil fix sebelumnya)
                $query->whereIn('periode', $getPeriodeAktif);
            }
            // 4. Select & Execute
            // Filter select langsung di chain ke $query
            $resultLinkLhp = $query->select('no_quotation', 'link', 'periode')
            ->orderBy('periode', 'asc')->get();
            // 5. Olah Hasil
            // Perbaikan typo >isNotEmpty() menjadi ->isNotEmpty()
            if ($resultLinkLhp->isNotEmpty()) {
                foreach ($resultLinkLhp as $link) {
                    $periodeLabel = "-";
                    if($link->periode !== null) {$periodeLabel = \Carbon\Carbon::parse($link->periode)->translatedFormat('F Y');};
                    $dataPush = [
                        'no_quotation' => $link->no_quotation,
                        'periode'      => $link->periode,
                        'periodeLabel'  => $periodeLabel,
                        'link'         => $link->link
                    ];
                    array_push($fileLinkLhp, $dataPush);
                }
            }
             $searchInvoice = Invoice::where('no_quotation',$ambilDB->no_quotation)
             ->select('no_invoice','filename','upload_file')
             ->where('is_active',true)
             ->get();
             if($searchInvoice->isNotEmpty()){
                foreach($searchInvoice as $inv){
                    $fileNameInvoice = $inv->upload_file ?? $inv->filename;
                    $realPath = public_path('invoice/' . $fileNameInvoice);
                    $encodedContent = $this->encode($realPath);
                    $data =[
                        "nomor_invoice" =>$inv->no_invoice,
                        "filename" => $encodedContent
                    ];
                    array_push($noInvoice,$data);
                }
                
             }
             
             $absolutePathQuot = public_path('quotation/' . $fileName);
             $absolutePathJadwal = public_path('quotation/' . $jadwalFile);
             $filenameQuotationEndcode = $this->encode($absolutePathQuot);
             $filenameJadwalEndcode = $this->encode($absolutePathJadwal);
            //   dd($absolutePathQuot,$fileName,$filenameQuotationEndcode);
             
            return response()->json([
                'info_dasar' => [
                    'no_order'      => $orderHeader->no_order,
                    'no_quotation'  => $orderHeader->no_document,
                    'klien'         => $orderHeader->nama_perusahaan,
                    'alamat'         => $orderHeader->alamat_sampling,
                    'total_periode' => $allPeriods->count(),
                ],
                'detail_per_periode' => $ringkasanPerPeriode,
                'file_name' =>$filenameQuotationEndcode,
                'jadwal_file' =>$filenameJadwalEndcode,
                'link_lhp' =>$fileLinkLhp,
                'nomor_invoice' =>$noInvoice,
                'typQt' => explode('/', $ambilDB->no_quotation)[1],
                'status' =>true
            ],200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'error'=>$th->getMessage(),
                'line'=>$th->getLine(),
                'file'=>$th->getFile(),
                'panjang_token' =>strlen($request->token)],500);
        }
    }

    private function encode ($filename)
    {
        if (is_file($filename)) {
            $fileContents = file_get_contents($filename);
            if ($fileContents !== false) {
                return 'data:application/pdf;base64,' . base64_encode($fileContents);
            }
        }
        // Return null jika file tidak ditemukan
        return null;
    }
}