<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\File;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Mpdf;
use App\Models\PengesahanLhp;
use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{OrderHeader, OrderDetail, PersiapanSampelHeader, CoverLhp};

class CoverLhpController extends Controller
{
    public function index()
    {
        $orders = OrderHeader::with('coverLhp')
            ->select('id', 'no_document', 'tanggal_penawaran', 'no_order', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'alamat_sampling')
            ->where('is_active', true)
            ->latest();

        return DataTables::of($orders)->make(true);
    }

    private function getGroupedCFRs($orderHeader, $selectedCFRs = null)
    {
        try {
            $orderDetails = OrderDetail::select('id', 'id_order_header', 'cfr', 'periode', 'no_sampel', 'keterangan_1', 'tanggal_terima', 'status', 'kategori_2', 'kategori_3', 'kategori_1')
                ->where([
                    'id_order_header' => $orderHeader->id,
                    'is_active' => true
                ]);

            if ($selectedCFRs) $orderDetails = $orderDetails->whereIn('cfr', $selectedCFRs);

            $orderDetails = $orderDetails->get();

            $groupedData = $orderDetails->groupBy(['cfr', 'periode'])->map(fn($periodGroups) =>
            $periodGroups->map(fn($itemGroup) => [
                'cfr' => $itemGroup->first()->cfr,
                'periode' => $itemGroup->first()->periode,
                'keterangan_1' => $itemGroup->pluck('keterangan_1')->toArray(),
                'kategori_3' => $itemGroup->pluck('kategori_3')->toArray(),
                'kategori_1' => $itemGroup->pluck('kategori_1')->toArray(),
                'no_sampel' => $itemGroup->pluck('no_sampel')->toArray(),
                'total_no_sampel' => $itemGroup->count(),
                'order_details' => $itemGroup->toArray(),
            ]))->flatten(1)->values();

            return $groupedData;
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function detail(Request $request)
    {
        $orderHeader = OrderHeader::with('persiapanHeader.psDetail')->find($request->id_order_header);

        $groupedData = $this->getGroupedCFRs($orderHeader);

        return response()->json([
            'groupedCFRs' => $groupedData,
            'psHeader' => $orderHeader->persiapanHeader
        ], 200);
    }

    public function generatePdf(Request $request)
    {
        try {
            $orderHeader = OrderHeader::where('no_order', $request->no_order)->first();

            $groupedCFRs = $this->getGroupedCFRs($orderHeader, $request->selectedCfrs);

            if ($groupedCFRs->isEmpty()) return response()->json(['message' => 'Tidak ada lhp yang dipilih'], 400);

            $formattedFirstDate = Carbon::parse($request->tgl_awal)->translatedFormat('d F Y');
            $formattedLastDate = Carbon::parse($request->tgl_akhir)->translatedFormat('d F Y');
            $formattedNowDate = Carbon::now()->translatedFormat('d F Y');

            $PengesahanLhp = PengesahanLhp::where('berlaku_mulai', '<=', Carbon::now())
                    ->orderBy('berlaku_mulai', 'desc')
                    ->first();

            $nama_perilis   = $PengesahanLhp->nama_karyawan ?? 'Abidah Walfathiyyah';
            $jabatan_perilis = $PengesahanLhp->jabatan_karyawan ?? 'Technical Control Supervisor';

            $arrayOfSamplingStatus = $groupedCFRs->map(fn($cfr) => $cfr['kategori_1'])->flatten()->filter(fn($v) => filled($v))->unique()->values()->toArray();

            $detail = [];
            $arrayOfCategories = $groupedCFRs->map(fn($cfr) => $cfr['kategori_3'])->flatten()->unique()->values()->toArray();
            foreach ($arrayOfCategories as $category) {
                $filteredCFRs = $groupedCFRs->filter(fn($item) => in_array($category, $item['kategori_3']));
                $titikCount = $groupedCFRs
                    ->filter(fn($item) => in_array($category, $item['kategori_3']))
                    ->flatMap(fn($item) => $item['keterangan_1']) // gabung semua titik
                    ->count();
                
                $aliases = [
                    // === Udara ===
                    'Udara Lingkungan Kerja' => 'Lingkungan Kerja',
                    'Debu' => 'Lingkungan Kerja',
                    'Pencahayaan' => 'Lingkungan Kerja',
                    'Kebisingan Personal' => 'Lingkungan Kerja',
                    'Frekuensi Radio' => 'Lingkungan Kerja',
                    'Medan Magnet' => 'Lingkungan Kerja',
                    'Medan Listrik' => 'Lingkungan Kerja',
                    'Power Density' => 'Lingkungan Kerja',
                    'Iklim Kerja' => 'Lingkungan Kerja',
                    'Suhu' => 'Lingkungan Kerja',
                    'Kelembapan' => 'Lingkungan Kerja',
                    'Sinar UV' => 'Lingkungan Kerja',
                    'PM 10' => 'Lingkungan Kerja',
                    'Getaran (Lengan & Tangan)' => 'Lingkungan Kerja',
                    'Getaran (Seluruh Tubuh)' => 'Lingkungan Kerja',
                    'Angka Kuman' => 'Lingkungan Kerja',

                    // === Air ===
                    'Air Bersih' => 'Air untuk Keperluan Higiene Sanitasi',
                    'Air Limbah Domestik' => 'Air Limbah',
                    'Air Limbah Industri' => 'Air Limbah',
                    'Air Permukaan' => 'Air Sungai',
                    'Air Kolam Renang' => 'Air Kolam Renang',
                    'Air Higiene Sanitasi' => 'Air untuk Keperluan Higiene Sanitasi',
                    'Air Khusus' => 'Air Reverse Osmosis',
                    'Air Limbah Terintegrasi' => 'Air Limbah',
                ];

                $categoryName = explode('-', $category)[1];
                if (array_key_exists($categoryName, $aliases)) {
                    $categoryName = $aliases[$categoryName];
                }

                $detail[] = "$categoryName - $titikCount Titik";
            }

            $data = (object) [
                'nama_perusahaan' => $orderHeader->nama_perusahaan,
                'alamat_sampling' => $orderHeader->alamat_sampling,
                'periode' => "$formattedFirstDate - $formattedLastDate",
                'no_order' => $orderHeader->no_order,
                'no_quotation' => $orderHeader->no_document,
                'no_bas' => $request->no_bas,
                'status_sampling' => implode(', ', array_map(fn($s) => $s === 'SD' ? 'Sampel Diantar' : 'Sampling', $arrayOfSamplingStatus)),
                'detail' => $detail
            ];

            $directoryPath = public_path() . '/laporan/cover_lhp';
            $filename = 'LHP-' . $request->no_order . '-' . Carbon::now()->format('Ymdhis') . '.pdf';
            $fullPath = $directoryPath . '/' . $filename;

            if (!File::isDirectory($directoryPath)) {
                File::makeDirectory($directoryPath, 0777, true);
            }

            $mpdf = new Mpdf([
                // 'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'L',
                'margin_top' => 40,
                'margin_bottom' => 0,
                'margin_left' => 30,
                'margin_right' => 30,
                // 'margin_header' => 5,
                // 'margin_footer' => 5
            ]);

            $mpdf->setDisplayMode('fullpage');
            // $mpdf->SetWatermarkImage(public_path() . '/logo-watermark.png');
            // $mpdf->showWatermarkImage = true;
            // $mpdf->watermarkImageAlpha = 0.1;

            $html = view('reports.laporan_hasil_pengujian', compact('data'))->render();

            $mpdf->SetTitle('Laporan Hasil Pengujian');
            $mpdf->SetAuthor('PT Inti Surya Laboratorium');
            $mpdf->SetSubject('Laporan Hasil Pengujian');

            $mpdf->WriteHTML($html);

            $basList = '';

            if($data->status_sampling != "Sampel Diantar"){
                foreach ($data->no_bas as $no_bas) {
                    $basList .= "<li>{$no_bas}</li>";
                }
            } else {
                $basList = '-';
            }

            $mpdf->SetHTMLFooter('
                <table class="sampling-signature">
                    <tr>
                        <td class="sampling-cell">
                            <div class="section-title">' . $data->status_sampling . '</div>
                            <table style="margin-top: 15px;">
                                <tr>
                                    <td colspan="3" style="font-size: 10px;">Dokumen Pendukung</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 10px;">No. Order</td>
                                    <td style="font-size: 10px;">:</td>
                                    <td style="font-size: 10px;">' . $data->no_order . '</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 10px;">No. Quote</td>
                                    <td style="font-size: 10px;">:</td>
                                    <td style="font-size: 10px;">' . $data->no_quotation . '</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 10px;">No. BAS</td>
                                    <td style="font-size: 10px;">:</td>
                                    <td style="font-size: 10px;"></td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="padding-left: 10px; font-size: 10px;">
                                        <ul>' . $basList . '</ul>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td class="signature-cell" style="font-size: 10px;">
                            Tangerang, ' . $formattedNowDate . '<br /><br /><br /><br /><br /><br />
                            <p class="sign-name">( ' . $nama_perilis . ' )</p>
                            <p class="sign-position">' . $jabatan_perilis . '</p>
                        </td>
                    </tr>
                </table>
                <div class="footer-text">
                    Keseluruhan hasil pengujian yang terkandung di dalam Laporan Hasil Pengujian merupakan kerahasiaan dan hak eksklusifitas pelanggan, sesuai dengan penamaan yang tercantum di dalam keseluruhan Laporan Hasil Pengujian ini. PT Inti Surya Laboratorium tidak bertanggung jawab terhadap apapun apabila terjadi penyalahgunaan Laporan Hasil Pengujian termasuk didalamnya, walaupun tidak terbatas, penggandaan dan atau pemalsuan baik data maupun dokumen secara sebagian maupun seluruhnya, yang dimana tanpa sepengetahuan dan ataupun persetujuan secara resmi dari pihak PT Inti Surya Laboratorium.
                </div>
                <div class="footer-company">
                    Ruko Icon Business Park Blok O No.5 - 6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br />
                    T: 021-5088-9889 / contact@intilab.com
                </div>
            ');

            $mpdf->Output($fullPath, 'F');

            $coverLhp = CoverLhp::firstOrNew(['no_order' => $request->no_order]);

            if (!$coverLhp->exists) {
                $coverLhp->created_by = $this->karyawan;
                $coverLhp->updated_by = $this->karyawan;
            } else {
                $coverLhp->updated_by = $this->karyawan;
            }

            $coverLhp->filename = $filename;
            $coverLhp->save();


            return response()->json([
                'success' => true,
                'message' => 'Berhasil generate Cover LHP',
                'data' => $filename
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}
