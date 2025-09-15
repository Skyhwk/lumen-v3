<?php

namespace App\Http\Controllers\api;

use Mpdf\Mpdf;
use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\OrderDetail;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\PersiapanSampelHeader;
use App\Models\PersiapanSampelDetail;
use App\Models\QrDocument;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class CodingSampleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $orderDetail = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                },
                'orderHeader.docCodeSampling' => function ($q) {
                    $q->where('menu', 'STPS');
                }
            ])->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                ->where('kategori_1', '!=', 'SD')
                ->whereBetween('tanggal_sampling', [
                    date('Y-m-01', strtotime($request->periode_awal)),
                    date('Y-m-t', strtotime($request->periode_akhir))
                ])->groupBy(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling']);

            $orderDetail = $orderDetail->get()->toArray();
            $formattedData = array_reduce($orderDetail, function ($carry, $item) {
                if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                    return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ? current(array_filter($samplingPlan, fn($plan) => isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode)) : current($samplingPlan);

                if (!$targetPlan)
                    return $carry;

                $results = [];
                $jadwal = $targetPlan['jadwal'] ?? [];
                foreach ($jadwal as $schedule) {
                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        $results[] = [
                            'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                            'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                            'status_sampling' => $item['kategori_1'] ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item['no_order'] ?? '',
                            'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                            'konsultan' => $item['order_header']['konsultan'] ?? '',
                            'is_revisi' => $item['order_header']['is_revisi'] ?? '',
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                            ]),
                            'info_sampling' => json_encode([
                                'id_sp' => $targetPlan['id'],
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation'],
                            ]),
                            'nama_cabang' => isset($schedule['id_cabang']) ? (
                                $schedule['id_cabang'] == 4 ? 'RO-KARAWANG' : ($schedule['id_cabang'] == 5 ? 'RO-PEMALANG' : ($schedule['id_cabang'] == 1 ? 'HEAD OFFICE' : 'UNKNOWN'))
                            ) : 'HEAD OFFICE (Default)',
                        ];
                    }
                }

                return array_merge($carry, $results);
            }, []);

            $groupedData = [];
            foreach ($formattedData as $item) {
                $key = implode('|', [
                    $item['nomor_quotation'],
                    $item['nama_perusahaan'],
                    $item['status_sampling'],
                    $item['periode'],
                    $item['jadwal'],
                    $item['no_order'],
                    $item['alamat_sampling'],
                    $item['konsultan'],
                    $item['kategori'],
                    $item['info_pendukung'],
                    $item['jadwal_jam_mulai'],
                    $item['jadwal_jam_selesai'],
                    $item['info_sampling'],
                    $item['nama_cabang'] ?? '',
                ]);

                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'nomor_quotation' => $item['nomor_quotation'],
                        'nama_perusahaan' => $item['nama_perusahaan'],
                        'status_sampling' => $item['status_sampling'],
                        'periode' => $item['periode'],
                        'jadwal' => $item['jadwal'],
                        'kategori' => $item['kategori'],
                        'sampler' => $item['sampler'],
                        'no_order' => $item['no_order'],
                        'alamat_sampling' => $item['alamat_sampling'],
                        'konsultan' => $item['konsultan'],
                        'info_pendukung' => $item['info_pendukung'],
                        'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                        'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                        'info_sampling' => $item['info_sampling'],
                        'is_revisi' => $item['is_revisi'],
                        'nama_cabang' => $item['nama_cabang'] ?? '',
                    ];
                } else {
                    $groupedData[$key]['sampler'] .= ',' . $item['sampler'];
                }

                $uniqueSampler = explode(',', $groupedData[$key]['sampler']);
                $uniqueSampler = array_unique($uniqueSampler);
                $groupedData[$key]['sampler'] = implode(',', $uniqueSampler);
            }

            $finalResult = array_values($groupedData);

            return DataTables::of(collect($finalResult))
                // Global search
                ->filter(function ($item) use ($request) {
                    $keyword = $request->input('search.value');

                    if (!$keyword)
                        return true;

                    $fieldsToSearch = [
                        'nomor_quotation',
                        'nama_perusahaan',
                        'periode',
                        'jadwal'
                    ];

                    foreach ($fieldsToSearch as $field) {
                        if (!empty($item->$field) && stripos($item->$field, $keyword) !== false) {
                            return true;
                        }
                    }

                    return false;
                })
                // Column search
                ->filter(function ($item) use ($request) {
                    $columns = $request->input('columns', []);

                    foreach ($columns as $column) {
                        $colName = $column['name'] ?? null;
                        $colValue = trim($column['search']['value'] ?? '');

                        if ($colName && $colValue) {
                            $field = $item->$colName ?? '';

                            if ($colName === 'periode') {
                                try {
                                    $parsed = Carbon::parse($field)->translatedFormat('F Y');
                                    if (stripos($parsed, $colValue) === false) {
                                        return false;
                                    }
                                } catch (\Exception $e) {
                                    return false;
                                }
                            } elseif ($colName === 'jadwal') {
                                try {
                                    $parsed = Carbon::parse($field)->format('d/m/Y');
                                    if (stripos($parsed, $colValue) === false) {
                                        return false;
                                    }
                                } catch (\Exception $e) {
                                    return false;
                                }
                            } else {
                                if (stripos($field, $colValue) === false) {
                                    return false;
                                }
                            }
                        }
                    }

                    return true;
                }, true)
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function preview(Request $request)
    {
        try {


            // $uniqueCategories = array_values(array_unique(array_map(fn($item) => explode(" - ", $item)[0], explode(",", $request->kategori))));
            $uniqueCategories = array_values(array_unique(array_map(fn($item) => html_entity_decode(explode(" - ", $item)[0]), explode(",", $request->kategori))));

            $noSampel = [];
            foreach (explode(",", $request->kategori) as $item)
                $noSampel[] = $request->no_order . '/' . explode(' - ', $item)[1];

            $qtModel = explode('/', $request->no_document)[1] == 'QTC' ? QuotationKontrakH::class : QuotationNonKontrak::class;
            $quotation = $qtModel::with(['order', 'sampling'])->where('no_document', $request->no_document)->first();

            if (!$quotation->order || !$quotation->order->orderDetail)
                return response()->json(['message' => "Order dengan No. Quotation $request->no_document tidak ditemukan"], 401);
            if ($quotation->order->is_revisi)
                return response()->json(['message' => "Order dengan No. Quotation $request->no_document sedang dalam revisi"], 401);
            // if (!$quotation->sampling || !$quotation->sampling->jadwal)     return response()->json(['message' => 'Sampling Plan tidak ditemukan'], 401);

            // $orderDetail = $quotation->order->orderDetail()->whereIn('no_sampel', $noSampel);
            $orderDetail = $quotation->order->orderDetail()->where('tanggal_sampling', $request->tanggal_sampling)->whereIn('no_sampel', $noSampel);
            if ($request->periode)
                $orderDetail->where('periode', $request->periode);

            if (!$orderDetail)
                return response()->json(['message' => "Order pada tanggal $request->tanggal_sampling tidak ditemukan"], 401);
            // $orderDetail = $orderDetail->get();
            $orderDetail = $orderDetail->get()->filter(fn($item) => in_array(preg_replace("/^\d+-/", "", $item->kategori_3), $uniqueCategories));
            $orderDetail->first()->no_quotation = $quotation->no_document; // INJECT NO_QUOTATION (PREVENT NULL)

            $dataList = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
            ])
            ->where('is_active', 1)
            ->get();

            $psHeader = $dataList->first(function ($item) use ($noSampel) {
                $no_sampel = json_decode($item->no_sampel, true) ?? [];
                return count(array_intersect($no_sampel, $noSampel)) > 0;
            });
            // $psHeader = PersiapanSampelHeader::with('psDetail')->where([
            //     'no_order' => $request->no_order,
            //     'no_quotation' => $request->no_document,
            //     'tanggal_sampling' => $request->tanggal_sampling,
            // ])
            // ->where('is_active', 1)
            // ->first();
          

           
            if ($psHeader) {
                $bsDocument = ($psHeader->detail_cs_documents != null) ? json_decode($psHeader->detail_cs_documents) : null;
                if ($bsDocument != null) {
                    // $temNosampel = $orderDetail->pluck('no_sampel')->toArray();
                    $kategoriList = is_array($request->kategori)
                        ? $request->kategori
                        : explode(',', $request->kategori);
                    $cleanedSamples = array_map(fn($s) => explode(' - ', $s)[1] ?? null, $kategoriList);
                    $cleanedSamples = array_values(array_filter($cleanedSamples));
                    sort($cleanedSamples);
                    $matchedDocument = null;
                    foreach ($bsDocument as $index => $doc) {
                        // Pastikan $doc->no_sampel adalah array
                        if (!isset($doc->no_sampel) || !is_array($doc->no_sampel)) {
                            continue;
                        }

                        // Sort no_sampel dokumen juga
                        $docSamples = $doc->no_sampel;
                        sort($docSamples);

                        $intersection = array_intersect($cleanedSamples, $doc->no_sampel);
                        if (!empty($intersection)) {
                            $matchedDocument = $doc; // simpan dokumen yang cocok
                            break; // stop di match pertama
                        }

                        // Bandingkan apakah sama persis (jumlah dan isi, setelah diurutkan)
                        /* if ($cleanedSamples === $docSamples) {
                            $matchedDocument = $doc;
                            break;
                        } */
                    }
                    $bsDocument = $matchedDocument;
                }
            } else {
                $psHeader = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
                ])
                ->where('is_active', 1)
                ->first();
                $bsDocument = null;
            }
            $orderDetail->first()->no_quotation = $quotation->no_document; // INJECT NO_QUOTATION (PREVENT NULL)

            if (!$psHeader || !$psHeader->psDetail)
                return response()->json(['message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu'], 401);

            $psDetail = $psHeader->psDetail->whereIn('no_sampel', $noSampel);
            $barcode =$psDetail->pluck('no_sampel')->toArray();
            // dd($barcode,$noSampel);


            // INJECT COUNT
            
            foreach ($orderDetail as &$item) {
                $jumlahBotol = 0;
                $jumlahLabel = 0;
                foreach ($psDetail as $psd) {
                    if ($item->no_sampel == $psd->no_sampel) {
                        $parameters = json_decode($psd->parameters);
                        foreach ($parameters as $category) {
                            foreach ($category as $key => $values) {
                                $jumlahBotol += (int) $values->disiapkan;
                                $jumlahLabel = $jumlahLabel + ((int) $values->disiapkan * 2);
                            }
                        }

                        break;
                    }
                }

                $item->jumlah_botol = $jumlahBotol;
                $item->jumlah_label = $jumlahLabel;
            }
           
            switch ($request->type_file) {
                case 'document':
                    return response()->json($this->cetakPDF($orderDetail, $bsDocument, $psHeader), 200);
                case 'qrcode':
                    return response()->json($this->cetakQRCodePDF($orderDetail, $psDetail,$psHeader->tanggal_sampling), 200);
                case 'label':
                    $isLabeled = false;
                    foreach ($psDetail as $psd) {
                        $hasZero = collect(json_decode($psd->parameters, true))
                            ->flatten(1)
                            ->contains(fn($item) => isset($item['disiapkan']) && $item['disiapkan'] == "0");

                        if ($psd->label || $hasZero) {
                            $isLabeled = true;
                        } else {
                            $isLabeled = false;
                            break;
                        }
                    }

                    if ($isLabeled)
                        return response()->json($this->cetakLabelPDF($orderDetail, $psDetail), 200);

                    return response()->json($orderDetail, 200);
                case 'resetLabel':
                    return response()->json($orderDetail, 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile(),
            ], 500);
        }
    }

    public function cetakPDF($orderDetail, $bsDocument, $psh)
    {
        $noDocument = explode('/', $psh->no_document);
        $noDocument[1] = 'CS';
        $noDocument = implode('/', $noDocument);

        $qr_img = '';
        $qr = QrDocument::where('id_document', $psh->id)
            ->where('type_document', 'coding_sample')
            ->whereJsonContains('data->no_document', $noDocument)
            ->first();

        if ($qr) {
            $qr_data = json_decode($qr->data, true);
            if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
                $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
            }
        }

        try {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3,
                'margin_bottom' => 3,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ]);

            $konsultan = '';
            if ($orderDetail->first()->konsultan)
                $konsultan = ' (' . $orderDetail->first()->konsultan . ')';

            $filename = 'DOC_CS_' . $orderDetail->first()->no_order . '.pdf';

            $pdf->setFooter([
                'odd' => [
                    'C' => [
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ],
                    'R' => [
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'L' => [
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'line' => -1,
                ]
            ]);

            $pdf->WriteHTML('
                <!DOCTYPE html>
                    <html>
                        <head>
                            <style>
                                .custom1 { font-size: 12px; font-weight: bold; }
                                .custom2 { font-size: 15px; font-weight: bold; text-align: center; padding: 5px; }
                                .custom3 { font-size: 12px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                                .custom4 { font-size: 12px; font-weight: bold; border: 1px solid #000000;padding: 5px;}
                                .custom5 { font-size: 10px; border: 1px solid #000000; padding: 5px;}
                                .custom6 { font-size: 10px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                            </style>
                        </head>
                        <body>
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                                <td class="custom2" width="320">CODING SAMPLE</br><p style="text-align: center; font-size: x-small;">' . $noDocument . '</p></td>
                                <td class="custom3">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</td>
                            </tr>
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                            <tr>
                                <td class="custom4">
                                    <table width="100%">
                                        <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                        <tr><td style="text-align: center;">' . $orderDetail->first()->no_quotation . '</td></tr>
                                    </table>
                                </td>
                                <td width="120" class="custom4" style="text-align: center;">' . $orderDetail->first()->nama_perusahaan . $konsultan . '</td>
                                <td class="custom3">' . $orderDetail->first()->no_order . '</td>
                            </tr>
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                        </table>
            ');

            $pdf->defaultheaderline = 0;
            $pdf->SetHeader('
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                                <td class="custom2" width="320">CODING SAMPLING</td>
                                <td class="custom3">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</td>
                            </tr>
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                            <tr>
                                <td class="custom4">
                                    <table width="100%">
                                        <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                        <tr><td style="text-align: center;">' . $orderDetail->first()->no_quotation . '</td></tr>
                                    </table>
                                </td>
                                <td width="120" class="custom4">' . $orderDetail->first()->nama_perusahaan . $konsultan . '</td>
                                <td class="custom3">' . $orderDetail->first()->no_order . '</td>
                            </tr>

                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                        </table>
            ');

            $pdf->WriteHTML('
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <th class="custom6" width="90">CS</th>
                                <th class="custom6" width="70">KATEGORI</th>
                                <th class="custom6">DESKRIPSI</th>
                                <th class="custom6" width="128">BARCODE</th>
                                <th class="custom6" width="28">CS</t>
                                <th class="custom6" width="28">C-1</th>
                                <th class="custom6" width="28">C-2</t>
                                <th class="custom6" width="28">C-3</th>
                            </tr>
            ');

            foreach ($orderDetail as $item) {
                $pdf->WriteHTML('
                            <tr>
                                <td class="custom5" width="90">' . $item->no_sampel . '</td>
                                <td class="custom5" width="70">' . explode("-", $item->kategori_3)[1] . '</td>
                                <td class="custom5" height="60">' . $item->keterangan_1 . '</td>
                                <td class="custom5" width="128"><img src="' . public_path() . '/barcode/sample/' . $item->file_koding_sampel . '" style="height: 30px; width:180px;"></td>
                                <td class="custom5" width="28">' . $item->jumlah_botol . '</td>
                                <td class="custom5" width="28"></td>
                                <td class="custom5" width="28"></td>
                                <td class="custom5" width="28"></td>
                            </tr>
                ');
            }

            $pdf->WriteHTML('</table></body></html>');
             $dir = public_path("cs");

            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            $pdf->Output(public_path() . '/cs/' . $filename, 'F');
            if ($bsDocument !== null) {
                return response()->json(['status' => true, 'data' => $bsDocument], 200);
            } else {
                return response()->json(['status' => false, 'data' => $filename], 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    private function generateQR($no_sampel, $directory = null)
    {
        try {
            // Validasi input
            if (empty($no_sampel)) {
                throw new \Exception("No sampel tidak boleh kosong");
            }

            if ($directory !== null) {
                $filename = \str_replace("/", "_", $no_sampel) . '.png';
                $path = public_path() . "$directory/$filename";
            } else {
                $filename = \str_replace("/", "_", $no_sampel) . '.png';
                $path = public_path() . "/qrcode/sample/$filename";
            }

            // Pastikan direktori ada
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            QrCode::format('png')->size(200)->generate($no_sampel, $path);

            return $filename;
        } catch (\Exception $th) {
            // Log error untuk debugging
            \Log::error("Error generating QR: " . $th->getMessage(), [
                'no_sampel' => $no_sampel,
                'directory' => $directory
            ]);
            throw $th;
        }
    }

    private function cetakQRCodePDF($orderDetail, $psDetail,$tanggal = null)
    {
        
        try {
            //code...
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [50, 15],
                'margin_left' => 1,
                'margin_right' => 1,
                'margin_top' => 0.5,
                'margin_header' => 0,
                'margin_bottom' => 0,
                'margin_footer' => 0,
            ]);

            $filename = 'QRCODE_CS_' . explode("/", $psDetail->first()->no_sampel)[0] . '.pdf';

            $pdf->WriteHTML('
                <!DOCTYPE html>
                    <html>
                        <body>
            ');

            $pdf->WriteHTML('<table width="100%">');

            $counter = 0;
            /* product foreach ($psDetail as $psd) {
                // $qrCode = $this->generateQR($psd->no_sampel);
                $parameters = json_decode($psd->parameters);
                // dd($psd, $parameters);
                foreach ($parameters as $key => $categories) {
                    // dd($parameters);
                    $path = ($key === 'air') ? '/barcode/botol/' : '/barcode/penjerap/';
                    foreach ($categories as $parameter => $qty) {
                        for ($i = 0; $i < $qty->disiapkan; $i++) {
                            if ($counter % 2 == 0)
                                $pdf->WriteHTML("<tr>");

                            $padding = ($counter % 2 == 0) ? '2% 40% 0% 0%' : '2% 0% 0% 0%';

                            $tanggal = Carbon::parse($orderDetail->firstWhere('no_sampel', $psd->no_sampel)->created_at);
                            $batas = Carbon::parse('2025-07-21');

                            if ($tanggal < $batas) {
                                $qrFile = $this->generateQR($psd->no_sampel);

                                $imagePath = public_path('qrcode/sample/' . $qrFile);
                            } else {
                                $imagePath = public_path() . $path . $qty->file;
                            }

                            $pdf->WriteHTML('
                                            <th style="padding: ' . $padding . ';">
                                                <table>
                                                    <tr>
                                                        <td style="text-align: left;"><img src="' . $imagePath . '"></td>
                                                        <td style="text-align: center !important;">' . $parameter . '</td>
                                                    </tr>
                                                    <tr><td colspan="2" style="font-size: 12px;">' . $psd->no_sampel . '</td></tr>
                                                </table>
                                            </th>
                                ');

                            if ($counter % 2 == 1)
                                $pdf->WriteHTML("</tr>");

                            $counter++;
                        }
                    }
                }
            } */
         
           foreach ($psDetail as $psd) {
                if (empty($psd->no_sampel)) {
                    throw new \Exception("No sampel kosong untuk data: " . json_encode($psd));
                }

                $qrCode = $this->generateQR($psd->no_sampel);
                $parameters = json_decode($psd->parameters);

                if (!$parameters) {
                    throw new \Exception("Parameters tidak valid untuk no_sampel: " . $psd->no_sampel);
                }

                $kodingSampling = OrderDetail::where('no_sampel', $psd->no_sampel)
                    ->where('is_active', 1)
                    ->where('tanggal_sampling', $tanggal)
                    ->first();
                
                if ($kodingSampling != null) {
                    // Ambil data persiapan yang sudah ada
                    $existingPersiapan = json_decode($kodingSampling->persiapan, true) ?? [];
                    
                    // Buat array untuk tracking parameter yang sudah ada
                    $existingParameters = [];
                    foreach ($existingPersiapan as $item) {
                        if (isset($item['parameter'])) {
                            $existingParameters[$item['parameter']] = $item;
                        } elseif (isset($item['type_botol'])) {
                            // Jika format lama menggunakan type_botol
                            $existingParameters[$item['type_botol']] = $item;
                        }
                    }
                    
                    foreach ($parameters as $key => $categories) {
                        
                        $path = ($key === 'air') ? '/barcode/botol/' : '/barcode/penjerap/';
                        
                        foreach ($categories as $parameter => $qty) {
                            // Cek apakah parameter sudah ada dalam existing data
                            
                            if (!isset($existingParameters[$parameter])) {
                                // Parameter belum ada, buat baru
                                if ($qty->file != null) {
                                    $persiapan = [
                                        'parameter' => $parameter,
                                        'disiapkan' => $qty->disiapkan,
                                        'koding' => null,
                                        'file' => $qty->file
                                    ];
                                } else {
                                    if($key !== 'air'){
                                        $noKodingSampling = $kodingSampling->koding_sampling . strtoupper(Str::random(5));
                                        $persiapan = [
                                            'parameter' => $parameter,
                                            'disiapkan' => $qty->disiapkan,
                                            'koding' => $noKodingSampling,
                                            'file' => $noKodingSampling . '.png'
                                        ];
                                        $qty->file = $persiapan['file'];
                                    }
                                }
                                
                                // Hanya generate QR jika koding tidak null
                                if ($persiapan['koding'] !== null) {
                                    $this->generateQR($persiapan['koding'], $path);
                                }
                                
                                // Tambahkan ke existing parameters
                                $existingParameters[$parameter] = $persiapan;
                            } else {
                                // Parameter sudah ada, gunakan yang existing
                                $persiapan = $existingParameters[$parameter];
                                
                                // Update qty->file dari existing data
                                if (isset($persiapan['file'])) {
                                    $qty->file = $persiapan['file'];
                                }
                            }

                            // Generate PDF content
                            for ($i = 0; $i < $qty->disiapkan; $i++) {
                                if ($counter % 2 == 0) $pdf->WriteHTML("<tr>");
                                $padding = ($counter % 2 == 0) ? '2% 40% 0% 0%' : '2% 0% 0% 0%';

                                $pdf->WriteHTML('
                                    <th style="padding: ' . $padding . ';">
                                        <table>
                                            <tr>
                                                <td style="text-align: left;"><img src="' . public_path() . $path . $persiapan['file'] . '"></td>
                                                <td style="text-align: center !important;">' . $parameter . '</td>
                                            </tr>
                                            <tr><td colspan="2" style="font-size: 12px;">' . $psd->no_sampel . '</td></tr>
                                        </table>
                                    </th>
                                ');

                                if ($counter % 2 == 1) $pdf->WriteHTML("</tr>");
                                $counter++;
                            }
                        }
                    }

                    // Convert associative array kembali ke indexed array untuk disimpan
                    $finalPersiapan = array_values($existingParameters);
                    
                    $kodingSampling->persiapan = json_encode($finalPersiapan);
                    $kodingSampling->save();

                    // Update parameters di persiapan detail
                    $psd->parameters = json_encode($parameters);
                    $psd->save();
                }
            }

            $pdf->WriteHTML('</table></body></html>');
             $dir = public_path("cs");
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $pdf->Output(public_path() . '/cs/' . $filename, 'F');

            return response()->json([
                'status' => false,
                'data' => $filename,
            ], 200);
        } catch (\Exception $th) {
            throw $th;
        }
    }
    // private function cetakQRCodePDF($psDetail, $generateQR = true)
    // {
    //     // dd('cetakQRCodePDF', $psDetail, $generateQR);
    //     $pdf = new Mpdf([
    //         'mode' => 'utf-8',
    //         'format' => [50, 15],
    //         'margin_left' => 1,
    //         'margin_right' => 1,
    //         'margin_top' => 0.5,
    //         'margin_header' => 0,
    //         'margin_bottom' => 0,
    //         'margin_footer' => 0,
    //     ]);

    //     $filename = 'QRCODE_CS_' . explode("/", $psDetail->first()->no_sampel)[0] . '.pdf';

    //     $pdf->WriteHTML('<!DOCTYPE html><html><body><table width="100%">');

    //     $counter = 0;
    //     foreach ($psDetail as $psd) {


    //         $parameters = json_decode($psd->parameters ?? $psd->persiapan); // fallback
    //         if (!$parameters)
    //             continue;

    //         if ($generateQR) {
    //             foreach ($parameters as $categories) {
    //                 foreach ($categories as $parameter => $qty) {
    //                     for ($i = 0; $i < $qty->disiapkan; $i++) {
    //                         if ($counter % 2 == 0)
    //                             $pdf->WriteHTML("<tr>");

    //                         $padding = ($counter % 2 == 0) ? '2% 40% 0% 0%' : '2% 0% 0% 0%';

    //                         // Ambil QR image
    //                         if ($generateQR) {
    //                             $qrFile = $this->generateQR($psd->no_sampel); // masih berupa nama file
    //                         } else {
    //                             $qrFile = $qty->file ?? null;
    //                             if (!$qrFile)
    //                                 continue; // skip kalau file kosong
    //                         }

    //                         $imagePath = public_path('qrcode/sample/' . $qrFile);

    //                         $pdf->WriteHTML('
    //                             <th style="padding: ' . $padding . ';"><table>
    //                                 <tr>
    //                                     <td style="text-align: left;"><img src="' . $imagePath . '"></td>
    //                                     <td style="text-align: center !important;">' . $parameter . '</td>
    //                                 </tr>
    //                                 <tr><td colspan="2" style="font-size: 12px;">' . $psd->no_sampel . '</td></tr>
    //                             </table></th>
    //                         ');

    //                         if ($counter % 2 == 1)
    //                             $pdf->WriteHTML("</tr>");

    //                         $counter++;
    //                     }
    //                 }
    //             }

    //         } else {
    //             $kat = explode('-', $psd->kategori_2)[1];
    //             foreach ($parameters as $categories => $qty) {
    //                 for ($i = 0; $i < $qty->disiapkan; $i++) {
    //                     if ($counter % 2 == 0)
    //                         $pdf->WriteHTML("<tr>");

    //                     $padding = ($counter % 2 == 0) ? '2% 40% 0% 0%' : '2% 0% 0% 0%';

    //                     // Ambil QR image
    //                     if ($generateQR) {
    //                         $qrFile = $this->generateQR($psd->no_sampel); // masih berupa nama file
    //                     } else {
    //                         $qrFile = $qty->file ?? null;
    //                         if (!$qrFile)
    //                             continue; // skip kalau file kosong
    //                     }

    //                     $imagePath = public_path('barcode/botol/' . $qrFile);

    //                     $pdf->WriteHTML('
    //                         <th style="padding: ' . $padding . ';"><table>
    //                             <tr>
    //                                 <td style="text-align: left;"><img src="' . $imagePath . '"></td>');
    //                     if ($kat == 'Air') {
    //                         $pdf->WriteHTML('<td style="text-align: center !important;">' . $qty->type_botol . '</td>');
    //                     } else if ($kat == 'Udara') {
    //                         $pdf->WriteHTML('<td style="text-align: center !important;">' . $qty->parameter . '</td>');
    //                     }
    //                     $pdf->WriteHTML('</tr>
    //                             <tr><td colspan="2" style="font-size: 12px;">' . $psd->no_sampel . '</td></tr>
    //                         </table></th>
    //                     ');

    //                     if ($counter % 2 == 1)
    //                         $pdf->WriteHTML("</tr>");

    //                     $counter++;
    //                 }
    //             }
    //         }
    //     }

    //     $pdf->WriteHTML('</table></body></html>');
    //     $pdf->Output(public_path('cs/' . $filename), 'F');

    //     return response()->json([
    //         'status' => true,
    //         'data' => $filename,
    //     ], 200);
    // }


    private function cetakLabelPDF($orderDetail, $psDetail)
    {
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [50, 15],
            'margin_left' => 1,
            'margin_right' => 1,
            'margin_top' => 0.5,
            'margin_header' => 0,
            'margin_bottom' => 0,
            'margin_footer' => 0,
        ]);

        $filename = 'LABEL_CS_' . $orderDetail->first()->no_order . '.pdf';

        $pdf->WriteHTML('
            <!DOCTYPE html>
                <html>
                    <head>
                        <style>
                            .colom1 { text-align: center; padding-right: 40px; }
                            .line { border-width: 10; color: black; }
                        </style>
                    </head>
                    <body>
        ');

        $pdf->WriteHTML('<table width="100%">');

        $counter = 0;
        foreach ($orderDetail as $item) {
            // dump($item->jumlah_botol);
            for ($i = 0; $i < $item->jumlah_label; $i++) {
                if ($counter % 2 == 0)
                    $pdf->WriteHTML("<tr>");

                $padding = ($counter % 2 == 0) ? '8% 40% 0% 0%;' : '8% 0% 0% 0%;';

                $label = $psDetail->where('no_sampel', $item->no_sampel)->first()->label;

                if ($label) {
                    $pdf->WriteHTML('
                                <th>
                                    <td style="text-align: center; padding: ' . $padding . '">
                                        <span style="font-size: 18px; font-weight: bold;">' . $item->no_sampel . '.</span><br>
                                        <span style="font-size: 14px; font-weight: bold;">' . json_decode($label)[$i] . '</span><br><hr>
                                        <span style="font-size: 16px; font-weight: bold;">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</span>
                                    </td>
                                </th>
                    ');
                }
                // if ($label) {
                //     $pdf->WriteHTML('
                //                 <th>
                //                     <td style="text-align: center; padding: ' . $padding . '">
                //                         <span style="font-size: 18px; font-weight: bold;">' . $item->no_sampel . '.</span><br>
                //                         <span style="font-size: 14px; font-weight: bold;">' . json_decode($label)[(int) $i/2] . '</span><br><hr>
                //                         <span style="font-size: 16px; font-weight: bold;">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</span>
                //                     </td>
                //                 </th>
                //     ');
                // }


                if ($counter % 2 == 1)
                    $pdf->WriteHTML("</tr>");

                $counter++;
            }
        }

        $pdf->WriteHTML('</table></body></html>');
        $dir = public_path("cs");

            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        $pdf->Output(public_path() . '/cs/' . $filename, 'F');

        return $filename;
    }

    public function saveLabel(Request $request)
    {
        $groupedData = [];
        foreach ($request->no_sampel as $index => $no_sampel) {
            $kategori = $request->kategori[$index];

            if (!isset($groupedData[$no_sampel]))
                $groupedData[$no_sampel] = [];

            $groupedData[$no_sampel][] = $kategori;
        }

        foreach ($groupedData as $no_sampel => $label)
            PersiapanSampelDetail::where('no_sampel', $no_sampel)->update(['label' => json_encode($label)]);

        return response()->json(['message' => 'Saved Successfully.'], 200);
    }
}
