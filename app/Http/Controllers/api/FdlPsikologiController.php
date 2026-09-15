<?php

namespace App\Http\Controllers\api;

use App\Models\{
    DataLapanganPsikologi,
    QrPsikologi,
    OrderDetail,
    OrderHeader,
    MasterSubKategori,
    MasterKaryawan,
    Parameter,
    PsikologiHeader
};

use App\Http\Controllers\Controller;
use App\Services\PsikologiHasilFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
class FdlPsikologiController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganPsikologi::with('detail')->where('no_sampel', '<>', null)
            ->orderBy('id', 'desc');
        return Datatables::of($data)
        ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
            $query->whereHas('detail', function ($q) use ($keyword) {
                $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
            });
        })
        ->filterColumn('created_at', function ($query, $keyword) {
            $query->where('created_at', 'like', '%' . $keyword . '%');
        })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('nama_perusahaan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.kategori_2', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_2', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function getAllNoSampel(Request $request)
    {
        if ($request->no_order != null) {
            $data = OrderDetail::Select('no_sampel')->where('no_order', $request->no_order)->where('is_active', true)->get();
        } else {
            $data = OrderDetail::Select('no_sampel')->where('is_active', true)->get();
        }
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganPsikologi::where('id', $request->id)->first();
            $order = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            if (!$data) {
                return response()->json(['message' => 'Data Lapangan tidak ditemukan.'], 404);
            }
            $header = PsikologiHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();

            if (!$header) {
                $orderParameter = $order->parameter;
                $clean = str_replace(['[', ']'], '', $orderParameter); 
                $parts = explode(';', $clean);

                $header = new PsikologiHeader;
                $header->no_sampel = $data->no_sampel;
                $header->id_parameter = isset($parts[0]) ? intval(trim($parts[0], "\" ")) : null;
                $header->parameter = rtrim(trim($parts[1]), "\"") ?? null;
                $header->tanggal_terima = $order->tanggal_terima;
                $header->is_approve = true;
                $header->approved_by = $this->karyawan;
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();

            }else{
                $header->tanggal_terima = $order->tanggal_terima;
                $header->is_reject = false;
                $header->is_approve = true;
                $header->approved_by = $this->karyawan;
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();
            }

            $data->is_approve = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();


            // if($this->pin != null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => "Data Dengan No Sampel $data->no_sampel Telah di Approve oleh $this->karyawan",
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPsikologi::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $header = PsikologiHeader::where('no_sampel', $data->no_sampel)->first();
            if($header){
                $header->is_reject = true;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                $header->save();
            }

            $data->is_reject = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->is_approve = false;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();
            // dd($data);

            // if($cek_sampler->pin_user!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Reject oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($cek_sampler->pin_user, $txt);
            // }

            return response()->json([
                'message' => "Data Dengan No Sampel $data->no_sampel Telah di reject oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPsikologi::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;


            $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lokasi)) {
                unlink($foto_lokasi);
            }

            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }

            $header = PsikologiHeader::where('no_sampel', $data->no_sampel)->first();
            if($header){
                $header->delete();
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => "Data Dengan No Sampel $data->no_sampel Telah di hapus oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganPsikologi::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di Unblock oleh $this->karyawan",
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganPsikologi::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di block oleh $this->karyawan",
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganPsikologi::where('id', $request->id)->first();

                $data->no_sampel = $request->no_sampel;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;

                $data->save();
                
                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)
                    ->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                    
                    $order_detail_lama->tanggal_terima = NULL;
                    $order_detail_lama->save();
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil menambahkan no sampel ' . $request->no_sampel,
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal menambahkan no sampel ' . $request->no_sampel,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function getDataAdmin(Request $request)
    {
        if (!isset($request->no_document) || $request->no_document === null) {
            return response()->json([
                'message' => 'No order tidak boleh kosong'
            ], 401);
        }

        $context = $this->resolveAdminPortalContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        return response()->json([
            'message' => 'Data Dengan No Order ' . $request->no_document,
            'nama_pekerja' => $context['participants'],
            'nama_pt' => $context['nama_pt'],
            'no_order' => $context['no_order'],
            'periode' => $context['periode'],
            'order_detail' => $context['order_detail'],
        ], 200);
    }

    public function exportExcelAdmin(Request $request)
    {
        if (!isset($request->no_document) || $request->no_document === null) {
            return response()->json([
                'message' => 'No order tidak boleh kosong'
            ], 401);
        }

        $context = $this->resolveAdminPortalContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $participants = $context['participants'];
        if (empty($participants)) {
            return response()->json([
                'message' => 'Tidak ada data psikologi untuk diekspor'
            ], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Psikologi');

        $nextRow = 1;
        foreach ($participants as $index => $participant) {
            $detail = $participant['detail'] ?? [];
            $nextRow = $this->appendParticipantExcelBlock(
                $sheet,
                $nextRow,
                $detail,
                $index === 0
            );
        }

        $this->applyPsikologiExcelColumnWidths($sheet);

        $lastRow = max(1, $nextRow - 1);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea('A1:H' . $lastRow);
        $sheet->getPageMargins()
            ->setTop(0.5)
            ->setRight(0.4)
            ->setLeft(0.4)
            ->setBottom(0.5)
            ->setHeader(0.2)
            ->setFooter(0.2);

        $safeCompany = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', (string) ($context['nama_pt'] ?? 'Data-Psikologi'));
        $safeCompany = trim($safeCompany) !== '' ? trim($safeCompany) : 'Data-Psikologi';
        $fileName = 'Data-Psikologi-' . mb_substr($safeCompany, 0, 40) . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function resolveAdminPortalContext(Request $request)
    {
        $qrPsikologi = QrPsikologi::where('token', $request->token)->first();
        if (!$qrPsikologi) {
            return response()->json(['message' => 'Token psikologi tidak valid'], 401);
        }

        $data = DataLapanganPsikologi::where('no_order', $request->no_document)
            ->where('periode', $qrPsikologi->periode)
            ->get();
        $header = DataLapanganPsikologi::where('no_order', $request->no_document)->first();
        $orderHeader = OrderHeader::where('no_order', $request->no_document)->first();

        if (!$orderHeader) {
            return response()->json(['message' => 'Data order tidak ditemukan'], 404);
        }

        $orderDetail = OrderDetail::where('no_order', $orderHeader->no_order)
            ->where('periode', $qrPsikologi->periode)
            ->where('is_active', true)
            ->whereJsonContains('parameter', '318;Psikologi')
            ->get();

        $samplingByNoSampel = $orderDetail->mapWithKeys(function ($item) {
            return [$item->no_sampel => $item->tanggal_sampling];
        });

        $formatter = new PsikologiHasilFormatter();
        $namaPt = $header->nama_perusahaan ?? '-';

        $participants = $data->map(function ($item) use ($formatter, $samplingByNoSampel, $namaPt) {
            $participant = [
                'id' => $item->id,
                'nama' => $item->nama_pekerja,
                'divisi' => $item->divisi,
                'lama_kerja' => $formatter->formatMasaKerja($item->lama_kerja),
                'no_sampel' => $item->no_sampel,
                'jenis_kelamin' => $item->jenis_kelamin,
                'created_at' => $item->created_at,
                'hasil' => $item->hasil,
            ];

            $tanggalSampling = $samplingByNoSampel->get($item->no_sampel);
            $participant['detail'] = $formatter->buildParticipantDetail($participant, $tanggalSampling, $namaPt);

            return $participant;
        })->values()->all();

        return [
            'participants' => $participants,
            'nama_pt' => $namaPt,
            'no_order' => $header->no_order ?? $request->no_document,
            'periode' => $qrPsikologi->periode ?? '-',
            'order_detail' => $orderDetail,
        ];
    }

    protected function appendParticipantExcelBlock(Worksheet $sheet, int $startRow, array $detail, bool $isFirst): int
    {
        $spacingRows = 4;

        if (!$isFirst) {
            $sheet->setBreak('A' . $startRow, Worksheet::BREAK_ROW);
        }

        $rows = [
            ['Tanggal Sampling', $detail['tanggal_sampling'] ?? '-'],
            ['Nama', $detail['nama'] ?? '-'],
            ['Department', $detail['department'] ?? '-'],
            [],
            ['Kategori Stress', 'Nilai per Kategori', '', '', '', '', 'Total Skor', 'Kesimpulan'],
        ];

        foreach ($detail['detail_rows'] ?? [] as $row) {
            $records = $row['records'] ?? [];
            $rows[] = array_merge(
                [$row['kategori'] ?? '-'],
                array_slice($records, 0, 5),
                [$row['total_skor'] ?? '-', $row['kesimpulan'] ?? '-']
            );
        }

        $sheet->fromArray($rows, null, 'A' . $startRow);
        $this->formatParticipantMetaSection($sheet, $startRow, $detail);

        $headerRow = $startRow + 4;
        $lastRow = $startRow + count($rows) - 1;

        $sheet->mergeCells('B' . $headerRow . ':F' . $headerRow);
        $sheet->getStyle('B' . $headerRow . ':F' . $headerRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $headerRow . ':H' . $lastRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        if ($lastRow > $headerRow) {
            $sheet->getStyle('B' . ($headerRow + 1) . ':F' . $lastRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . ($headerRow + 1) . ':G' . $lastRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return $lastRow + 1 + $spacingRows;
    }

    protected function formatParticipantMetaSection(Worksheet $sheet, int $startRow, array $detail): void
    {
        $metaRows = [
            ['No Sampel', $detail['no_sampel'] ?? '-'],
            ['Nama PT', $detail['nama_pt'] ?? '-'],
            ['Masa Kerja', $detail['masa_kerja'] ?? '-'],
        ];

        for ($i = 0; $i < 3; $i++) {
            $row = $startRow + $i;

            $sheet->mergeCells('B' . $row . ':C' . $row);
            $sheet->getStyle('B' . $row . ':C' . $row)
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $sheet->mergeCells('D' . $row . ':E' . $row);
            $sheet->setCellValue('D' . $row, $metaRows[$i][0]);
            $sheet->getStyle('D' . $row . ':E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('D' . $row . ':E' . $row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->mergeCells('F' . $row . ':H' . $row);
            $value = (string) $metaRows[$i][1];
            if ($i === 2) {
                $sheet->setCellValueExplicit('F' . $row, $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue('F' . $row, $value);
            }
            $sheet->getStyle('F' . $row . ':H' . $row)
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $sheet->getStyle('A' . $startRow . ':A' . ($startRow + 2))->getFont()->setBold(true);
        $sheet->getStyle('A' . $startRow . ':H' . ($startRow + 2))
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    protected function applyPsikologiExcelColumnWidths(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(9);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(18);
    }

    public function sendDataAdmin(Request $request)
    {
        if (!isset($request->pekerja) || empty($request->pekerja)) {
            return response()->json([
                'message' => 'Daftar pekerja tidak boleh kosong'
            ], 422);
        }

        if (!$request->filled('no_order')) {
            return response()->json([
                'message' => 'No order tidak boleh kosong'
            ], 422);
        }

        $usedNoSampel = DataLapanganPsikologi::whereIn('no_sampel', function ($query) use ($request) {
            $query->select('no_sampel')
                ->from('order_detail')
                ->where('no_order', $request->no_order)
                ->where('periode', $request->periode ?? null)
                ->where('parameter', 'like', '%PSIKOLOGI%')
                ->where('is_active', true);
        })->pluck('no_sampel')->toArray();

        $no_sampelList = OrderDetail::where('no_order', $request->no_order)
            ->where('parameter', 'like', '%PSIKOLOGI%')
            ->where('periode', $request->periode ?? null)
            ->where('is_active', true)
            ->whereNotIn('no_sampel', $usedNoSampel)
            ->pluck('no_sampel')
            ->values();


        // Cek apakah cukup
        if (count($request->pekerja) > count($no_sampelList)) {
            return response()->json([
                'message' => 'Data yang dipilih melebihi jumlah yang di order, silahkan pilih data sejumlah ' . count($no_sampelList),
                'total_pekerja' => count($request->pekerja),
                'total_no_sampel' => count($no_sampelList),
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->pekerja as $index => $pekerjaId) {
                $data = DataLapanganPsikologi::find($pekerjaId);
                $no_sampel = $no_sampelList[$index] ?? null;

                if ($data && $no_sampel) {
                    $data->no_sampel = $no_sampel;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }
            // Rehitung sisa no_sampel yang belum dipakai setelah update
            $remainingNoSampel = OrderDetail::where('no_order', $request->no_order)
                ->where('parameter', 'like', '%PSIKOLOGI%')
                ->where('periode', $request->periode ?? null)
                ->where('is_active', true)
                ->whereNotIn('no_sampel', function ($query) use ($request) {
                    $query->select('no_sampel')
                        ->from('data_lapangan_psikologi')
                        ->whereNotNull('no_sampel');
                })->count();

            // Update waktu submit jika semua sudah terpakai
            $qrPsikologi = QrPsikologi::where('token', $request->token)->first();
            if ($qrPsikologi && $remainingNoSampel === 0) {
                QrPsikologi::where('id_quotation', $qrPsikologi->id_quotation)
                    ->update([
                        'submitted_at' => now(),
                        'is_finished' => true
                    ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Berhasil mengisi no sampel ke semua pekerja',
                'data' => [
                    'no_order' => $request->no_order,
                    'jumlah_pekerja' => count($request->pekerja),
                    'jumlah_no_sampel_tersisa' => $remainingNoSampel,
                    'status' => $remainingNoSampel === 0 ? 'selesai' : 'belum lengkap'
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganPsikologi::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}