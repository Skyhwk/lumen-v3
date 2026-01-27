<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganAir;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\MasterJabatan;
use App\Models\Parameter;

use App\Models\Titrimetri;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\Subkontrak;
use App\Models\WsValueAir;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Mpdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use ZipArchive;
use \App\Services\MpdfService as PDF;
use File;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlAirController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganAir::with('detail')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
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

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganAir::where('id', $request->id)->first();
                $data->rejected_at = null;
                $data->rejected_by = null;
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->save();
                
                app(NotificationFdlService::class)->sendApproveNotification($data->jenis_sampel, $data->no_sampel, $this->karyawan, $data->created_by);
                
                DB::commit();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di approve'
                ], 200);
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal approve ' . $th->getMessage(),
                    'line' => $th->getLine()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'Gagal approve'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganAir::where('id', $request->id)->first();

            $data->is_approve = false;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganAir::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification($data->jenis_sampel, $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
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
            $data = DataLapanganAir::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data no sample ' . $request->no_sampel . ' telah di hapus'
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
                $data = DataLapanganAir::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = date('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di block untuk user'
                ], 200);
            } else {
                $data = DataLapanganAir::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di unblock untuk user'
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
                $data = DataLapanganAir::where('no_sampel', $request->no_sampel_lama)->whereNull('no_sampel_lama')->get();

                $ws = WsValueAir::where('no_sampel', $request->no_sampel_lama)->get();

                if ($ws->isNotEmpty()) {
                    Titrimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                    Colorimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                    Gravimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                    Subkontrak::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                }

                $ws = WsValueAir::where('no_sampel', $request->no_sampel_lama)->update([
                    'no_sampel' => $request->no_sampel_baru,
                    'no_sampel_lama' => $request->no_sampel_lama
                ]);

                // Jika data ditemukan (Collection berisi elemen)
                $data->each(function ($item) use ($request) {
                    $item->no_sampel = $request->no_sampel_baru;
                    $item->no_sampel_lama = $request->no_sampel_lama;
                    $item->updated_by = $this->karyawan;  // Pastikan $this->karyawan valid
                    $item->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $item->save();  // Simpan perubahan untuk setiap item
                });
                // $data->no_sampel = $request->no_sampel_baru;
                // $data->no_sampel_lama = $request->no_sampel_lama;
                // $data->updated_by = $this->karyawan;
                // $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                // $data->save();

                // update OrderDetail
                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)
                    ->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }


                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        try {
            $data = DataLapanganAir::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail sample lapangan success';

            if ($data->debit_air == null) {
                $debit = 'Data By Customer';
            } else {
                $debit = $data->debit_air;
            }
            // dd($debit);

            return response()->json([
                'id' => $data->id,
                'no_sample' => $data->no_sampel,
                'no_order' => $data->detail->no_order,
                'sampler' => $data->created_by,
                'jam' => $data->jam_pengambilan ?? '-',
                'nama_perusahaan' => $data->detail->nama_perusahaan,
                'jenis' => explode('-', $data->detail->kategori_3)[1],
                'keterangan' => $data->keterangan,
                'jenis_produksi' => $data->jenis_produksi,
                'pengawet' => $data->jenis_pengawet,
                'teknik' => $data->teknik_sampling,
                'warna' => $data->warna,
                'bau' => $data->bau,
                'volume' => $data->volume,
                'suhu_air' => $data->suhu_air,
                'suhu_udara' => $data->suhu_udara,
                'ph' => $data->ph,
                'tds' => $data->tds,
                'dhl' => $data->dhl,
                'do' => $data->do,
                'debit' => $debit,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'koordinat' => $data->titik_koordinat,
                'message' => $this->resultx,
                'jumlah_titik_pengambilan' => $data->jumlah_titik_pengambilan,
                'jenis_fungsi_air' => $data->jenis_fungsi_air,
                'perlakuan_penyaringan' => $data->perlakuan_penyaringan,
                'pengendalian_mutu' => $data->pengendalian_mutu,
                'teknik_pengukuran_debit' => $data->teknik_pengukuran_debit,
                'klor_bebas' => $data->klor_bebas,
                'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
                'jenis_sample' => $data->jenis_sample,
                'ipal' => $data->status_kesediaan_ipal,
                'lokasi_sampling' => $data->lokasi_sampling,
                'diameter' => $data->diameter_sumur,
                'kedalaman1' => $data->kedalaman_sumur1,
                'kedalaman2' => $data->kedalaman_sumur2,
                'kedalamanair' => $data->kedalaman_air_terambil,
                'total_waktu' => $data->total_waktu,
                'kedalaman_titik' => $data->kedalaman_titik,
                'lokasi_pengambilan' => $data->lokasi_titik_pengambilan,
                'salinitas' => $data->salinitas,
                'kecepatan_arus' => $data->kecepatan_arus,
                'arah_arus' => $data->arah_arus,
                'pasang_surut' => $data->pasang_surut,
                'kecerahan' => $data->kecerahan,
                'lapisan_minyak' => $data->lapisan_minyak,
                'cuaca' => $data->cuaca,
                'info_tambahan' => $data->informasi_tambahan,
                'keterangan' => $data->keterangan,
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kondisi' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'sampah' => $data->sampah,
                'status' => '200'
            ], 200);

        } catch (\exeption $err) {
            dd($err);
        }
    }

    public function print(Request $request)
    {
        $no_sampel = str_replace("/", "-", strtoupper(trim($request->no_sampel)));
        $kondisi = $request->kondisi;
        $sing = array_map('trim', [$request->no_sampel]);

        $tggal = $request->tanggal;
        $blan = $request->bulan;
        $value = $request->value;

        // dd($sing);
        // dd($no_sampel);
        if ($request->no_sampel != null) {
            if ($kondisi === "1") {
                $fileName = $no_sampel . ".xlsx";

                $body = self::save($sing);
                // dd($body);
                self::saveK($kondisi, $body, $fileName);
                return response()->json([
                    'message' => 'Export Excel Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            } else if ($kondisi === "2") {
                $fileName = $no_sampel . ".pdf";
                $body = self::save($sing);
                self::saveK($kondisi, $body, $fileName);

                return response()->json([
                    'message' => 'Export PDF Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            }
        } else if ($request->value != null) {
            if ($kondisi === "1") {
                $fileName = "Sample.xlsx";
                $body = self::save($request->value);
                self::saveK($kondisi, $body, $fileName);

                return response()->json([
                    'message' => 'Export Multi Data Excel Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            } else if ($kondisi === "2") {
                $fileName = "Sample.pdf";
                $body = self::save($request->value);
                self::saveK($kondisi, $body, $fileName);

                return response()->json([
                    'message' => 'Export PDF Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            }
        } else if ($request->tanggal != null) {
            if ($kondisi === "1") {
                $my = explode("-", $tggal);
                $date = $my[2];
                $month = $my[1];
                $year = $my[0];
                $monthName = date('F', mktime(0, 0, 0, $month, 10));
                $tangg = $date . ' ' . $monthName . ' ' . $year;
                $fileName = $tangg . ".xlsx";
                $body = self::exportRekap($tggal, $blan);
                self::saveK($kondisi, $body, $fileName);
                return response()->json([
                    'message' => 'Export Excel Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            } else if ($kondisi === "2") {
                $my = explode("-", $tggal);
                $date = $my[2];
                $month = $my[1];
                $year = $my[0];
                $monthName = date('F', mktime(0, 0, 0, $month, 10));
                $tangg = $date . ' ' . $monthName . ' ' . $year;
                $fileName = $tangg . ".pdf";
                $body = self::exportRekap($tggal, $blan);
                self::saveK($kondisi, $body, $fileName);
                return response()->json([
                    'message' => 'Export Excel Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            }
        } else if ($request->bulan != null) {
            if ($kondisi === "1") {
                $my = explode("-", $blan);
                $month = $my[0];
                $year = $my[1];
                $monthName = date('F', mktime(0, 0, 0, $month, 10));
                $tangg = $monthName . ' ' . $year;
                $fileName = $tangg . ".xlsx";

                $body = self::exportRekap($tggal, $blan);
                self::saveK($kondisi, $body, $fileName);
                return response()->json([
                    'message' => 'Export Excel Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            } else if ($kondisi === "2") {
                $my = explode("-", $blan);
                $month = $my[0];
                $year = $my[1];
                // dd($my);
                $monthName = date('F', mktime(0, 0, 0, $month, 10));
                $tangg = $monthName . ' ' . $year;
                $fileName = $tangg . ".pdf";
                $body = self::exportRekap($tggal, $blan);
                self::saveK($kondisi, $body, $fileName);
                return response()->json([
                    'message' => 'Export Excel Berhasil',
                    'success' => true,
                    'status' => 200,
                    'link' => $fileName
                ], 200);
            }
        }
    }

    public function save($jenis)
    {
        // dd($jenis);
        $nilai = array();
        $spreadsheet = new Spreadsheet();
        $cell = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32);
        $sheet = $spreadsheet->getActiveSheet();
        //    dd($jenis);    
        foreach ($jenis as $key => $value) {

            $nilairow = $cell;
            $user = MasterKaryawan::where('nama_lengkap', $this->karyawan)->first();
            $jabatan = MasterJabatan::where('id', $user->id_jabatan)->first();
            $data = DataLapanganAir::with('detail')->where('no_sampel', $value)->first();
            // dd($data);
            $jenis = MasterSubKategori::where('id', explode('-', $data->detail->kategori_3)[0])->first();

            if ($data == null) {
                return false;
            }

            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            // dd('masuk');

            $drawing->setName('inti');
            $drawing->setDescription('inti');

            $drawing->setPath(public_path() . '/' . 'isl_logo.png');

            $drawing->setCoordinates('A' . $cell[1]);

            // $drawing->setOffsetX(110);
            $drawing->setWidth(150);
            $drawing->getShadow()->setVisible(true);
            $drawing->getShadow()->setDirection(45);

            $drawing->setWorksheet($spreadsheet->getActiveSheet());

            $formatText = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
            ];
            $stylecenter = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];
            $stylecenterB = [
                'font' => [
                    'bold' => true,
                    'underline' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];
            $styleBorder = [
                'font' => [
                    'bold' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];
            $sheet->setCellValue('B' . $cell[1], "LAPORAN DATA LAPANGAN AIR");
            $sheet->mergeCells('B' . $cell[1] . ':P' . $cell[1]);
            $styleArray = [
                'font' => [
                    'bold' => true,
                    'underline' => true,
                    'size' => 18,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];
            $spreadsheet->getActiveSheet()->getStyle('B' . $cell[1])->applyFromArray($styleArray);


            $sheet->getColumnDimension('E')->setWidth(2);
            $sheet->getColumnDimension('D')->setWidth(2);
            $sheet->getColumnDimension('M')->setWidth(2);

            $sheet->mergeCells('G' . $cell[3] . ':H' . $cell[3]);
            $sheet->mergeCells('I' . $cell[3] . ':J' . $cell[3]);
            $sheet->mergeCells('K' . $cell[3] . ':P' . $cell[3]);
            $sheet->mergeCells('G' . $cell[4] . ':H' . $cell[4]);
            $sheet->mergeCells('I' . $cell[4] . ':J' . $cell[4]);
            $sheet->mergeCells('K' . $cell[4] . ':P' . $cell[4]);

            $sheet->mergeCells('M' . $cell[23] . ':P' . $cell[23]);
            $sheet->mergeCells('M' . $cell[27] . ':P' . $cell[27]);
            $sheet->mergeCells('M' . $cell[28] . ':P' . $cell[28]);


            $sheet->mergeCells('A' . $cell[6] . ':D' . $cell[6]);
            $sheet->mergeCells('A' . $cell[7] . ':D' . $cell[7]);
            $sheet->mergeCells('A' . $cell[8] . ':D' . $cell[8]);
            $sheet->mergeCells('A' . $cell[9] . ':D' . $cell[9]);
            $sheet->mergeCells('A' . $cell[10] . ':D' . $cell[10]);
            $sheet->mergeCells('A' . $cell[11] . ':D' . $cell[11]);
            $sheet->mergeCells('A' . $cell[12] . ':D' . $cell[12]);
            $sheet->mergeCells('A' . $cell[13] . ':D' . $cell[13]);
            $sheet->mergeCells('A' . $cell[14] . ':D' . $cell[14]);
            $sheet->mergeCells('A' . $cell[15] . ':D' . $cell[15]);
            $sheet->mergeCells('A' . $cell[16] . ':D' . $cell[16]);
            $sheet->mergeCells('A' . $cell[17] . ':D' . $cell[17]);
            $sheet->mergeCells('A' . $cell[18] . ':D' . $cell[18]);
            $sheet->mergeCells('A' . $cell[19] . ':D' . $cell[19]);
            $sheet->mergeCells('A' . $cell[20] . ':D' . $cell[20]);
            $sheet->mergeCells('F' . $cell[6] . ':I' . $cell[6]);
            $sheet->mergeCells('F' . $cell[7] . ':I' . $cell[7]);
            $sheet->mergeCells('F' . $cell[8] . ':I' . $cell[8]);
            $sheet->mergeCells('F' . $cell[9] . ':I' . $cell[9]);
            $sheet->mergeCells('F' . $cell[10] . ':I' . $cell[10]);
            $sheet->mergeCells('F' . $cell[11] . ':I' . $cell[11]);
            $sheet->mergeCells('F' . $cell[12] . ':I' . $cell[12]);
            $sheet->mergeCells('F' . $cell[13] . ':I' . $cell[13]);
            $sheet->mergeCells('F' . $cell[14] . ':I' . $cell[14]);
            $sheet->mergeCells('F' . $cell[15] . ':I' . $cell[15]);
            $sheet->mergeCells('F' . $cell[16] . ':I' . $cell[16]);
            $sheet->mergeCells('F' . $cell[17] . ':I' . $cell[17]);
            $sheet->mergeCells('F' . $cell[18] . ':I' . $cell[18]);
            $sheet->mergeCells('F' . $cell[19] . ':I' . $cell[19]);
            $sheet->mergeCells('F' . $cell[20] . ':I' . $cell[20]);

            $sheet->mergeCells('K' . $cell[6] . ':L' . $cell[6]);
            $sheet->mergeCells('N' . $cell[6] . ':Q' . $cell[6]);
            $sheet->mergeCells('K' . $cell[7] . ':L' . $cell[7]);
            $sheet->mergeCells('N' . $cell[7] . ':Q' . $cell[7]);
            $sheet->mergeCells('K' . $cell[8] . ':L' . $cell[8]);
            $sheet->mergeCells('N' . $cell[8] . ':Q' . $cell[8]);
            $sheet->mergeCells('K' . $cell[9] . ':L' . $cell[9]);
            $sheet->mergeCells('N' . $cell[9] . ':R' . $cell[9]);

            $sheet->setCellValue('G' . $cell[3], 'No. ORDER');
            $sheet->setCellValue('I' . $cell[3], 'No. SAMPEL');
            $sheet->setCellValue('K' . $cell[3], 'NAMA SAMPLER');

            $sheet->setCellValue('A' . $cell[6], 'Nama Perusahaan');
            $sheet->setCellValue('E' . $cell[6], ':');
            $sheet->setCellValue('A' . $cell[7], 'Informasi Tambahan');
            $sheet->setCellValue('E' . $cell[7], ':');
            $sheet->setCellValue('A' . $cell[8], 'Keterangan');
            $sheet->setCellValue('E' . $cell[8], ':');
            $sheet->setCellValue('A' . $cell[9], 'Status Kesediaan Ipal');
            $sheet->setCellValue('E' . $cell[9], ':');
            $sheet->setCellValue('A' . $cell[10], 'Jenis Pengawet');
            $sheet->setCellValue('E' . $cell[10], ':');
            $sheet->setCellValue('A' . $cell[11], 'Lokasi Sampling');
            $sheet->setCellValue('E' . $cell[11], ':');
            $sheet->setCellValue('A' . $cell[12], 'Teknik Sampling');
            $sheet->setCellValue('E' . $cell[12], ':');
            $sheet->setCellValue('A' . $cell[13], 'Jam Pengambilan');
            $sheet->setCellValue('E' . $cell[13], ':');
            $sheet->setCellValue('A' . $cell[14], 'Perlakuan Penyaringan');
            $sheet->setCellValue('E' . $cell[14], ':');
            $sheet->setCellValue('A' . $cell[15], 'Pengendalian Mutu');
            $sheet->setCellValue('E' . $cell[15], ':');
            $sheet->setCellValue('A' . $cell[16], 'Volume');
            $sheet->setCellValue('E' . $cell[16], ':');
            $sheet->setCellValue('A' . $cell[17], 'Warna');
            $sheet->setCellValue('E' . $cell[17], ':');
            $sheet->setCellValue('A' . $cell[18], 'Bau');
            $sheet->setCellValue('E' . $cell[18], ':');
            $sheet->setCellValue('A' . $cell[19], 'PH');
            $sheet->setCellValue('E' . $cell[19], ':');
            $sheet->setCellValue('A' . $cell[20], 'DHL');
            $sheet->setCellValue('E' . $cell[20], ':');

            $sheet->setCellValue('K' . $cell[6], 'Suhu Air');
            $sheet->setCellValue('M' . $cell[6], ':');
            $sheet->setCellValue('K' . $cell[7], 'Suhu Udara');
            $sheet->setCellValue('M' . $cell[7], ':');
            $sheet->setCellValue('K' . $cell[8], 'Debit');
            $sheet->setCellValue('M' . $cell[8], ':');
            $sheet->setCellValue('K' . $cell[9], 'Koordinat');
            $sheet->setCellValue('M' . $cell[9], ':');

            $sheet->setCellValue('G' . $cell[4], $data->detail->no_order ?? '');
            $sheet->setCellValue('I' . $cell[4], $data->detail->no_sampel ?? '');
            $sheet->setCellValue('K' . $cell[4], $data->created_by ?? '') ?? '';
            $sheet->setCellValue('F' . $cell[6], $data->detail->nama_perusahaan ?? '');
            $sheet->setCellValue('F' . $cell[7], $data->informasi_tambahan ?? '');
            $sheet->setCellValue('F' . $cell[8], $data->detail->keterangan_1 ?? '');
            $sheet->setCellValue('F' . $cell[9], str_replace("_", " ", $data->status_kesediaan_ipal) ?? '');
            $sheet->setCellValue('F' . $cell[10], str_replace(str_split('\\/:*?"<>|+[]'), '', $data->jenis_pengawet ?? ''));
            $sheet->setCellValue('F' . $cell[11], $data->lokasi_sampling ?? '');
            $sheet->setCellValue('F' . $cell[12], $data->teknik_sampling ?? '');
            $sheet->setCellValue('F' . $cell[13], DATE('H:i:s', strtotime($data->created_at . "+7hours")) ?? '');
            $sheet->setCellValue('F' . $cell[14], str_replace("_", " ", $data->perlakuan_penyaringan) ?? '');
            $sheet->setCellValue('F' . $cell[15], str_replace(str_split('\\/:*?"<>|+[]'), '', $data->pengendalian_mutu ?? ''));
            $sheet->setCellValue('F' . $cell[16], $data->volume ?? '');
            $sheet->setCellValue('F' . $cell[17], str_replace("_", " ", $data->warna) ?? '');
            $sheet->setCellValue('F' . $cell[18], str_replace("_", " ", $data->bau) ?? '');
            $sheet->setCellValue('F' . $cell[19], $data->ph ?? '');
            $sheet->setCellValue('F' . $cell[20], $data->dhl ?? '');
            $sheet->setCellValue('N' . $cell[6], $data->suhu_air ?? '');
            $sheet->setCellValue('N' . $cell[7], $data->suhu_udara ?? '');
            $sheet->setCellValue('N' . $cell[8], $data->debit_air ?? '');
            $sheet->setCellValue('N' . $cell[9], $data->titik_koordinat ?? '') ?? '';

            $sheet->setCellValue('M' . $cell[27], '(' . $user->nama_lengkap . ' )' ?? '');
            $sheet->setCellValue('M' . $cell[28], $jabatan->nama_jabatan ?? '');

            setlocale(LC_TIME, 'id_ID.utf8');
            $hariIni = Carbon::now();
            $sheet->setCellValue('M' . $cell[23], 'Tangerang, ' . strftime('%d %B %Y', $hariIni->timestamp));

            $spreadsheet->getActiveSheet()->getStyle('F' . $cell[16])->applyFromArray($formatText);
            $spreadsheet->getActiveSheet()->getStyle('F' . $cell[19])->applyFromArray($formatText);
            $spreadsheet->getActiveSheet()->getStyle('F' . $cell[20])->applyFromArray($formatText);
            $spreadsheet->getActiveSheet()->getStyle('N' . $cell[6])->applyFromArray($formatText);
            $spreadsheet->getActiveSheet()->getStyle('N' . $cell[7])->applyFromArray($formatText);
            $spreadsheet->getActiveSheet()->getStyle('N' . $cell[8])->applyFromArray($formatText);
            $spreadsheet->getActiveSheet()->getStyle('M' . $cell[28])->applyFromArray($stylecenter);
            $spreadsheet->getActiveSheet()->getStyle('M' . $cell[23])->applyFromArray($stylecenter);
            $spreadsheet->getActiveSheet()->getStyle('M' . $cell[27])->applyFromArray($stylecenterB);
            $spreadsheet->getActiveSheet()->getStyle('G' . $cell[3] . ':P' . $cell[4])->applyFromArray($styleBorder);

            $sheet->getPageSetup()->setPrintArea('A' . $cell[0] . ':Q' . $cell[31]);
            $spreadsheet->getActiveSheet()->getPageSetup()
                ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            $spreadsheet->getActiveSheet()->getPageSetup()
                ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
            $spreadsheet->getActiveSheet()->setShowGridlines(False);
            $spreadsheet->getProperties()->setTitle($value);
            $cell = self::fungsiRow($nilairow);
        }

        return $spreadsheet;
    }

    public function fungsiRow($cell = array())
    {
        $sum = 32;
        foreach ($cell as $jumlah) {
            $nilai[] = (int) $jumlah + $sum;
        }
        return $nilai;
    }

    public function exportRekap($tanggal, $bulan)
    {
        // use Carbon\Carbon;
        if ($tanggal != null) {
            $date = Carbon::createFromFormat('Y-m-d', $tanggal);

            setlocale(LC_TIME, 'id_ID.utf8');
            $monthName = $date->locale('id')->monthName;

            $tangg = $date->day . ' ' . $monthName . ' ' . $date->year;

            $data = DataLapanganAir::with('detail')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->whereDay('created_at', $date->day)
                ->get();
            // dd($data);
            $jumlah_air = count($data);
            // dd($jumlah_air);
        } else if ($bulan != null) {

            $date = Carbon::createFromFormat('Y-m', $bulan);
            // dd($bulan);

            setlocale(LC_TIME, 'id_ID.utf8');
            $monthName = $date->locale('id')->monthName;

            // Menyusun string bulan
            $tangg = $monthName . ' ' . $date->year;

            // Query data berdasarkan bulan
            $data = DataLapanganAir::with('detail')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->get();

            // Menghitung jumlah data
            $jumlah_air = count($data);
        }



        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        //Style
        $styleBorder = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ];

        $styleBorderB = [
            'font' => [
                'size' => 12,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ],
        ];

        $stylecenter = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 24,
            ],
        ];

        //Column Width
        $sheet->getColumnDimension('A')->setWidth(2);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);
        $sheet->getColumnDimension('H')->setAutoSize(true);
        $sheet->getColumnDimension('I')->setAutoSize(true);
        $sheet->getColumnDimension('J')->setAutoSize(true);
        $sheet->getColumnDimension('K')->setAutoSize(true);
        $sheet->getColumnDimension('L')->setAutoSize(true);
        $sheet->getColumnDimension('M')->setAutoSize(true);
        $sheet->getColumnDimension('N')->setAutoSize(true);
        $sheet->getColumnDimension('O')->setAutoSize(true);
        $sheet->getColumnDimension('P')->setAutoSize(true);
        $sheet->getColumnDimension('Q')->setAutoSize(true);
        $sheet->getColumnDimension('R')->setAutoSize(true);
        $sheet->getColumnDimension('S')->setAutoSize(true);
        $sheet->getColumnDimension('T')->setAutoSize(true);
        $sheet->getColumnDimension('U')->setAutoSize(true);
        $sheet->getColumnDimension('V')->setAutoSize(true);
        $sheet->getColumnDimension('W')->setAutoSize(true);
        $sheet->getColumnDimension('X')->setAutoSize(true);
        $sheet->getColumnDimension('Y')->setAutoSize(true);
        $sheet->getColumnDimension('Z')->setAutoSize(true);
        $sheet->getColumnDimension('AA')->setAutoSize(true);
        $sheet->getColumnDimension('AB')->setAutoSize(true);
        $sheet->getColumnDimension('AC')->setAutoSize(true);
        $sheet->getColumnDimension('AD')->setAutoSize(true);
        $sheet->getColumnDimension('AE')->setAutoSize(true);
        $sheet->getColumnDimension('AF')->setAutoSize(true);
        $sheet->getColumnDimension('AG')->setAutoSize(true);
        $sheet->getColumnDimension('AH')->setAutoSize(true);
        $sheet->getColumnDimension('AI')->setAutoSize(true);
        $sheet->getColumnDimension('AJ')->setAutoSize(true);
        $sheet->getColumnDimension('AK')->setAutoSize(true);
        $sheet->getColumnDimension('AL')->setAutoSize(true);
        $sheet->getColumnDimension('AM')->setAutoSize(true);
        $sheet->getColumnDimension('AN')->setAutoSize(true);
        $sheet->getColumnDimension('AO')->setAutoSize(true);
        $sheet->getColumnDimension('AP')->setAutoSize(true);
        $sheet->getColumnDimension('AQ')->setAutoSize(true);
        $sheet->getColumnDimension('AR')->setAutoSize(true);
        $sheet->getColumnDimension('AS')->setAutoSize(true);
        $sheet->getColumnDimension('AT')->setAutoSize(true);

        //Header Title
        $sheet->setCellValue('B1', 'LIST REKAP DATA LAPANGAN AIR ' . strtoupper($tangg));
        $sheet->setCellValue('B4', ' No.');
        $sheet->setCellValue('C4', ' No Order');
        $sheet->setCellValue('D4', ' No Sampel');
        $sheet->setCellValue('E4', ' Nama Sampler');
        $sheet->setCellValue('F4', ' Nama Perusahaan');
        $sheet->setCellValue('G4', ' Kategori 3');
        $sheet->setCellValue('H4', ' Kedalaman Titik');
        $sheet->setCellValue('I4', ' Jenis Produksi');
        $sheet->setCellValue('J4', ' Lokasi Titik Pengambilan');
        $sheet->setCellValue('K4', ' Jenis Fungsi Air');
        $sheet->setCellValue('L4', ' Status Kesediaan Ipal');
        $sheet->setCellValue('M4', ' Jumlah Titik Pengambilan');
        $sheet->setCellValue('N4', ' Penamaan Titik');
        $sheet->setCellValue('O4', ' Penamaan Tambahan');
        $sheet->setCellValue('P4', ' Diameter Sumur');
        $sheet->setCellValue('Q4', ' Kedalaman Sumur 1');
        $sheet->setCellValue('R4', ' Kedalaman Sumur 2 ');
        $sheet->setCellValue('S4', ' Kedalaman Air Terambil');
        $sheet->setCellValue('T4', ' Total Waktu');
        $sheet->setCellValue('U4', ' Teknik Sampling');
        $sheet->setCellValue('V4', ' Jam Pengambilan');
        $sheet->setCellValue('W4', ' Volume');
        $sheet->setCellValue('X4', ' Jenis Pengawet');
        $sheet->setCellValue('Y4', ' Perlakuan Penyaringan');
        $sheet->setCellValue('Z4', ' Pengendalian Mutu');
        $sheet->setCellValue('AA4', ' Tekhnik Pengukuran Debit');
        $sheet->setCellValue('AB4', ' Debit Air');
        $sheet->setCellValue('AC4', ' DO');
        $sheet->setCellValue('AD4', ' PH');
        $sheet->setCellValue('AE4', ' Suhu Air');
        $sheet->setCellValue('AF4', ' Suhu Udara');
        $sheet->setCellValue('AG4', ' DHL');
        $sheet->setCellValue('AH4', ' Warna');
        $sheet->setCellValue('AI4', ' Bau');
        $sheet->setCellValue('AJ4', ' Salinitas');
        $sheet->setCellValue('AK4', ' Kecepatan Arus');
        $sheet->setCellValue('AL4', ' Arah Arus');
        $sheet->setCellValue('AM4', ' Pasang Surut');
        $sheet->setCellValue('AN4', ' Kecerahan');
        $sheet->setCellValue('AO4', ' Lapisan Minyak');
        $sheet->setCellValue('AP4', ' Cuaca');
        $sheet->setCellValue('AQ4', ' Klor Bebas');
        $sheet->setCellValue('AR4', ' Titik Koordinat');
        $sheet->setCellValue('AS4', ' Jam Penginputan');
        $sheet->setCellValue('AT4', ' Status Approve');
        $hariIni = Carbon::now();
        $sheet->setCellValue('B2', ' Tanggal Export : ' . strftime('%d %B %Y', $hariIni->getTimestamp()));
        $sheet->setCellValue('B3', ' Jumlah FDL Air : ' . $jumlah_air);
        $i = 5;
        $no = 1;


        foreach ($data as $key => $value) {

            $categoryIds = isset($value->detail) && isset($value->detail->kategori_3)
                ? explode(',', $value->detail->kategori_3)
                : [];

            $categori = !empty($categoryIds)
                ? MasterSubKategori::whereIn('id', $categoryIds)->first()
                : null;

            $sheet->setCellValue('B' . $i, $no++);
            $sheet->setCellValue('C' . $i, $value->detail->no_order ?? '');
            $sheet->setCellValue('D' . $i, $value->detail->no_sampel ?? '');
            $sheet->setCellValue('E' . $i, $value->created_by ?? '');
            $sheet->setCellValue('F' . $i, $value->detail->nama_perusahaan ?? '');
            $sheet->setCellValue('G' . $i, $categori->nama_sub_kategori ?? '');
            $sheet->setCellValue('H' . $i, $value->kedalaman_titik ?? '');
            $sheet->setCellValue('I' . $i, $value->jenis_produksi ?? '');
            $sheet->setCellValue('J' . $i, $value->lokasi_titik_pengambilan ?? '');
            $sheet->setCellValue('K' . $i, str_replace(str_split('\\/:*?"<>|+[]'), '', $value->jenis_fungsi_air ?? ''));
            $sheet->setCellValue('L' . $i, str_replace("_", " ", $value->status_kesediaan_ipal ?? ''));
            $sheet->setCellValue('M' . $i, $value->jumlah_titik_pengambilan ?? '');
            $sheet->setCellValue('N' . $i, $value->keterangan ?? '');
            $sheet->setCellValue('O' . $i, $value->informasi_tambahan ?? '');
            $sheet->setCellValue('P' . $i, $value->diameter_sumur ?? '');
            $sheet->setCellValue('Q' . $i, $value->kedalaman_sumur1 ?? '');
            $sheet->setCellValue('R' . $i, $value->kedalaman_sumur2 ?? '');
            $sheet->setCellValue('S' . $i, $value->kedalaman_air_terambil ?? '');
            $sheet->setCellValue('T' . $i, $value->total_waktu ?? '');
            $sheet->setCellValue('U' . $i, $value->teknik_sampling ?? '');
            $sheet->setCellValue('V' . $i, $value->jam_pengambilan ?? '');
            $sheet->setCellValue('W' . $i, $value->volume ?? '');
            $sheet->setCellValue('X' . $i, str_replace(str_split('\\/:*?"<>|+[]'), '', $value->jenis_pengawet ?? ''));
            $sheet->setCellValue('Y' . $i, str_replace("_", " ", $value->perlakuan_penyaringan ?? ''));
            $sheet->setCellValue('Z' . $i, str_replace(str_split('\\/:*?"<>|+[]'), '', $value->pengendalian_mutu ?? ''));
            $sheet->setCellValue('AA' . $i, $value->teknik_pengukuran_debit ?? '');
            $sheet->setCellValue('AB' . $i, str_replace(str_split('\\:*?"<>|+[]'), '', $value->debit_air ?? ''));
            $sheet->setCellValue('AC' . $i, $value->do ?? '');
            $sheet->setCellValue('AD' . $i, $value->ph ?? '');
            $sheet->setCellValue('AE' . $i, $value->suhu_air ?? '');
            $sheet->setCellValue('AF' . $i, $value->suhu_udara ?? '');
            $sheet->setCellValue('AG' . $i, $value->dhl ?? '');
            $sheet->setCellValue('AH' . $i, str_replace("_", " ", $value->warna ?? ''));
            $sheet->setCellValue('AI' . $i, str_replace("_", " ", $value->bau ?? ''));
            $sheet->setCellValue('AJ' . $i, $value->salinitas ?? '');
            $sheet->setCellValue('AK' . $i, $value->kecepatan_arus ?? '');
            $sheet->setCellValue('AL' . $i, $value->arah_arus ?? '');
            $sheet->setCellValue('AM' . $i, $value->pasang_surut ?? '');
            $sheet->setCellValue('AN' . $i, $value->kecerahan ?? '');
            $sheet->setCellValue('AO' . $i, $value->lapisan_minyak ?? '');
            $sheet->setCellValue('AP' . $i, $value->cuaca ?? '');
            $sheet->setCellValue('AQ' . $i, $value->klor_bebas ?? '');
            $sheet->setCellValue('AR' . $i, $value->titik_koordinat ?? '');
            $sheet->setCellValue('AS' . $i, $value->created_at ?? '');
            if ($value->is_approve == 0) {
                $sheet->setCellValue('AT' . $i, "-");
            } else if ($value->is_approve == 1) {
                $sheet->setCellValue('AT' . $i, "APPROVE");
            }

            $spreadsheet->getActiveSheet()->getStyle('B' . $i . ':AT' . $i)->applyFromArray($styleBorderB);
            $i++;
        }

        //Set Style To Column
        $spreadsheet->getActiveSheet()->getStyle('B4:AT4')->applyFromArray($styleBorder);
        $spreadsheet->getActiveSheet()->getStyle('B1')->applyFromArray($stylecenter);
        $spreadsheet->getProperties()->setTitle($value->detail->no_sampel);
        $sheet->mergeCells('B1:AS1');
        $sheet->mergeCells('B2:E2');
        $sheet->mergeCells('B3:E3');

        // Set page orientation to landscape
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        // Set paper size to A4
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL);

        //Create Excel
        return $spreadsheet;
    }

    public function saveK($kondisi = '', $spreadsheet = '', $fileName = '')
    {

        if ($kondisi === "1") {
            $writer = new Xlsx($spreadsheet);
            // dd($spreadsheet, $fileName);
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);
        } else if ($kondisi === "2") {
            // dd($spreadsheet, $fileName);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
            $writer->save($fileName);
        }
        return true;
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganAir::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}