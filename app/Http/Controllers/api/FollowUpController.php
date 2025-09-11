<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

// use Datatables;
use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\DFUS;
use App\Models\OrderHeader;
use App\Models\MasterPelanggan;
use App\Models\MasterKaryawan;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Yajra\DataTables\DataTables as DataTables;

class FollowUpController extends Controller
{
    public function index(Request $request)
    {
        $pelanggan = MasterPelanggan::with('kontak_pelanggan')->where('is_active', true);
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        switch ($jabatan) {
            case 24: // Sales Staff
                $pelanggan->where('sales_id', $this->user_id);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $pelanggan->whereIn('sales_id', $bawahan);
                break;
        }
        $pelanggan = $pelanggan->orderBy('master_pelanggan.id', 'desc');

        return Datatables::eloquent($pelanggan)
            ->filterColumn('kontak_pelanggan', function ($query, $keyword) {
                $query->whereHas('kontak_pelanggan', function ($q) use ($keyword) {
                    $q->where('no_tlp_perusahaan', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function getCustomerContact(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'pic_pelanggan'])->where('id', $request->id)->first();

        return response()->json(['data' => $data], 200);
    }

    public function randomstr($str, $no)
    {
        $str = str_replace(["'", '"', '+', '-', '=', ')', '(', '`', '~', '?', '/', '.', ',', '>', '<', ':', ';', '|', '!', '@', '#', '$', '%', '^', '&', '*', '[', ']', '{', '}'], '', str_replace([' ', '\t', ','], '', $str));
        return substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
    }

    public function saveFollowUp(Request $request)
    {
        // Generate no_urut
        $lastPelanggan = MasterPelanggan::orderBy('no_urut', 'desc')->first();
        $noUrut = str_pad($lastPelanggan ? (int) $lastPelanggan->no_urut + 1 : 1, 5, '0', STR_PAD_LEFT);

        // Generate id_pelanggan
        $timestamp = DATE('Y-m-d H:i:s');
        $namaPelangganUpper = strtoupper(str_replace([' ', '\t', ','], '', $request->nama_pelanggan));
        $idPelanggan = null;
        for ($i = 1; $i <= 10; $i++) {
            $generatedId = $this->randomstr($namaPelangganUpper, $i);
            if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                $idPelanggan = $generatedId;
                break;
            }
        }

        $no_tlp_perusahaan = preg_replace("/[^0-9]/", "", $request->no_tlp_perusahaan); // bersihin non-angka

        if (substr($no_tlp_perusahaan, 0, 2) === "62") { // convert depannya jadi 0
            $no_tlp_perusahaan = "0" . substr($no_tlp_perusahaan, 2);
        }

        // Menghapus PT, CV, UD, PD, Koperasi, Perum, Persero, BUMD, Yayasan (beserta variasi di belakang nama pelanggan)
        $clearNamaPelanggan = preg_replace('/(,?\s*\.?\s*(PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN))\s*$/i', '', $request->nama_pelanggan);

        // Cek duplikasi berdasarkan nama pelanggan dan no kontak
        // $existingData = MasterPelanggan::where('nama_pelanggan', $request->nama_pelanggan)
        //     ->orWhere('id_pelanggan', $idPelanggan)
        //     ->orWhereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
        //         $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
        //     })->first();

        $existingData = MasterPelanggan::whereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
            $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
        })->first();

        if ($existingData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pelanggan dengan nama dan atau nomor kontak sudah ada.'
            ], 401);
        }

        $existingData = MasterPelanggan::where('nama_pelanggan', 'like', '%' . $clearNamaPelanggan . '%')
            ->orWhereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
                $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
            })->first();

        if ($existingData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pelanggan dengan nama dan atau nomor kontak sudah ada.'
            ], 401);
        }

        $followUp = new MasterPelanggan;

        $followUp->id_cabang = 1;
        $followUp->no_urut = $noUrut;
        $followUp->id_pelanggan = $idPelanggan;
        $followUp->nama_pelanggan = $request->nama_pelanggan;
        $followUp->sales_id = $this->user_id;
        $followUp->sales_penanggung_jawab = $this->karyawan;
        $followUp->created_by = $this->karyawan;
        $followUp->created_at = $timestamp;
        $followUp->save();

        $followUp->kontak_pelanggan()->create([
            'pelanggan_id' => $followUp->id,
            'no_tlp_perusahaan' => $no_tlp_perusahaan
        ]);

        return response()->json(['message' => 'Created Successfully'], 200);
    }

    public function dfus(Request $request)
    {
        $dfus = DFUS::select('dfus.*')
            ->addSelect('p.id_pelanggan as idPelanggan', 'p.nama_pelanggan as namaPelanggan')
            ->join('master_pelanggan as p', function ($join) {
                $join->on('p.id_pelanggan', '=', 'dfus.id_pelanggan')->where('p.is_active', true);
            })
            ->where('dfus.tanggal', $request->tanggal ?: date('Y-m-d'))
            ->orderBy('dfus.tanggal', 'desc')
            ->orderBy('dfus.jam', 'desc');

        switch ($request->attributes->get('user')->karyawan->id_jabatan) {
            case 24: // Sales Staff
                $dfus->where('dfus.sales_penanggung_jawab', $this->karyawan);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('nama_lengkap')
                    ->toArray();
                array_push($bawahan, $this->karyawan);

                $dfus->whereIn('dfus.sales_penanggung_jawab', $bawahan);
                break;
        }

        return DataTables::of($dfus)
            ->editColumn('pelanggan', fn($row) => [
                'id_pelanggan'  => $row->idPelanggan,
                'nama_pelanggan' => $row->namaPelanggan,
            ])
            // ->addColumn('status_order', fn($row) => OrderHeader::where('id_pelanggan', $row->id_pelanggan)->where('is_active', true)->exists() ? 'REPEAT' : 'NEW')
            ->addColumn('status_order', fn() => "Coming Soon")
            ->addColumn('log_webphone', fn($row) => $row->getLogWebphoneAttribute()->toArray())
            ->make(true);
    }

    public function saveDFUS(Request $request)
    {
        $message = null;

        switch ($request->action) {
            case 'updateDFUS':
                $dfus = DFUS::where('id', $request->id)->first();

                if ($request->pic_pelanggan)
                    $dfus->pic_pelanggan = $request->pic_pelanggan;
                if ($request->email_pic)
                    $dfus->email_pic = $request->email_pic;
                if ($request->no_pic)
                    $dfus->no_pic = $request->no_pic;
                if ($request->status)
                    $dfus->status = $request->status == '-1' ? null : $request->status;
                if ($request->keterangan)
                    $dfus->keterangan = $request->keterangan;

                if ($request->column_name && $request->column_name != "") {
                    $dfus->{$request->column_name} = $request->value;
                }

                $dfus->updated_by = $this->karyawan;
                $dfus->save();

                $message = 'Saved Successfully.';
                break;

            case 'updateForecast':
                // Check for update or create new record
                $check = DFUS::where('id', $request->id)->first();
                $oldForecast = $check ? $check->forecast : null;
                $newForecast = date('Y-m-d H:i:s', strtotime($request->forecast));
                if ($oldForecast) {
                    // Update forecast on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast = $newForecast;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Update forecast on created before
                    $forecasted = DFUS::where(['id_pelanggan' => $request->id_pelanggan, 'tanggal' => date('Y-m-d', strtotime($oldForecast)), 'jam' => date('H:i:s', strtotime($oldForecast))])->first();
                    $forecasted->tanggal = date('Y-m-d', strtotime($newForecast));
                    $forecasted->jam = date('H:i:s', strtotime($newForecast));
                    $dfus->updated_by = $this->karyawan;
                    $forecasted->save();
                } else {
                    // Update forecast on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast = $newForecast;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Create new record
                    $dfus = new DFUS;
                    $dfus->id_pelanggan = $request->id_pelanggan;
                    $dfus->kontak = $request->kontak;
                    $dfus->sales_penanggung_jawab = $request->sales_penanggung_jawab;
                    $dfus->tanggal = $request->tanggal;
                    $dfus->jam = $request->jam;
                    $dfus->created_by = $this->karyawan;
                    $dfus->save();
                };

                $message = 'Saved Successfully.';
                break;

            case 'updateForecastPO':
                // Check for update or create new record
                $check = DFUS::where('id', $request->id)->first();
                $oldForecastPO = $check ? $check->forecast_po : null;
                $newForecastPO = date('Y-m-d H:i:s', strtotime($request->forecast_po));
                if ($oldForecastPO) {
                    // Update forecast_po on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast_po = $newForecastPO;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Update forecast_po on created before
                    $forecastedPO = DFUS::where(['id_pelanggan' => $request->id_pelanggan, 'tanggal' => date('Y-m-d', strtotime($oldForecastPO))])->first();
                    $forecastedPO->tanggal = date('Y-m-d', strtotime($newForecastPO));
                    $dfus->updated_by = $this->karyawan;
                    $forecastedPO->save();
                } else {
                    // Update forecast_po on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast_po = $newForecastPO;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Create new record
                    $dfus = new DFUS;
                    $dfus->id_pelanggan = $request->id_pelanggan;
                    $dfus->kontak = $request->kontak;
                    $dfus->sales_penanggung_jawab = $request->sales_penanggung_jawab;
                    $dfus->tanggal = $request->tanggal;
                    // $dfus->keterangan = 'Created PO';
                    $dfus->created_by = $this->karyawan;
                    $dfus->save();
                };

                $message = 'Saved Successfully.';
                break;

            default:
                if ($request->has('array_data')) {
                    $data = [];
                    foreach ($request->array_data as $item) {
                        if (!isset($item['id_pelanggan']) || !isset($item['kontak_pelanggan']) || !isset($item['sales_penanggung_jawab'])) {
                            continue;
                        }

                        if (preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], $item['kontak_pelanggan']) == '') {
                            continue;
                        }

                        $data[] = [
                            'id_pelanggan' => $item['id_pelanggan'],
                            'kontak' => 'Perusahaan - ' . preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], $item['kontak_pelanggan']),
                            'sales_penanggung_jawab' => $item['sales_penanggung_jawab'],
                            'tanggal' => Carbon::now()->format('Y-m-d'),
                            'jam' => Carbon::now()->format('H:i:s'),
                            'created_by' => $this->karyawan,
                        ];
                    }

                    if (!empty($data)) {
                        DFUS::insert($data);
                        $message = 'Data berhasil disimpan.';
                    } else {
                        $message = 'Tidak ada data valid untuk disimpan.';
                    }
                } else {
                    if (!$request->id_pelanggan || !$request->kontak || !$request->sales_penanggung_jawab || !$request->tanggal || !$request->jam) {
                        $message = 'Data tidak lengkap.';
                    } else {
                        $dfus = new DFUS;
                        $dfus->id_pelanggan = $request->id_pelanggan;
                        $dfus->kontak = $request->kontak;
                        $dfus->sales_penanggung_jawab = $request->sales_penanggung_jawab;
                        $dfus->tanggal = $request->tanggal;
                        $dfus->jam = $request->jam;
                        $dfus->created_by = $this->karyawan;
                        $dfus->save();

                        $message = 'Data berhasil disimpan.';
                    }
                }
                break;
        }

        return response()->json(['message' => $message, 'status' => 'success'], 200);
    }

    public function exportDFUS(Request $request)
    {
        $type = $request->input('type', 'harian');
        $tanggal = $request->input('date', Carbon::now()->format('Y-m-d'));
        $batchSize = 1000;

        $hariIndo = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['No', 'Hari', 'Tanggal', 'Jam', 'Nama Pelanggan', 'No. Telepon', 'PIC Pelanggan', 'E-Mail PIC', 'No. Telepon PIC', 'Sales Penanggung Jawab', 'Call Status', 'Forecast FU', 'Forecast PO', 'Status', 'Keterangan'];
        $col = 'A';

        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $sheet->getStyle("A1:O1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $baseQuery = DFUS::query();

        if ($type === 'bulanan') {
            $baseQuery->whereMonth('tanggal', date('m', strtotime($tanggal)))->whereYear('tanggal', date('Y', strtotime($tanggal)));
        } else {
            $baseQuery->whereDate('tanggal', $tanggal);
        }

        switch ($jabatan) {
            case 24: // Sales Staff
                $baseQuery->where('sales_penanggung_jawab', $this->karyawan);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('nama_lengkap')
                    ->toArray();

                array_push($bawahan, $this->karyawan);
                $baseQuery->whereIn('sales_penanggung_jawab', $bawahan);
                break;

            default:
                // no extra filter
                break;
        }

        $totalRecords = $baseQuery->count();
        $totalBatches = ceil($totalRecords / $batchSize);

        $rowNumber = 2;
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $offset = $batch * $batchSize;

            $dfusQuery = clone $baseQuery;
            $dfusQuery->with('pelanggan')
                ->orderBy('dfus.tanggal', 'desc')
                ->orderBy('dfus.jam')
                ->offset($offset)
                ->limit($batchSize);

            $dfusBatch = $dfusQuery->get();

            foreach ($dfusBatch as $index => $row) {
                $globalIndex = $offset + $index;
                $hari = $hariIndo[Carbon::parse($row->tanggal)->format('l')];

                $callStatus = '-';
                if (!empty($row->log_webphone)) {
                    $logs = $row->log_webphone;
                    $statusList = [];

                    foreach ($logs as $log) {
                        $durasi = explode(":", $log->time ?? "");
                        $jam = isset($durasi[0]) ? (int)$durasi[0] : 0;
                        $menit = isset($durasi[1]) ? (int)$durasi[1] : 0;
                        $detik = isset($durasi[2]) ? (int)$durasi[2] : 0;

                        $formatDurasi = [];
                        if ($jam > 0) $formatDurasi[] = "{$jam} jam";
                        if ($menit > 0) $formatDurasi[] = "{$menit} menit";
                        if ($detik > 0 || empty($formatDurasi)) $formatDurasi[] = "{$detik} detik";

                        $statusLog = $log->status_call ?? '-';
                        if (!empty($log->time)) {
                            $statusLog .= "\n" . implode(" ", $formatDurasi);
                        }
                        $statusLog .= "\n" . Carbon::parse($log->created_at)->translatedFormat('d F Y H:i:s');

                        $statusList[] = $statusLog;
                    }

                    $callStatus = implode("\n\n", $statusList);
                }

                $sheet->setCellValue('A' . $rowNumber, $globalIndex + 1);
                $sheet->setCellValue('B' . $rowNumber, $hari);
                $sheet->setCellValue('C' . $rowNumber, $row->tanggal);
                $sheet->setCellValue('D' . $rowNumber, $row->jam ?? '-');
                $sheet->setCellValue('E' . $rowNumber, $row->pelanggan->nama_pelanggan ?? '-');
                $sheet->setCellValue('F' . $rowNumber, $row->kontak ?? '-');
                $sheet->setCellValue('G' . $rowNumber, $row->pic_pelanggan ?? '-');
                $sheet->setCellValue('H' . $rowNumber, $row->email_pic ?? '-');
                $sheet->setCellValue('I' . $rowNumber, $row->no_pic ?? '-');
                $sheet->setCellValue('J' . $rowNumber, $row->sales_penanggung_jawab ?? '-');
                $sheet->setCellValue('K' . $rowNumber, $callStatus);
                $sheet->setCellValue('L' . $rowNumber, $row->forecast ?? '-');
                $sheet->setCellValue('M' . $rowNumber, $row->forecast_po ?? '-');
                $sheet->setCellValue('N' . $rowNumber, ($row->status == 'qt') ? 'Quotation' : (($row->status == 'req_qt') ? 'Request Quotation' : '-'));
                $sheet->setCellValue('O' . $rowNumber, $row->keterangan ?? '-');

                $sheet->getStyle('K' . $rowNumber)->getAlignment()->setWrapText(true);

                $rowNumber++;

                unset($row);
            }

            unset($dfusBatch);

            if (function_exists('gc_collect_cycles'))
                gc_collect_cycles();
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $cellRange = "A1:$highestColumn$highestRow";

        $sheet->getStyle($cellRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        foreach (['E', 'F', 'G', 'H', 'K'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $path = public_path() . '/dfus/';
        $writer = new Xlsx($spreadsheet);
        $fileName = 'DFUS_' . str_replace('-', '_', $tanggal) . '.xlsx';
        $writer->save($path . $fileName);

        return response()->json(['data' => $fileName], 200);
    }
}
