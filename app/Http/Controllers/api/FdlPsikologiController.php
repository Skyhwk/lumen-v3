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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
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
        if (isset($request->no_document) && $request->no_document != null) {
            $data = DataLapanganPsikologi::where('no_order', $request->no_document)->get();
            $header = DataLapanganPsikologi::where('no_order', $request->no_document)->first();

            $noSampelTerkumpul = $data->pluck('no_sampel')->toArray();
            
            $order_header = OrderHeader::where('no_order', $request->no_document)->first();
            $order_detail = OrderDetail::where('no_order', $order_header->no_order)->where('is_active', true)->whereJsonContains('parameter', '318;Psikologi')->get();
            return response()->json([
                'message' => 'Data Dengan No Order ' . $request->no_document,
                'nama_pekerja' => $data->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nama' => $item->nama_pekerja,
                        'divisi' => $item->divisi,
                        'lama_kerja' => $item->lama_kerja,
                        'no_sampel' => $item->no_sampel,
                        'jenis_kelamin' => $item->jenis_kelamin,
                        'created_at' => $item->created_at,
                        'hasil' => $item->hasil
                    ];
                }),
                'nama_pt' => $header->nama_perusahaan ?? '-',
                'no_order' => $header->no_order ?? '-',
                'periode' => $header->periode ?? '-',
                'order_detail' => $order_detail

            ], 200);
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
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