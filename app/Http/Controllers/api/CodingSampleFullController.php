<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\PersiapanSampelHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf;
use Yajra\Datatables\Datatables;

Carbon::setLocale('id');

class CodingSampleFullController extends Controller
{
    public function index(Request $request)
    {
        try {
            $myPrivileges = $this->privilageCabang;
            $isOrangPusat = in_array('1', $myPrivileges);
            $query = OrderDetail::query();

            if (!$isOrangPusat) {
                $query->whereHas('orderHeader.samplingPlan.jadwal', function ($q) use ($myPrivileges) {
                    $q->where('is_active', true);
                    $q->whereIn('id_cabang', $myPrivileges);
                });
            }

            $data = $query->with([
                'orderHeader' => function ($q) {
                    $q->select([
                        'id', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'no_document',
                        'alamat_sampling', 'nama_pic_order', 'nama_pic_sampling',
                        'no_tlp_pic_sampling', 'jabatan_pic_sampling', 'jabatan_pic_order', 'is_revisi',
                    ]);
                },
                'orderHeader.samplingPlan' => function ($q) {
                    $q->select(['id', 'periode_kontrak', 'quotation_id', 'status_quotation', 'is_active'])
                        ->where('is_active', true);
                },
                'orderHeader.samplingPlan.jadwal' => function ($q) use ($isOrangPusat, $myPrivileges) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler'),
                    ])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                    if (!$isOrangPusat) {
                        $q->whereIn('id_cabang', $myPrivileges);
                    }
                },
            ])
                ->select(['id_order_header', 'no_order', 'kategori_1', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                ->whereBetween('tanggal_sampling', [$request->periode_awal, $request->periode_akhir])
                ->get();

            $cabangMap = [
                1 => 'HEAD OFFICE',
                4 => 'RO-KARAWANG',
                5 => 'RO-PEMALANG',
            ];

            $groupedData = [];

            foreach ($data as $item) {
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {
                    continue;
                }

                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                $targetPlan = $periode
                    ? $orderHeader->sampling->firstWhere('periode_kontrak', $periode)
                    : null;

                if (!$targetPlan) {
                    $targetPlan = $orderHeader->sampling->first();
                }

                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                    continue;
                }

                $isKontrak = strpos($orderHeader->no_document, '/QTC/') !== false;
                $groupKey = $isKontrak
                    ? $orderHeader->no_document . '|' . $periode
                    : $orderHeader->no_document;

                foreach ($targetPlan->jadwal as $schedule) {
                    if (!$isOrangPusat && !in_array($schedule->id_cabang, $this->privilageCabang)) {
                        continue;
                    }
                    if ($schedule->tanggal !== $item->tanggal_sampling) {
                        continue;
                    }

                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $namaCabang = $cabangMap[$schedule->id_cabang] ?? 'HEAD OFFICE (Default)';
                    $samplers = array_filter(array_map('trim', explode(',', $schedule->sampler ?? '')));

                    if (!isset($groupedData[$groupKey])) {
                        $groupedData[$groupKey] = [
                            'nomor_quotation' => $orderHeader->no_document ?? '',
                            'nama_perusahaan' => $orderHeader->nama_perusahaan ?? '',
                            'status_sampling' => $item->kategori_1 ?? '',
                            'periode' => $isKontrak ? $periode : null,
                            'jadwal_dates' => [],
                            'kategori_list' => [],
                            'sampler_list' => [],
                            'cabang_list' => [],
                            'no_order' => $item->no_order ?? '',
                            'alamat_sampling' => $orderHeader->alamat_sampling ?? '',
                            'konsultan' => $orderHeader->konsultan ?? '',
                            'is_revisi' => $orderHeader->is_revisi,
                            'tipe' => $isKontrak ? 'kontrak' : 'non_kontrak',
                        ];
                    }

                    $groupedData[$groupKey]['jadwal_dates'][] = $schedule->tanggal;
                    foreach (array_filter(explode(',', $kategori)) as $kat) {
                        $groupedData[$groupKey]['kategori_list'][] = trim($kat);
                    }
                    foreach ($samplers as $sampler) {
                        $groupedData[$groupKey]['sampler_list'][] = $sampler;
                    }
                    $groupedData[$groupKey]['cabang_list'][] = $namaCabang;
                }
            }

            $rows = [];
            foreach ($groupedData as $row) {
                $dates = array_values(array_unique(array_filter($row['jadwal_dates'])));
                sort($dates);

                $kategori = array_values(array_unique(array_filter($row['kategori_list'])));
                $sampler = array_values(array_unique(array_filter($row['sampler_list'])));
                $cabang = array_values(array_unique(array_filter($row['cabang_list'])));

                $rows[] = [
                    'nomor_quotation' => $row['nomor_quotation'],
                    'nama_perusahaan' => $row['nama_perusahaan'],
                    'status_sampling' => $row['status_sampling'],
                    'periode' => $row['periode'],
                    'jadwal' => implode(',', $dates),
                    'kategori' => implode(',', $kategori),
                    'sampler' => implode(',', $sampler),
                    'no_order' => $row['no_order'],
                    'alamat_sampling' => $row['alamat_sampling'],
                    'konsultan' => $row['konsultan'],
                    'is_revisi' => $row['is_revisi'],
                    'nama_cabang' => implode(', ', $cabang),
                    'tipe' => $row['tipe'],
                ];
            }

            return DataTables::of(collect($rows))->make(true);
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
            $qtModel = explode('/', $request->no_document)[1] == 'QTC'
                ? QuotationKontrakH::class
                : QuotationNonKontrak::class;
            $isKontrak = $qtModel === QuotationKontrakH::class;

            $quotation = $qtModel::with(['order', 'sampling'])->where('no_document', $request->no_document)->first();

            if (!$quotation || !$quotation->order || !$quotation->order->orderDetail) {
                return response()->json(['message' => "Order dengan No. Quotation $request->no_document tidak ditemukan"], 401);
            }
            if ($quotation->order->is_revisi) {
                return response()->json(['message' => "Order dengan No. Quotation $request->no_document sedang dalam revisi"], 401);
            }

            $orderDetailQuery = $quotation->order->orderDetail();
            if ($isKontrak && $request->periode) {
                $orderDetailQuery->where('periode', $request->periode);
            }

            $orderDetail = $orderDetailQuery->orderBy('no_sampel')->get();
            if ($orderDetail->isEmpty()) {
                return response()->json(['message' => 'Order detail tidak ditemukan'], 401);
            }

            $orderDetail->first()->no_quotation = $quotation->no_document;

            $psQuery = PersiapanSampelHeader::with('psDetail')
                ->where('no_order', $request->no_order)
                ->where('no_quotation', $request->no_document)
                ->where('is_active', 1);

            if ($isKontrak && $request->periode) {
                $psQuery->where('periode', $request->periode);
            }

            $psHeaders = $psQuery->get();
            if ($psHeaders->isEmpty()) {
                return response()->json(['message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu'], 401);
            }

            $psDetail = $psHeaders->flatMap(function ($header) {
                return $header->psDetail ?? collect();
            });

            $preparedNos = $psDetail->pluck('no_sampel')->filter()->unique()->values();
            $orderDetail = $orderDetail->filter(function ($item) use ($preparedNos) {
                return $preparedNos->contains($item->no_sampel);
            })->values();

            if ($orderDetail->isEmpty()) {
                return response()->json(['message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu'], 401);
            }

            $orderDetail->first()->no_quotation = $quotation->no_document;

            foreach ($orderDetail as $item) {
                $jumlahBotol = 0;
                foreach ($psDetail as $psd) {
                    if ($item->no_sampel != $psd->no_sampel) {
                        continue;
                    }

                    $parameters = json_decode($psd->parameters);
                    if (!$parameters) {
                        break;
                    }

                    foreach ($parameters as $category) {
                        foreach ($category as $values) {
                            $jumlahBotol += (int) ($values->disiapkan ?? 0);
                        }
                    }
                    break;
                }

                $item->jumlah_botol = $jumlahBotol;
            }

            return response()->json($this->cetakPdfInline($orderDetail), 200);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile(),
            ], 500);
        }
    }

    private function cetakPdfInline($orderDetail)
    {
        $first = $orderDetail->first();
        $dates = $orderDetail->pluck('tanggal_sampling')->filter()->unique()->sort()->values();
        $headerDate = $dates->map(function ($date) {
            return Carbon::parse($date)->translatedFormat('d F Y');
        })->implode('<br>');
        if ($headerDate === '') {
            $headerDate = Carbon::parse($first->tanggal_sampling)->translatedFormat('d F Y');
        }
        $dateFontSize = $dates->count() > 1 ? '9px' : '12px';

        $csParts = explode('/', $first->no_quotation);
        if (isset($csParts[1])) {
            $csParts[1] = 'CS';
        }
        $noDocument = implode('/', $csParts);

        $konsultan = $first->konsultan ? ' (' . $first->konsultan . ')' : '';
        $safeOrder = str_replace('/', '_', $first->no_order);
        $periodeSuffix = $first->periode ? '_' . str_replace(['-', '/'], '', $first->periode) : '';
        $filename = 'DOC_CS_FULL_' . $safeOrder . $periodeSuffix . '.pdf';

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 3,
            'margin_bottom' => 3,
            'margin_footer' => 3,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'orientation' => 'P',
        ]);

        $pdf->setFooter([
            'odd' => [
                'C' => [
                    'content' => 'Hal {PAGENO} dari {nbpg}',
                    'font-size' => 6,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060',
                ],
                'R' => [
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000000',
                ],
                'line' => -1,
            ],
        ]);

        $headerTable = '
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                    <td class="custom2" width="320">CODING SAMPLE</br><p style="text-align: center; font-size: x-small;">' . $noDocument . '</p></td>
                    <td class="custom3" style="font-size: ' . $dateFontSize . ';">' . $headerDate . '</td>
                </tr>
                <tr><td colspan="3" style="padding: 2px;"></td></tr>
                <tr>
                    <td class="custom4">
                        <table width="100%">
                            <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                            <tr><td style="text-align: center;">' . $first->no_quotation . '</td></tr>
                        </table>
                    </td>
                    <td width="120" class="custom4" style="text-align: center;">' . $first->nama_perusahaan . $konsultan . '</td>
                    <td class="custom3">' . $first->no_order . '</td>
                </tr>
                <tr><td colspan="3" style="padding: 2px;"></td></tr>
            </table>
        ';

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
                ' . $headerTable . '
        ');

        $pdf->defaultheaderline = 0;
        $pdf->SetHeader($headerTable);

        $pdf->WriteHTML('
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <th class="custom6" width="90">CS</th>
                    <th class="custom6" width="70">KATEGORI</th>
                    <th class="custom6">DESKRIPSI</th>
                    <th class="custom6" width="128">BARCODE</th>
                    <th class="custom6" width="28">CS</th>
                    <th class="custom6" width="28">C-1</th>
                    <th class="custom6" width="28">C-2</th>
                    <th class="custom6" width="28">C-3</th>
                </tr>
        ');

        foreach ($orderDetail as $item) {
            $kategori = $item->kategori_3 ?? '';
            $kategoriParts = explode('-', $kategori);
            $kategoriLabel = isset($kategoriParts[1]) ? trim($kategoriParts[1]) : $kategori;
            $barcode = $item->file_koding_sampel
                ? '<img src="' . public_path() . '/barcode/sample/' . $item->file_koding_sampel . '" style="height: 30px; width:180px;">'
                : '';

            $pdf->WriteHTML('
                <tr>
                    <td class="custom5" width="90">' . $item->no_sampel . '</td>
                    <td class="custom5" width="70">' . $kategoriLabel . '</td>
                    <td class="custom5" height="60">' . $item->keterangan_1 . '</td>
                    <td class="custom5" width="128">' . $barcode . '</td>
                    <td class="custom5" width="28">' . $item->jumlah_botol . '</td>
                    <td class="custom5" width="28"></td>
                    <td class="custom5" width="28"></td>
                    <td class="custom5" width="28"></td>
                </tr>
            ');
        }

        $pdf->WriteHTML('</table></body></html>');

        return [
            'inline' => true,
            'filename' => $filename,
            'pdf' => base64_encode($pdf->Output('', 'S')),
        ];
    }
}
