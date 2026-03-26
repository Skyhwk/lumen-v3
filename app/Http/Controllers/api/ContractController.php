<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\OrderHeader;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class ContractController extends Controller
{

    public function index(Request $request)
    {
        try {
            // $subTahun = substr($request->year, -2); 
            $subTahun = $request->year; 

            $orders = OrderHeader::select(
                            'nama_perusahaan',
                            DB::raw('MAX(wilayah) as wilayah'),
                            // Menggabungkan no_document menjadi string untuk referensi jika perlu
                            DB::raw('GROUP_CONCAT(DISTINCT no_document SEPARATOR ", ") as daftar_no_doc'),
                            DB::raw('SUM(COALESCE(biaya_akhir, 0) - COALESCE(total_ppn, 0)) as summary')
                        )
                        ->where('is_active', 1)
                        ->where('tanggal_penawaran', "LIKE", "%$subTahun-%")
                        ->groupBy('nama_perusahaan')
                        ->orderByDesc('summary')
                        ->get();

                    // 2. Ambil semua no_document unik dari perusahaan tersebut untuk ditarik detailnya
                    // Kita perlu memecah kembali daftar_no_doc jika ada banyak dokumen
                    $allNoDocs = [];
                    foreach ($orders as $o) {
                        $docs = explode(', ', $o->daftar_no_doc);
                        $allNoDocs = array_merge($allNoDocs, $docs);
                    }
                    $allNoDocs = array_unique($allNoDocs);

                    // 3. Tarik semua data Quotation Detail berdasarkan kumpulan no_document tersebut
                    $allQuotations = QuotationKontrakH::with(['detail' => function($q) use ($request) {
                            $q->where('periode_kontrak', 'LIKE', '%' . $request->year . '%');
                        }])
                        ->whereIn('no_document', $allNoDocs)
                        ->get()
                        ->groupBy('no_document');

                    // 4. Inisialisasi Total untuk Footer
                    $bulanTotals = [
                        'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0,
                        'may' => 0, 'june' => 0, 'july' => 0, 'august' => 0,
                        'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
                    ];

                    // 5. Mapping data Bulanan ke setiap baris Perusahaan
                    foreach ($orders as $order) {
                        $myDocs = explode(', ', $order->daftar_no_doc);
                        // Penampung summary bulan untuk perusahaan ini (campuran QT dan QTC)
                        $perusahaanBulan = [
                            'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0,
                            'may' => 0, 'june' => 0, 'july' => 0, 'august' => 0,
                            'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
                        ];

                        foreach ($myDocs as $docNo) {
                            if (str_contains($docNo, 'QTC')) {
                                // SKENARIO KONTRAK: Ambil dari tabel kontrak & detail
                                if (isset($allQuotations[$docNo])) {
                                    $contractSummary = $this->processDetails($allQuotations[$docNo]);
                                    foreach ($contractSummary as $bln => $val) {
                                        $perusahaanBulan[$bln] += $val;
                                    }
                                }
                            } else {
                                // SKENARIO NON-KONTRAK (QT): Ambil langsung dari OrderHeader
                                // Kita perlu ambil data asli baris ini untuk mendapatkan tanggal_penawaran dan total_dpp-nya
                                $rawDoc = OrderHeader::where('no_document', $docNo)
                                    ->where('is_active', 1)
                                    ->first();

                                if ($rawDoc && $rawDoc->tanggal_penawaran) {
                                    $bulanKey = $this->getBulanFromTanggal($rawDoc->tanggal_penawaran);
                                    if ($bulanKey) {
                                        $perusahaanBulan[$bulanKey] += (float) $rawDoc->total_dpp;
                                    }
                                }
                            }
                        }

                        $order->bulan_summary = $perusahaanBulan;

                        // Tambahkan ke total footer
                        foreach ($perusahaanBulan as $key => $val) {
                            $bulanTotals[$key] += $val;
                        }
                    }

            // Pake datatables dari collection
            $orders = $orders->sortByDesc('summary')->values();
            return datatables()->of($orders)
                ->addIndexColumn()
                ->addColumn('bulan_summary', function ($row) {
                    return $row->bulan_summary;
                })
                ->with(array_merge([
                    'total_summary' => $orders->sum('summary'),
                ], collect($bulanTotals)->mapWithKeys(fn($v, $k) => ["total_{$k}" => $v])->toArray()))
                ->make(true);

        } catch (\Throwable $th) {
            dd($th);
        }
    }


    private function getBulanFromTanggal($tanggal) 
    {
        $mapBulan = [
            '01' => 'january', '02' => 'february', '03' => 'march', '04' => 'april',
            '05' => 'may', '06' => 'june', '07' => 'july', '08' => 'august',
            '09' => 'september', '10' => 'october', '11' => 'november', '12' => 'december',
        ];

        $parts = explode('-', $tanggal);
        $bulanNum = $parts[1] ?? null;

        return $mapBulan[$bulanNum] ?? null;
    }
    private function processDetails($quotationHeaders)
    {
        $bulan = [
            'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0,
            'may' => 0, 'june' => 0, 'july' => 0, 'august' => 0,
            'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
        ];

        foreach ($quotationHeaders as $value) {
            foreach ($value->detail as $detail) {
                $bulanStr = explode('-', $detail->periode_kontrak)[1] ?? null;
                switch ($bulanStr) {
                    case '01': $bulan['january'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '02': $bulan['february'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '03': $bulan['march'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '04': $bulan['april'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '05': $bulan['may'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '06': $bulan['june'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '07': $bulan['july'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '08': $bulan['august'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '09': $bulan['september'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '10': $bulan['october'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '11': $bulan['november'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                    case '12': $bulan['december'] += ((float)$detail->biaya_akhir - (float)$detail->total_ppn); break;
                }
            }
        }

        return $bulan;
    }
}